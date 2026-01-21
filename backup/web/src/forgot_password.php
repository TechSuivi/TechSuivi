<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechSuivi - Mot de passe oublié</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: var(--bg-color-dark, #121212);
            color: var(--text-color-dark, #eee);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .reset-wrapper {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .reset-card {
            background: var(--card-bg, #1e1e1e);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            border: 1px solid var(--border-color, #333);
            padding: 30px;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .reset-header h1 {
            margin: 0;
            font-size: 1.5em;
            color: var(--text-color, #eee);
        }

        .reset-header p {
            margin: 10px 0 0;
            color: var(--text-color-muted, #888);
            font-size: 0.9em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color, #ccc);
            font-size: 0.9em;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            background: var(--input-bg, #2b2b2b);
            border: 2px solid var(--border-color, #444);
            border-radius: 8px;
            color: var(--text-color, #eee);
            font-size: 1em;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: var(--accent-color, #2A4C9C);
            outline: none;
            box-shadow: 0 0 0 3px rgba(42, 76, 156, 0.2);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--accent-color, #2A4C9C);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-color-muted, #888);
            text-decoration: none;
            font-size: 0.9em;
            transition: color 0.2s;
        }
        
        .btn-back:hover {
            color: var(--text-color, #eee);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
            display: none;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #51cf66;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
    </style>
</head>
<body class="dark">
    <div class="reset-wrapper">
        <div class="reset-card">
            <div class="reset-header">
                <h1>Mot de passe oublié</h1>
                <p>Entrez votre email pour recevoir un lien de réinitialisation.</p>
            </div>

            <div id="alertError" class="alert alert-error"></div>
            <div id="alertSuccess" class="alert alert-success"></div>

            <form id="resetForm" onsubmit="submitReset(event)">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="nom@exemple.com">
                </div>

                <button type="submit" class="btn-submit">Envoyer le lien</button>
                <a href="login.php" class="btn-back">Retour à la connexion</a>
            </form>
        </div>
    </div>

    <script>
        async function submitReset(event) {
            event.preventDefault();
            const form = event.target;
            const email = form.email.value;
            const btn = form.querySelector('button');
            const alertError = document.getElementById('alertError');
            const alertSuccess = document.getElementById('alertSuccess');

            btn.disabled = true;
            btn.textContent = 'Envoi en cours...';
            alertError.style.display = 'none';
            alertSuccess.style.display = 'none';

            try {
                const response = await fetch('actions/send_reset_link.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'email=' + encodeURIComponent(email)
                });

                const data = await response.json();

                if (data.success) {
                    alertSuccess.textContent = data.message;
                    alertSuccess.style.display = 'block';
                    form.reset();
                } else {
                    alertError.textContent = data.message || 'Une erreur est survenue.';
                    alertError.style.display = 'block';
                }
            } catch (error) {
                alertError.textContent = 'Erreur de communication avec le serveur.';
                alertError.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Envoyer le lien';
            }
        }
    </script>
</body>
</html>
