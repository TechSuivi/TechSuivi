#!/bin/bash

# Script d'initialisation pour PHPMailer dans TechSuivi
echo "🚀 Initialisation de PHPMailer pour TechSuivi..."

# Vérifier si PHPMailer est déjà installé
if [ ! -d "/var/www/html/vendor/phpmailer" ]; then
    echo "📦 Installation de PHPMailer..."
    
    # Créer le fichier composer.json s'il n'existe pas
    if [ ! -f "/var/www/html/composer.json" ]; then
        cat > /var/www/html/composer.json << 'EOF'
{
    "name": "techsuivi/web",
    "description": "TechSuivi Web Application",
    "type": "project",
    "require": {
        "php": ">=7.4",
        "phpmailer/phpmailer": "^6.8"
    },
    "config": {
        "vendor-dir": "vendor"
    }
}
EOF
    fi
    
    # Installer PHPMailer
    cd /var/www/html
    composer install --no-dev --optimize-autoloader --quiet
    
    # Créer le fichier d'autoload personnalisé
    cat > /var/www/html/autoload.php << 'EOF'
<?php
/**
 * Autoload pour TechSuivi avec PHPMailer
 */
if (file_exists(__DIR__ . "/vendor/autoload.php")) {
    require_once __DIR__ . "/vendor/autoload.php";
}

function isPHPMailerAvailable() {
    return class_exists("PHPMailer\\PHPMailer\\PHPMailer");
}
EOF
    
    echo "✅ PHPMailer installé avec succès !"
else
    echo "✅ PHPMailer déjà installé."
fi

# Ajuster les permissions
chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true
chown www-data:www-data /var/www/html/composer.* 2>/dev/null || true
chown www-data:www-data /var/www/html/autoload.php 2>/dev/null || true

echo "🎯 Initialisation terminée !"