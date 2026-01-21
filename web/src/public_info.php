<?php
// Page d'informations publiques (Notes, Téléchargements, Liens)
require_once __DIR__ . '/config/database.php';

// Initialisation de la connexion PDO
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les notes publiques
$stmt = $pdo->query("SELECT * FROM notes_globales WHERE show_on_login = 1 ORDER BY date_note DESC");
$publicNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les téléchargements
$stmt = $pdo->query("SELECT * FROM download WHERE show_on_login = 1 ORDER BY NOM ASC");
$publicDownloads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les liens
$stmt = $pdo->query("SELECT * FROM liens WHERE show_on_login = 1 ORDER BY NOM ASC");
$publicLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasInfo = !empty($publicDownloads) || !empty($publicLinks) || !empty($publicNotes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechSuivi - Informations</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        body {
            background-color: var(--bg-color-dark, #121212);
            color: var(--text-color-dark, #eee);
            min-height: 100vh;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 20px;
            box-sizing: border-box;
        }

        .info-wrapper {
            width: 100%;
            max-width: 1400px; /* Plus large pour afficher tout le contenu */
            margin: 0 auto;
            background: var(--card-bg, #1e1e1e);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid var(--border-color, #333);
            padding: 40px;
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color, #333);
        }

        .header-title h1 {
            margin: 0;
            font-size: 1.8em;
            background: -webkit-linear-gradient(45deg, #4dabf7, #2A4C9C);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-back {
            padding: 10px 20px;
            background: rgba(255,255,255,0.05);
            color: var(--text-color, #eee);
            text-decoration: none;
            border-radius: 8px;
            border: 1px solid var(--border-color, #444);
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(-2px);
        }

        /* Colonne Info (Adaptée du login) */
        .info-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            align-content: start;
        }
        
        @media (min-width: 1200px) {
            .info-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        .info-section {
            display: flex;
            flex-direction: column;
        }

        .info-header {
            font-size: 1.1em;
            font-weight: 600;
            color: var(--text-color, #eee);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color, #333);
        }

        .link-grid {
            display: grid;
            gap: 10px;
            margin-bottom: 30px;
        }

        .link-item {
            display: block;
            padding: 15px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            text-decoration: none;
            color: var(--accent-color-light, #4dabf7);
            font-size: 0.9em;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.05);
            text-align: left;
        }

        .link-item:hover {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .link-desc {
            display: block;
            color: var(--text-color-muted, #999);
            font-size: 0.85em;
            margin-top: 5px;
            text-align: left;
            width: 100%;
        }

        /* Modal Styles */
        .note-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }
        .note-modal-content {
            background-color: var(--card-bg, #1e1e1e);
            margin: auto;
            padding: 30px;
            border: 1px solid var(--border-color, #333);
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 15px 50px rgba(0,0,0,0.5);
        }
        .note-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color, #333);
        }
        .note-modal-title {
            margin: 0;
            font-size: 1.4em;
            color: var(--text-color, #fff);
        }
        .note-modal-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        .note-modal-close:hover {
            color: #fff;
        }
        .note-modal-body {
            color: var(--text-color, #eee);
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        /* Attachment Button in Modal */
        .modal-attachment-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 15px;
            background: rgba(77, 171, 247, 0.1);
            color: #4dabf7;
            text-decoration: none;
            border-radius: 8px;
            border: 1px solid rgba(77, 171, 247, 0.3);
            transition: all 0.2s;
            margin-left: 10px;
            font-weight: 500;
        }
        
        .modal-attachment-btn:hover {
            background: rgba(77, 171, 247, 0.2);
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="dark">
    <div class="info-wrapper">
        <div class="header-bar">
            <div class="header-title">
                <h1>TechSuivi - Informations Publiques</h1>
            </div>
            <a href="login.php" class="btn-back">← Retour connexion</a>
        </div>

        <div class="info-container">
            <?php if (!empty($publicNotes)): ?>
                <div class="info-section">
                    <div class="info-header">Notes & Informations</div>
                    <div class="link-grid">
                        <?php foreach ($publicNotes as $index => $note): ?>
                            <div class="link-item" onclick="showNote(<?= $index ?>)" style="cursor: pointer; display: flex; flex-direction: column; gap: 5px;">
                                <div style="font-weight: 600; color: var(--text-color); display: flex; justify-content: space-between; align-items: start;">
                                    <span>📓 <?= htmlspecialchars($note['titre']) ?></span>
                                    <span style="font-size: 0.75em; opacity: 0.5; font-weight: normal;"><?= date('d/m/Y', strtotime($note['date_note'])) ?></span>
                                </div>
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.85em; color: var(--text-color-muted, #999); opacity: 0.9;">
                                    <?= htmlspecialchars(strip_tags($note['contenu'])) ?>
                                </div>
                                <?php if ($note['fichier_path']): ?>
                                    <div class="text-xs" style="color: var(--accent-color-light); opacity: 0.8; font-size: 0.8em;">📎 Pièce jointe incluse</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($publicDownloads)): ?>
                <div class="info-section">
                    <div class="info-header">Téléchargements</div>
                    <div class="link-grid">
                        <?php foreach ($publicDownloads as $download): ?>
                            <a href="<?= htmlspecialchars($download['URL']) ?>" target="_blank" class="link-item">
                                <span>📥 <?= htmlspecialchars($download['NOM']) ?></span>
                                <?php if (!empty($download['DESCRIPTION'])): ?>
                                    <span class="link-desc"><?= htmlspecialchars($download['DESCRIPTION']) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($publicLinks)): ?>
                <div class="info-section">
                    <div class="info-header">Liens Utiles</div>
                    <div class="link-grid">
                        <?php foreach ($publicLinks as $link): ?>
                            <a href="<?= htmlspecialchars($link['URL']) ?>" target="_blank" class="link-item">
                                <span>🔗 <?= htmlspecialchars($link['NOM']) ?></span>
                                <?php if (!empty($link['DESCRIPTION'])): ?>
                                    <span class="link-desc"><?= htmlspecialchars($link['DESCRIPTION']) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Note Modal -->
    <div id="publicNoteModal" class="note-modal" onclick="closeNoteModal(event)">
        <div class="note-modal-content" onclick="event.stopPropagation()">
            <div class="note-modal-header">
                <h3 class="note-modal-title" id="noteModalTitle">📓 Note</h3>
                <span class="note-modal-close" onclick="closeNoteModal()">&times;</span>
            </div>
            <div class="note-modal-body" id="noteModalBody"></div>
            <div id="noteModalFooter" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color, #333); font-size: 0.85em; opacity: 0.7; text-align: right;"></div>
        </div>
    </div>

    <script>
        const publicNotes = <?= json_encode($publicNotes) ?>;

        function showNote(index) {
            const note = publicNotes[index];
            document.getElementById('noteModalTitle').textContent = '📓 ' + note.titre;
            document.getElementById('noteModalBody').textContent = note.contenu;
            
            let footerHtml = '<div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">';
            footerHtml += '<span>Posté le ' + new Date(note.date_note).toLocaleDateString('fr-FR') + '</span>';
            
            if (note.fichier_path) {
                // Bouton plus visible pour la pièce jointe
                footerHtml += '<a href="' + note.fichier_path + '" target="_blank" class="modal-attachment-btn">📎 Télécharger la pièce jointe</a>';
            }
            footerHtml += '</div>';
            
            document.getElementById('noteModalFooter').innerHTML = footerHtml;
            
            document.getElementById('publicNoteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeNoteModal(event) {
            document.getElementById('publicNoteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    </script>
</body>
</html>
