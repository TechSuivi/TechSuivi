<?php
session_start();

// Empêcher l'affichage des erreurs PHP dans le retour (cela casse le JSON)
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 0);
ob_start(); // Capture tout output parasite

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    ob_end_clean();
    die(json_encode(['error' => 'Non autorisé']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$rustdeskDir = '/var/www/rustdesk_data';
$persistenceDir = __DIR__ . '/../uploads/keys';

// Créer les dossiers si manquants (Silent mode)
if (!is_dir($persistenceDir)) {
    @mkdir($persistenceDir, 0777, true);
    @chown($persistenceDir, 'www-data');
}

if ($action === 'download_keys') {
    ob_end_clean(); // On vide le buffer pour envoyer le zip propre
    $zipFile = sys_get_temp_dir() . '/rustdesk_keys_' . date('Y-m-d_His') . '.zip';
    $zip = new ZipArchive();

    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        die(json_encode(['error' => 'Impossible de créer le zip']));
    }

    $filesAdded = 0;
    // On essaie de prendre les clés "live" du volume, sinon du dossier uploads
    $sources = [
        'id_ed25519' => [
            $rustdeskDir . '/id_ed25519',
            $persistenceDir . '/id_ed25519'
        ],
        'id_ed25519.pub' => [
            $rustdeskDir . '/id_ed25519.pub',
            $persistenceDir . '/id_ed25519.pub'
        ]
    ];

    foreach ($sources as $name => $paths) {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $zip->addFile($path, $name);
                $filesAdded++;
                break; // On a trouvé ce fichier, on passe au suivant
            }
        }
    }

    $zip->close();

    if ($filesAdded > 0) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="rustdesk_keys_backup.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    } else {
        die("Aucune clé trouvée sur le serveur (Chemin: $rustdeskDir).");
    }

} elseif ($action === 'upload_keys') {
    header('Content-Type: application/json');

    if (!isset($_FILES['key_files'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
        exit;
    }

    $count = 0;
    $errors = [];

    // Boucle sur les fichiers envoyés (supporte l'upload multiple)
    foreach ($_FILES['key_files']['name'] as $i => $name) {
        $tmpName = $_FILES['key_files']['tmp_name'][$i];
        $error = $_FILES['key_files']['error'][$i];

        if ($error === UPLOAD_ERR_OK) {
            // Validation du nom (sécurité stricte)
            if ($name !== 'id_ed25519' && $name !== 'id_ed25519.pub') {
                $errors[] = "$name ignoré (Nom invalide)";
                continue;
            }

            // Copie dans le dossier de persistance (pour init_installer.php au prochain boot)
            $destPersistence = $persistenceDir . '/' . $name;
            if (@move_uploaded_file($tmpName, $destPersistence)) {
                @chmod($destPersistence, 0644); // .pub est world readable, private key sera corrigée après
                
                // Copie immédiate dans le volume live (si existe)
                // On utilise copy() car move_uploaded_file a déjà déplacé le fichier
                if (is_dir($rustdeskDir) && is_writable($rustdeskDir)) {
                    $destLive = $rustdeskDir . '/' . $name;
                    @copy($destPersistence, $destLive);
                    
                    // Fix permissions
                    if ($name === 'id_ed25519') {
                        @chmod($destLive, 0600); // Privée
                        @chmod($destPersistence, 0600);
                    } else {
                        @chmod($destLive, 0644); // Publique
                    }
                }
                $count++;
            } else {
                $errors[] = "Échec copie de $name (Verif permissions uploads/keys)";
            }
        } else {
            $errors[] = "Erreur upload code $error";
        }
    }

    ob_end_clean(); // Clean buffer before JSON output
    if ($count > 0) {
        echo json_encode([
            'success' => true, 
            'message' => "$count fichier(s) importé(s) avec succès. Redémarrez Rustdesk pour appliquer.",
            'details' => $errors
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucun fichier valide importé.', 'details' => $errors]);
    }
    exit;
}

ob_end_clean();
echo json_encode(['error' => 'Action inconnue']);
