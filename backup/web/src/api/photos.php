<?php
session_start();
// API pour la gestion des photos d'interventions
header('Content-Type: application/json');

// Vérification de la session
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration PHP pour les gros fichiers (spécialement pour mobile)
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300); // 5 minutes
ini_set('max_input_time', 300);
ini_set('post_max_size', '50M');
ini_set('upload_max_filesize', '50M');

// Log de débogage
error_log("API Photos - Méthode: " . $_SERVER['REQUEST_METHOD']);
error_log("API Photos - Données POST: " . print_r($_POST, true));
error_log("API Photos - Fichiers: " . print_r($_FILES, true));
error_log("API Photos - Limites PHP: post_max_size=" . ini_get('post_max_size') . ", upload_max_filesize=" . ini_get('upload_max_filesize'));

// Inclure l'utilitaire de gestion des permissions
require_once __DIR__ . '/../utils/permissions_helper.php';

// Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/interventions/');
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Fonction pour récupérer les paramètres de configuration
function getPhotoSettings($pdo) {
    $defaults = [
        'max_width' => 1920,
        'max_height' => 1080,
        'thumb_size' => 300,
        'max_file_size' => 10,
        'quality' => 85
    ];
    
    try {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM photos_settings");
        while ($row = $stmt->fetch()) {
            if (isset($defaults[$row['setting_name']])) {
                $defaults[$row['setting_name']] = (int)$row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        // Table n'existe pas encore, utiliser les valeurs par défaut
    }
    
    return $defaults;
}

// Créer le dossier d'upload s'il n'existe pas avec gestion des permissions
$uploadResult = createDirectoryWithPermissions(UPLOAD_DIR);
if (!$uploadResult['success']) {
    error_log("API Photos - Erreur création dossier: " . $uploadResult['message']);
    // Continuer quand même, l'erreur sera gérée lors de l'upload
}

// Connexion à la base de données (même configuration que index.php)
// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    // Gérer l'erreur de connexion
    throw $e;
}

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Fonction pour convertir les tailles PHP (ex: "8M") en bytes
function parseSize($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
        return round($size);
    }
}

function resizeImage($source, $destination, $maxWidth, $maxHeight, $quality = 85) {
    // Vérifier si l'extension GD est disponible
    if (!extension_loaded('gd')) {
        error_log("Extension GD non disponible, copie simple du fichier");
        return copy($source, $destination);
    }
    
    $imageInfo = getimagesize($source);
    if (!$imageInfo) {
        return false;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];
    
    // Calculer les nouvelles dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    if ($ratio >= 1) {
        // L'image est déjà plus petite, on la copie simplement
        return copy($source, $destination);
    }
    
    $newWidth = round($width * $ratio);
    $newHeight = round($height * $ratio);
    
    // Créer l'image source avec vérification des fonctions
    $sourceImage = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            if (function_exists('imagecreatefromjpeg')) {
                $sourceImage = imagecreatefromjpeg($source);
            }
            break;
        case IMAGETYPE_PNG:
            if (function_exists('imagecreatefrompng')) {
                $sourceImage = imagecreatefrompng($source);
            }
            break;
        case IMAGETYPE_GIF:
            if (function_exists('imagecreatefromgif')) {
                $sourceImage = imagecreatefromgif($source);
            }
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $sourceImage = imagecreatefromwebp($source);
            }
            break;
        default:
            error_log("Type d'image non supporté: $type");
            return copy($source, $destination);
    }
    
    // Si impossible de créer l'image source, copier simplement
    if (!$sourceImage) {
        error_log("Impossible de créer l'image source, copie simple");
        return copy($source, $destination);
    }
    
    if (!$sourceImage) {
        error_log("Impossible de créer l'image source, copie simple");
        return copy($source, $destination);
    }
    
    // Vérifier si les fonctions de manipulation d'image sont disponibles
    if (!function_exists('imagecreatetruecolor')) {
        error_log("Fonctions de manipulation d'image non disponibles, copie simple");
        imagedestroy($sourceImage);
        return copy($source, $destination);
    }
    
    // Créer l'image de destination
    $destImage = imagecreatetruecolor($newWidth, $newHeight);
    if (!$destImage) {
        error_log("Impossible de créer l'image de destination, copie simple");
        imagedestroy($sourceImage);
        return copy($source, $destination);
    }
    
    // Préserver la transparence pour PNG et GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        if (function_exists('imagealphablending') && function_exists('imagesavealpha')) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            if (function_exists('imagecolorallocatealpha') && function_exists('imagefilledrectangle')) {
                $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
                imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
        }
    }
    
    // Redimensionner
    if (!function_exists('imagecopyresampled')) {
        error_log("Fonction imagecopyresampled non disponible, copie simple");
        imagedestroy($sourceImage);
        imagedestroy($destImage);
        return copy($source, $destination);
    }
    
    imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Sauvegarder avec vérification des fonctions
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            if (function_exists('imagejpeg')) {
                $result = imagejpeg($destImage, $destination, $quality);
            }
            break;
        case IMAGETYPE_PNG:
            if (function_exists('imagepng')) {
                $result = imagepng($destImage, $destination, 9);
            }
            break;
        case IMAGETYPE_GIF:
            if (function_exists('imagegif')) {
                $result = imagegif($destImage, $destination);
            }
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $result = imagewebp($destImage, $destination, $quality);
            }
            break;
    }
    
    // Si la sauvegarde a échoué, copier le fichier original
    if (!$result) {
        error_log("Échec de la sauvegarde de l'image redimensionnée, copie simple");
        $result = copy($source, $destination);
    }
    
    // Nettoyer la mémoire
    imagedestroy($sourceImage);
    imagedestroy($destImage);
    
    return $result;
}

