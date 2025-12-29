#!/bin/bash

# Script d'installation de PHPMailer pour TechSuivi
# Ce script installe PHPMailer via Composer pour améliorer l'envoi d'emails

echo "🚀 Installation de PHPMailer pour TechSuivi"
echo "=========================================="

# Vérifier si nous sommes dans le bon répertoire
if [ ! -f "web/src/index.php" ]; then
    echo "❌ Erreur: Ce script doit être exécuté depuis la racine du projet TechSuivi"
    echo "   Répertoire actuel: $(pwd)"
    echo "   Assurez-vous d'être dans le répertoire contenant le dossier 'web/'"
    exit 1
fi

# Aller dans le répertoire web/src
cd web/src

echo "📁 Répertoire de travail: $(pwd)"

# Vérifier si Composer est installé
if ! command -v composer &> /dev/null; then
    echo "❌ Composer n'est pas installé sur ce système"
    echo ""
    echo "🔧 Pour installer Composer:"
    echo "   Ubuntu/Debian: sudo apt install composer"
    echo "   CentOS/RHEL: sudo yum install composer"
    echo "   Ou téléchargez depuis: https://getcomposer.org/download/"
    echo ""
    echo "📋 Installation manuelle alternative:"
    echo "   1. Téléchargez PHPMailer: https://github.com/PHPMailer/PHPMailer/releases"
    echo "   2. Extrayez dans web/src/vendor/phpmailer/"
    echo "   3. La configuration automatique se chargera du reste"
    exit 1
fi

echo "✅ Composer trouvé: $(composer --version)"

# Initialiser composer.json s'il n'existe pas
if [ ! -f "composer.json" ]; then
    echo "📝 Création du fichier composer.json..."
    cat > composer.json << 'EOF'
{
    "name": "techsuivi/web",
    "description": "TechSuivi Web Application",
    "type": "project",
    "require": {
        "php": ">=7.4"
    },
    "config": {
        "vendor-dir": "vendor"
    }
}
EOF
    echo "✅ Fichier composer.json créé"
fi

# Installer PHPMailer
echo "📦 Installation de PHPMailer..."
if composer require phpmailer/phpmailer; then
    echo "✅ PHPMailer installé avec succès!"
else
    echo "❌ Erreur lors de l'installation de PHPMailer"
    echo "   Vérifiez votre connexion internet et les permissions"
    exit 1
fi

# Vérifier l'installation
if [ -f "vendor/phpmailer/phpmailer/src/PHPMailer.php" ]; then
    echo "✅ Vérification: PHPMailer correctement installé"
    echo "   Fichier trouvé: vendor/phpmailer/phpmailer/src/PHPMailer.php"
else
    echo "⚠️  Attention: Fichiers PHPMailer non trouvés à l'emplacement attendu"
fi

# Créer un fichier d'autoload personnalisé si nécessaire
if [ ! -f "autoload.php" ]; then
    echo "📝 Création du fichier d'autoload personnalisé..."
    cat > autoload.php << 'EOF'
<?php
/**
 * Autoload personnalisé pour TechSuivi
 * Charge automatiquement les dépendances Composer si disponibles
 */

// Charger l'autoloader Composer si disponible
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Fonction pour vérifier si PHPMailer est disponible
function isPHPMailerAvailable() {
    return class_exists('PHPMailer\PHPMailer\PHPMailer');
}
EOF
    echo "✅ Fichier autoload.php créé"
fi

echo ""
echo "🎉 Installation terminée!"
echo "=========================================="
echo "✅ PHPMailer est maintenant installé et prêt à être utilisé"
echo "✅ TechSuivi utilisera automatiquement PHPMailer pour l'envoi d'emails"
echo "✅ Meilleure fiabilité et support SMTP complet disponible"
echo ""
echo "🔧 Prochaines étapes:"
echo "   1. Allez sur votre page de configuration mail TechSuivi"
echo "   2. Vous devriez voir 'PHPMailer' comme méthode d'envoi"
echo "   3. Configurez vos paramètres SMTP"
echo "   4. Testez l'envoi d'emails"
echo ""
echo "📍 Page de configuration: http://votre-serveur/index.php?page=mail_config"