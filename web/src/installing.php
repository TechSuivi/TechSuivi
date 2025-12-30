<?php
/**
 * Page d'attente d'installation TechSuivi
 */
$lockFile = __DIR__ . '/install_in_progress.lock';

// Si le verrou n'existe plus, on redirige vers l'accueil
if (!file_exists($lockFile)) {
    header('Location: index.php');
    exit();
}

// Action AJAX pour vérifier l'état
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    $logContent = file_exists(__DIR__ . '/install.log') ? file_get_contents(__DIR__ . '/install.log') : 'Initialisation en cours...';
    echo json_encode([
        'ready' => !file_exists($lockFile),
        'logs' => $logContent
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation en cours - TechSuivi</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
    <style>
        :root {
            --bg-color: #f4f7f6;
            --text-color: #2d3436;
            --accent-color: #0984e3;
            --card-bg: #ffffff;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --log-bg: #f1f2f6;
            --log-text: #2f3542;
        }

        body.dark {
            --bg-color: #0f1416;
            --text-color: #dfe6e9;
            --card-bg: #1e272e;
            --shadow: 0 10px 30px rgba(0,0,0,0.3);
            --log-bg: #2f3542;
            --log-text: #dfe6e9;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            transition: all 0.3s ease;
        }

        .container {
            text-align: center;
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 600px;
            width: 95%;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--accent-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(9, 132, 227, 0.1);
            border-top: 5px solid var(--accent-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 1.5rem auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        h1 { font-size: 1.3rem; margin-bottom: 0.5rem; }
        p { color: #636e72; line-height: 1.4; margin-bottom: 1rem; font-size: 0.95rem; }
        body.dark p { color: #a4b0be; }

        .log-area {
            background: var(--log-bg);
            color: var(--log-text);
            padding: 1rem;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            text-align: left;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 1rem;
            border: 1px solid rgba(0,0,0,0.05);
            white-space: pre-wrap;
        }

        .status-badge {
            background: rgba(9, 132, 227, 0.1);
            color: var(--accent-color);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 1rem;
        }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark' : ''; ?>">
    <div class="container">
        <div class="logo">
            🐳 TechSuivi
        </div>
        
        <div class="spinner"></div>
        
        <h1>Installation en cours</h1>
        <p>TechSuivi prépare votre environnement... Votre NAS finalise la configuration.</p>
        
        <div class="log-area" id="log-content">Initialisation...</div>

        <div class="status-badge" id="status">
            Patientez quelques instants...
        </div>
    </div>

    <script>
        const logContent = document.getElementById('log-content');
        
        function checkStatus() {
            fetch('installing.php?check=1')
                .then(r => r.json())
                .then(data => {
                    if (data.logs) {
                        logContent.innerText = data.logs;
                        logContent.scrollTop = logContent.scrollHeight;
                    }
                    
                    if (data.ready) {
                        document.getElementById('status').innerText = "✓ Installation terminée !";
                        document.getElementById('status').style.backgroundColor = "#27ae60";
                        document.getElementById('status').style.color = "#fff";
                        
                        setTimeout(() => {
                            document.body.style.opacity = '0';
                            document.body.style.transition = 'opacity 0.5s ease';
                            setTimeout(() => {
                                window.location.href = 'index.php';
                            }, 500);
                        }, 1000);
                    }
                })
                .catch(e => console.error("Erreur check:", e));
        }

        setInterval(checkStatus, 3000);
        checkStatus(); // Premier check immédiat
        
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && !document.cookie.includes('theme=')) {
            document.body.classList.add('dark');
        }
    </script>
</body>
</html>