// Traitement des requêtes
$method = $_SERVER['REQUEST_METHOD'];

// Récupérer l'ID d'intervention depuis GET ou POST selon la méthode
$intervention_id = '';
if ($method === 'GET' || $method === 'DELETE') {
    $intervention_id = trim($_GET['intervention_id'] ?? '');
} elseif ($method === 'POST') {
    $intervention_id = trim($_POST['intervention_id'] ?? '');
}

error_log("API Photos - intervention_id récupéré: '$intervention_id' (méthode: $method)");

switch ($method) {
    case 'GET':
        // Récupérer les photos d'une intervention
        if (empty($intervention_id)) {
            sendResponse(false, 'ID d\'intervention requis');
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM intervention_photos WHERE intervention_id = :intervention_id ORDER BY uploaded_at DESC");
            $stmt->execute([':intervention_id' => $intervention_id]);
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ajouter l'URL complète pour chaque photo
            foreach ($photos as &$photo) {
                // Utiliser des chemins absolus depuis la racine du site
                $photo['url'] = '/uploads/interventions/' . $photo['filename'];
                $photo['thumbnail_url'] = '/uploads/interventions/thumb_' . $photo['filename'];
            }
            
            sendResponse(true, 'Photos récupérées avec succès', $photos);
        } catch (PDOException $e) {
            sendResponse(false, 'Erreur de base de données : ' . $e->getMessage());
        }
        break;
        
    case 'POST':
        // Upload d'une nouvelle photo
        if (empty($intervention_id)) {
            sendResponse(false, 'ID d\'intervention requis');
        }
        
        // Vérification détaillée des erreurs d'upload
        if (!isset($_FILES['photo'])) {
            sendResponse(false, 'Aucun fichier reçu');
        }
        
        $file = $_FILES['photo'];
        $uploadError = $file['error'];
        
        // Messages d'erreur détaillés selon le code d'erreur PHP
        switch ($uploadError) {
            case UPLOAD_ERR_OK:
                break; // Pas d'erreur
            case UPLOAD_ERR_INI_SIZE:
                sendResponse(false, 'Fichier trop volumineux (limite serveur PHP: ' . ini_get('upload_max_filesize') . ')');
                break;
            case UPLOAD_ERR_FORM_SIZE:
                sendResponse(false, 'Fichier trop volumineux (limite formulaire)');
                break;
            case UPLOAD_ERR_PARTIAL:
                sendResponse(false, 'Upload interrompu (connexion instable)');
                break;
            case UPLOAD_ERR_NO_FILE:
                sendResponse(false, 'Aucun fichier sélectionné');
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                sendResponse(false, 'Erreur serveur: dossier temporaire manquant');
                break;
            case UPLOAD_ERR_CANT_WRITE:
                sendResponse(false, 'Erreur serveur: impossible d\'écrire le fichier');
                break;
            case UPLOAD_ERR_EXTENSION:
                sendResponse(false, 'Upload bloqué par une extension PHP');
                break;
            default:
                sendResponse(false, 'Erreur d\'upload inconnue (code: ' . $uploadError . ')');
        }
        
        $description = $_POST['description'] ?? '';
        
        // Log des informations du fichier
        error_log("API Photos - Fichier reçu: " . $file['name'] . " (" . round($file['size']/1024/1024, 2) . "MB)");
        
        // Récupérer les paramètres configurables
        $settings = getPhotoSettings($pdo);
        $maxFileSize = $settings['max_file_size'] * 1024 * 1024; // Convertir MB en bytes
        
        // Vérifications avec logs détaillés
        if ($file['size'] > $maxFileSize) {
            error_log("API Photos - Fichier trop volumineux: " . $file['size'] . " bytes > " . $maxFileSize . " bytes");
            sendResponse(false, "Fichier trop volumineux (" . round($file['size']/1024/1024, 2) . "MB > {$settings['max_file_size']}MB)");
        }
        
        // Vérification supplémentaire pour les limites PHP
        $phpMaxSize = min(
            parseSize(ini_get('post_max_size')),
            parseSize(ini_get('upload_max_filesize'))
        );
        if ($file['size'] > $phpMaxSize) {
            error_log("API Photos - Fichier dépasse les limites PHP: " . $file['size'] . " > " . $phpMaxSize);
            sendResponse(false, "Fichier trop volumineux pour le serveur (" . round($file['size']/1024/1024, 2) . "MB)");
        }
        
        if (!in_array($file['type'], ALLOWED_TYPES)) {
            sendResponse(false, 'Type de fichier non autorisé');
        }
        
        // Générer un nom de fichier unique
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $intervention_id . '_' . uniqid() . '.' . $extension;
        $filepath = UPLOAD_DIR . $filename;
        $thumbpath = UPLOAD_DIR . 'thumb_' . $filename;
        
        try {
            // Redimensionner et sauvegarder l'image principale
            if (!resizeImage($file['tmp_name'], $filepath, $settings['max_width'], $settings['max_height'], $settings['quality'])) {
                sendResponse(false, 'Erreur lors du redimensionnement de l\'image');
            }
            
            // Créer une miniature
            if (!resizeImage($filepath, $thumbpath, $settings['thumb_size'], $settings['thumb_size'], $settings['quality'])) {
                // Si la création de miniature échoue, on continue quand même
                copy($filepath, $thumbpath);
            }
            
            // Obtenir les dimensions de l'image finale
            $imageInfo = getimagesize($filepath);
            $width = $imageInfo[0] ?? null;
            $height = $imageInfo[1] ?? null;
            
            // Enregistrer en base de données
            $stmt = $pdo->prepare("
                INSERT INTO intervention_photos 
                (intervention_id, filename, original_filename, file_size, mime_type, width, height, description, uploaded_by) 
                VALUES (:intervention_id, :filename, :original_filename, :file_size, :mime_type, :width, :height, :description, :uploaded_by)
            ");
            
            $stmt->execute([
                ':intervention_id' => $intervention_id,
                ':filename' => $filename,
                ':original_filename' => $file['name'],
                ':file_size' => filesize($filepath),
                ':mime_type' => $file['type'],
                ':width' => $width,
                ':height' => $height,
                ':description' => $description,
                ':uploaded_by' => $_SESSION['user_id'] ?? 'api'
            ]);
            
            $photo_id = $pdo->lastInsertId();
            
            sendResponse(true, 'Photo uploadée avec succès', [
                'id' => $photo_id,
                'filename' => $filename,
                'url' => '/uploads/interventions/' . $filename,
                'thumbnail_url' => '/uploads/interventions/thumb_' . $filename
            ]);
            
        } catch (PDOException $e) {
            // Nettoyer les fichiers en cas d'erreur
            if (file_exists($filepath)) unlink($filepath);
            if (file_exists($thumbpath)) unlink($thumbpath);
            sendResponse(false, 'Erreur de base de données : ' . $e->getMessage());
        } catch (Exception $e) {
            sendResponse(false, 'Erreur : ' . $e->getMessage());
        }
        break;
        
    case 'DELETE':
        // Supprimer une photo
        $photo_id = $_GET['photo_id'] ?? '';
        if (empty($photo_id)) {
            sendResponse(false, 'ID de photo requis');
        }
        
        try {
            // Récupérer les informations de la photo
            $stmt = $pdo->prepare("SELECT * FROM intervention_photos WHERE id = :id");
            $stmt->execute([':id' => $photo_id]);
            $photo = $stmt->fetch();
            
            if (!$photo) {
                sendResponse(false, 'Photo non trouvée');
            }
            
            // Supprimer les fichiers
            $filepath = UPLOAD_DIR . $photo['filename'];
            $thumbpath = UPLOAD_DIR . 'thumb_' . $photo['filename'];
            
            if (file_exists($filepath)) unlink($filepath);
            if (file_exists($thumbpath)) unlink($thumbpath);
            
            // Supprimer de la base de données
            $stmt = $pdo->prepare("DELETE FROM intervention_photos WHERE id = :id");
            $stmt->execute([':id' => $photo_id]);
            
            sendResponse(true, 'Photo supprimée avec succès');
            
        } catch (PDOException $e) {
            sendResponse(false, 'Erreur de base de données : ' . $e->getMessage());
        }
        break;
        
    default:
        sendResponse(false, 'Méthode non autorisée');
}
?>