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
    echo json_encode(['ready' => !file_exists($lockFile)]);
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
        }

        body.dark {
            --bg-color: #0f1416;
            --text-color: #dfe6e9;
            --card-bg: #1e272e;
            --shadow: 0 10px 30px rgba(0,0,0,0.3);
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
            padding: 3rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 500px;
            width: 90%;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--accent-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(9, 132, 227, 0.1);
            border-top: 5px solid var(--accent-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        h1 { font-size: 1.5rem; margin-bottom: 1rem; }
        p { color: #636e72; line-height: 1.6; margin-bottom: 1.5rem; }
        body.dark p { color: #a4b0be; }

        .status-badge {
            background: rgba(9, 132, 227, 0.1);
            color: var(--accent-color);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
        }

        /* Dark mode switch (optional visual only) */
        .theme-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            font-size: 1.2rem;
            cursor: pointer;
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
        <p>TechSuivi prépare votre environnement... Nous configurons la base de données et l'installeur pour votre NAS.</p>
        
        <div class="status-badge" id="status">
            Patientez quelques instants...
        </div>
    </div>

    <script>
        // Vérification automatique de l'état
        function checkStatus() {
            fetch('installing.php?check=1')
                .then(r => r.json())
                .then(data => {
                    if (data.ready) {
                        document.body.style.opacity = '0';
                        document.body.style.transition = 'opacity 0.5s ease';
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 500);
                    }
                })
                .catch(e => console.error("Erreur de vérification:", e));
        }

        // Vérifier toutes le 3 secondes
        setInterval(checkStatus, 3000);
        
        // Appliquer le thème sombre si besoin
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && !document.cookie.includes('theme=')) {
            document.body.classList.add('dark');
        }
    </script>
</body>
</html>
