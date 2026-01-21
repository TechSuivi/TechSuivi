<?php
/**
 * Utilitaire de gestion des permissions pour TechSuivi
 * Évite les erreurs mkdir() et gère les permissions automatiquement
 */

/**
 * Crée un dossier avec gestion d'erreurs et permissions
 * @param string $path Chemin du dossier à créer
 * @param int $permissions Permissions à appliquer (par défaut 0775)
 * @param bool $recursive Création récursive (par défaut true)
 * @return array ['success' => bool, 'message' => string, 'path' => string]
 */
function createDirectoryWithPermissions($path, $permissions = 0775, $recursive = true) {
    $result = [
        'success' => false,
        'message' => '',
        'path' => $path
    ];
    
    // Vérifier si le dossier existe déjà
    if (is_dir($path)) {
        $result['success'] = true;
        $result['message'] = "Le dossier existe déjà : $path";
        return $result;
    }
    
    // Essayer de créer le dossier
    if (@mkdir($path, $permissions, $recursive)) {
        $result['success'] = true;
        $result['message'] = "Dossier créé avec succès : $path";
        
        // Essayer d'appliquer les permissions explicitement
        @chmod($path, $permissions);
        
        return $result;
    }
    
    // Si la création a échoué, essayer de diagnostiquer le problème
    $parentDir = dirname($path);
    
    if (!is_dir($parentDir)) {
        $result['message'] = "Le dossier parent n'existe pas : $parentDir";
    } elseif (!is_writable($parentDir)) {
        $result['message'] = "Pas de permissions d'écriture sur le dossier parent : $parentDir";
    } else {
        $result['message'] = "Impossible de créer le dossier : $path (erreur inconnue)";
    }
    
    return $result;
}

/**
 * Vérifie et crée tous les dossiers uploads nécessaires
 * @param string $baseUploadPath Chemin de base des uploads
 * @return array Résultats de création pour chaque dossier
 */
function ensureUploadDirectories($baseUploadPath) {
    $directories = [
        'backups',
        'documents', 
        'temp',
        'interventions',
        'autoit',
        'autoit/logiciels',
        'autoit/nettoyage'
    ];
    
    $results = [];
    
    foreach ($directories as $dir) {
        $fullPath = rtrim($baseUploadPath, '/') . '/' . $dir;
        $results[$dir] = createDirectoryWithPermissions($fullPath);
    }
    
    return $results;
}

/**
 * Vérifie si un dossier est accessible en écriture
 * @param string $path Chemin à vérifier
 * @return array ['writable' => bool, 'exists' => bool, 'message' => string]
 */
function checkDirectoryPermissions($path) {
    $result = [
        'writable' => false,
        'exists' => false,
        'message' => ''
    ];
    
    if (!file_exists($path)) {
        $result['message'] = "Le dossier n'existe pas : $path";
        return $result;
    }
    
    $result['exists'] = true;
    
    if (!is_dir($path)) {
        $result['message'] = "Le chemin n'est pas un dossier : $path";
        return $result;
    }
    
    if (!is_writable($path)) {
        $result['message'] = "Pas de permissions d'écriture sur : $path";
        return $result;
    }
    
    $result['writable'] = true;
    $result['message'] = "Dossier accessible en écriture : $path";
    
    return $result;
}

/**
 * Génère un message d'erreur utilisateur-friendly pour les problèmes de permissions
 * @param string $operation Opération tentée (ex: "sauvegarde", "upload photo")
 * @param string $path Chemin problématique
 * @return string Message d'erreur formaté
 */
function getPermissionErrorMessage($operation, $path) {
    $message = "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
    $message .= "<strong>⚠️ Problème de permissions</strong><br>";
    $message .= "Impossible d'effectuer l'opération : <strong>$operation</strong><br>";
    $message .= "Dossier concerné : <code>$path</code><br><br>";
    $message .= "<strong>Solutions :</strong><br>";
    $message .= "1. Exécuter le script de correction : <code>./fix_permissions.sh</code><br>";
    $message .= "2. Ou manuellement : <code>chmod 775 " . dirname($path) . " && chown www-data:www-data " . dirname($path) . "</code><br>";
    $message .= "3. Pour Docker : <code>docker-compose build --no-cache && docker-compose up -d</code>";
    $message .= "</div>";
    
    return $message;
}

/**
 * Crée un fichier de test pour vérifier les permissions d'écriture
 * @param string $directory Dossier à tester
 * @return bool True si l'écriture est possible
 */
function testWritePermissions($directory) {
    $testFile = rtrim($directory, '/') . '/.write_test_' . uniqid();
    
    if (@file_put_contents($testFile, 'test')) {
        @unlink($testFile);
        return true;
    }
    
    return false;
}