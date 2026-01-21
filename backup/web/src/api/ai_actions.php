<?php
// web/src/api/ai_actions.php

// Initialisation
define('TECHSUIVI_INCLUDED', true);
// Désactiver l'affichage des erreurs HTML pour ne pas casser le JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

// Force JSON response
header('Content-Type: application/json');

// Vérification de la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Fonction utilitaire de réponse
function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

try {
    // Connexion DB
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception("Erreur de connexion base de données");
    }

    // Vérifier/Créer la table ai_rules
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Table Conversations
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT 'Nouvelle conversation',
        rule_content TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Migration "douce" : Tenter d'ajouter la colonne si elle manque (pour les DB existantes)
    try {
        $pdo->exec("ALTER TABLE ai_conversations ADD COLUMN rule_content TEXT NULL AFTER title");
    } catch (Throwable $e) {
        // Ignorer si la colonne existe déjà
    }

    // Table Messages
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        role ENUM('user', 'model') NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Action : Gestion des règles
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'list_rules') {
            $stmt = $pdo->query("SELECT * FROM ai_rules ORDER BY name ASC");
            sendResponse(true, "Liste des règles", ['rules' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        
        if ($action === 'add_rule') {
            $name = trim($_POST['name'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if (empty($name) || empty($content)) throw new Exception("Nom et contenu requis");
            
            $stmt = $pdo->prepare("INSERT INTO ai_rules (name, content) VALUES (?, ?)");
            $stmt->execute([$name, $content]);
            sendResponse(true, "Règle ajoutée");
        }

        if ($action === 'edit_rule') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if ($id <= 0 || empty($name) || empty($content)) throw new Exception("ID, nom et contenu requis");
            
            $stmt = $pdo->prepare("UPDATE ai_rules SET name = ?, content = ? WHERE id = ?");
            $stmt->execute([$name, $content, $id]);
            sendResponse(true, "Règle modifiée");
        }

        if ($action === 'delete_rule') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM ai_rules WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(true, "Règle supprimée");
        }

        // --- GESTION CONVERSATIONS ---

        if ($action === 'list_conversations') {
            $stmt = $pdo->query("SELECT * FROM ai_conversations ORDER BY updated_at DESC");
            sendResponse(true, "Liste conversations", ['conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($action === 'load_conversation') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID conversation invalide");
            
            // Fetch conversation info (rule)
            $stmtConv = $pdo->prepare("SELECT rule_content FROM ai_conversations WHERE id = ?");
            $stmtConv->execute([$id]);
            $convData = $stmtConv->fetch(PDO::FETCH_ASSOC);

            // Récupérer les messages
            $stmt = $pdo->prepare("SELECT role, content FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $stmt->execute([$id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mapper 'content' vers 'text' pour le front
            $mapped = array_map(function($m) {
                return ['role' => $m['role'], 'text' => $m['content']];
            }, $messages);

            sendResponse(true, "Conversation chargée", [
                'messages' => $mapped,
                'rule' => $convData['rule_content'] ?? ''
            ]);
        }

        if ($action === 'delete_conversation') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID conversation invalide");
            
            $stmt = $pdo->prepare("DELETE FROM ai_conversations WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(true, "Conversation supprimée");
        }
        
        if ($action === 'new_conversation') {
            $stmt = $pdo->prepare("INSERT INTO ai_conversations (title) VALUES ('Nouvelle conversation')");
            $stmt->execute();
            sendResponse(true, "Nouvelle conversation créée", ['id' => $pdo->lastInsertId()]);
        }
    }

    // Récupérer la configuration Gemini
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM configuration WHERE config_key IN ('gemini_api_key', 'gemini_model')");
    $stmt->execute();
    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config[$row['config_key']] = $row['config_value'];
    }

    $apiKey = $config['gemini_api_key'] ?? '';
    $modelName = $config['gemini_model'] ?? 'gemini-1.5-flash';

    if (empty($apiKey)) {
        sendResponse(false, "Clé API Gemini non configurée. Veuillez la configurer dans Paramètres > Configuration.");
    }

    // Récupérer le prompt
    $input = $_POST['prompt'] ?? '';
    if (empty($input)) {
        sendResponse(false, "Le prompt est vide.");
    }
    
    // Gestion Conversation ID
    $conversationId = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
    
    // Rule System
    $ruleContent = $_POST['rule_content'] ?? '';
    
    // Si pas d'ID, on crée une nouvelle conversation
    if ($conversationId <= 0) {
        $stmt = $pdo->prepare("INSERT INTO ai_conversations (title, rule_content) VALUES (?, ?)");
        // Titre temporaire
        $shortTitle = mb_substr($input, 0, 30) . '...';
        $stmt->execute([$shortTitle, $ruleContent]);
        $conversationId = $pdo->lastInsertId();
    } else {
        // Conversation existante : on récupère la règle sauvegardée pour l'utiliser
        $stmt = $pdo->prepare("SELECT rule_content FROM ai_conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        $existingRule = $stmt->fetchColumn();
        if ($existingRule) {
            $ruleContent = $existingRule; // Override POST rule with Saved rule
        }
        // Optionnel : si $ruleContent est vide en DB mais présent en POST, on pourrait update la DB.
        // Pour l'instant on reste simple : la règle à la création fait foi.
    }
    
    // Sauvegarder message USER
    $stmt = $pdo->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?, 'user', ?)");
    $stmt->execute([$conversationId, $input]);

    // Récupérer l'historique complet pour le contexte Gemini
    // On relit la DB pour être sûr d'avoir l'ordre exact + le message qu'on vient d'insérer
    $stmt = $pdo->prepare("SELECT role, content FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$conversationId]);
    $dbMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Construction du contenu de la requête Gemini
    $contents = [];
    
    foreach ($dbMessages as $index => $msg) {
        $text = $msg['content'];
        
        // Injecter la règle système au TOUT DÉBUT de l'historique (premier message user)
        if ($index === 0 && !empty($ruleContent)) { // && $msg['role'] === 'user' (normalement oui)
            $text = "Instructions système : " . $ruleContent . "\n\nDemande utilisateur : " . $text;
        }

        $contents[] = [
            "role" => $msg['role'],
            "parts" => [["text" => $text]]
        ];
    }
    
    // S'assurer que le dernier message est bien 'user' (Gemini requirement)
    // Normalement oui car on vient de l'insérer
    
    // URL API Gemini
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    $data = ["contents" => $contents];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 30, // Timeout plus long pour la génération de texte
            'ignore_errors' => true 
        ]
    ];
    
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        $error = error_get_last();
        throw new Exception("Erreur de connexion API : " . $error['message']);
    }
    
    $decoded = json_decode($result, true);
    
    // Vérification des erreurs API
    if (isset($decoded['error'])) {
        throw new Exception("Erreur Gemini : " . ($decoded['error']['message'] ?? 'Inconnue'));
    }
    
    // Extraction du texte généré
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $generatedText = $decoded['candidates'][0]['content']['parts'][0]['text'];
        
        // Sauvegarder message AI
        $stmt = $pdo->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?, 'model', ?)");
        $stmt->execute([$conversationId, $generatedText]);
        
        // Update Timestamp conversation
        $stmt = $pdo->prepare("UPDATE ai_conversations SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversationId]);
        
        sendResponse(true, "Texte généré avec succès", [
            'text' => $generatedText,
            'conversation_id' => $conversationId
        ]);

    } else {
        // ... (Gestion refus inchangée)
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? 'UNKNOWN';
        if ($finishReason === 'SAFETY') {
            throw new Exception("Le modèle a refusé de répondre pour des raisons de sécurité.");
        } else if ($finishReason === 'RECITATION') {
            throw new Exception("Le modèle a détecté une récitation de contenu protégé.");
        } else if ($finishReason === 'OTHER') {
             throw new Exception("Le modèle a refusé de répondre. (Raison: OTHER)");
        } else {
             // Log pour débug
             error_log("Gemini API Response Error: " . print_r($decoded, true));
             throw new Exception("Format de réponse inattendu (FinishReason: $finishReason).");
        }
    }

} catch (Throwable $e) {
    $data = [];
    if (isset($conversationId) && $conversationId > 0) {
        $data['conversation_id'] = $conversationId;
    }
    sendResponse(false, "Erreur Serveur : " . $e->getMessage(), $data);
}
