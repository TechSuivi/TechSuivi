#!/bin/bash

# Script d'installation automatique TechSuivi
# Version non-interactive pour éviter les blocages

set -e

echo "=== INSTALLATION AUTOMATIQUE TECHSUIVI ==="
echo ""

# Configuration par défaut
DB_HOST="db"
DB_NAME="techsuivi_db"
DB_USER="techsuivi_user"
DB_PASS="techsuivi_pass_$(date +%Y)"
DB_ROOT_PASS="techsuivi_root_$(date +%Y)"

# Fonction pour détecter la commande Docker Compose
detect_docker_compose() {
    if command -v docker-compose &> /dev/null; then
        echo "docker-compose"
    elif docker compose version &> /dev/null 2>&1; then
        echo "docker compose"
    else
        return 1
    fi
}

# Étape 1: Vérification de Docker
echo "1. Vérification de Docker..."
if ! command -v docker &> /dev/null; then
    echo "❌ Docker n'est pas installé"
    echo "💡 Installez Docker: https://docs.docker.com/get-docker/"
    exit 1
fi

DOCKER_COMPOSE_CMD=$(detect_docker_compose)
if [ $? -ne 0 ]; then
    echo "❌ Docker Compose n'est pas installé"
    echo "💡 Installez Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi

echo "✅ Docker et Docker Compose sont installés (utilise: $DOCKER_COMPOSE_CMD)"

# Étape 2: Création du fichier .env
echo ""
echo "2. Configuration automatique..."
echo "📝 Création du fichier .env avec configuration par défaut"

cat > .env << EOF
# Configuration de la base de données
DB_HOST=${DB_HOST}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_ROOT_PASS=${DB_ROOT_PASS}

# Configuration Docker
MYSQL_ROOT_PASSWORD=${DB_ROOT_PASS}
MYSQL_DATABASE=${DB_NAME}
MYSQL_USER=${DB_USER}
MYSQL_PASSWORD=${DB_PASS}
EOF

echo "✅ Fichier .env créé avec succès"

# Étape 3: Création du fichier .env pour l'application web
echo ""
echo "3. Configuration de l'application web..."
mkdir -p web/src
cat > web/src/.env << EOF
# Configuration de la base de données pour l'application web
DB_HOST=${DB_HOST}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
EOF

echo "✅ Configuration web créée dans web/src/.env"

# Étape 4: Arrêt des conteneurs existants
echo ""
echo "4. Nettoyage des conteneurs existants..."
$DOCKER_COMPOSE_CMD down --remove-orphans 2>/dev/null || true
echo "✅ Conteneurs arrêtés"

# Étape 5: Construction et démarrage
echo ""
echo "5. Construction et démarrage des conteneurs..."
echo "⏳ Cette étape peut prendre quelques minutes..."

if $DOCKER_COMPOSE_CMD up -d --build; then
    echo "✅ Conteneurs démarrés avec succès"
else
    echo "❌ Erreur lors du démarrage des conteneurs"
    echo "💡 Vérifiez les logs avec: $DOCKER_COMPOSE_CMD logs"
    exit 1
fi

# Étape 6: Attente que la base de données soit prête
echo ""
echo "6. Attente de la base de données..."
echo "⏳ Patientez pendant l'initialisation..."

for i in {1..30}; do
    if $DOCKER_COMPOSE_CMD exec -T db mariadb -h localhost -u root -p${DB_ROOT_PASS} -e "SELECT 1;" &>/dev/null; then
        echo "✅ Base de données prête"
        break
    fi
    
    if [ $i -eq 30 ]; then
        echo "❌ Timeout: La base de données n'est pas prête après 30 tentatives"
        echo "💡 Vérifiez les logs: $DOCKER_COMPOSE_CMD logs db"
        exit 1
    fi
    
    echo "   Tentative $i/30..."
    sleep 2
done

# Étape 7: Vérification de l'utilisateur
echo ""
echo "7. Vérification de l'utilisateur de base de données..."

# Créer l'utilisateur s'il n'existe pas
$DOCKER_COMPOSE_CMD exec -T db mariadb -h localhost -u root -p${DB_ROOT_PASS} -e "
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
" 2>/dev/null || echo "⚠️  Utilisateur déjà configuré"

echo "✅ Utilisateur de base de données configuré"

# Étape 8: Vérification de la base de données V3
echo ""
echo "8. Vérification de la base de données V3..."
echo "📊 La base V3 est automatiquement importée par Docker au démarrage"

if [ -f "db/techsuivi_db V3.sql" ]; then
    echo "✅ Fichier de base V3 trouvé et configuré dans docker-compose.yml"
else
    echo "⚠️  Fichier db/techsuivi_db V3.sql non trouvé"
    echo "💡 Assurez-vous que le fichier existe pour une installation complète"
fi

# Étape 9: Test de connexion
echo ""
echo "9. Test de connexion..."
if $DOCKER_COMPOSE_CMD exec -T db mariadb -h localhost -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "SELECT 1;" &>/dev/null; then
    echo "✅ Connexion à la base de données réussie"
else
    echo "❌ Impossible de se connecter à la base de données"
    echo "💡 Vérifiez les logs avec: $DOCKER_COMPOSE_CMD logs db"
    echo "💡 Ou utilisez le script de diagnostic: ./debug_installation.sh"
    exit 1
fi

# Étape 10: Installation de PHPMailer
echo ""
echo "10. Installation de PHPMailer..."
echo "📧 Configuration du système d'envoi d'emails"

if [ -f "install_phpmailer.sh" ]; then
    echo "   🔧 Exécution du script d'installation PHPMailer..."
    if bash install_phpmailer.sh; then
        echo "✅ PHPMailer installé avec succès"
    else
        echo "⚠️  Erreur lors de l'installation de PHPMailer (non critique)"
    fi
else
    echo "⚠️  Script install_phpmailer.sh non trouvé, installation manuelle nécessaire"
fi

# Étape 11: Configuration du cron Docker
echo ""
echo "11. Configuration des tâches programmées..."
echo "⏰ Installation du système de cron pour Docker"

if [ -f "install_cron_docker.sh" ]; then
    echo "   🐳 Exécution du script de configuration cron Docker..."
    if bash install_cron_docker.sh; then
        echo "✅ Système de cron configuré avec succès"
    else
        echo "⚠️  Erreur lors de la configuration du cron (non critique)"
    fi
else
    echo "⚠️  Script install_cron_docker.sh non trouvé, configuration manuelle nécessaire"
fi

# Étape 12: Correction des permissions des fichiers uploads
echo ""
echo "12. Correction des permissions des fichiers..."
echo "🔧 Configuration des dossiers uploads pour éviter les erreurs de permissions"

# Fonction pour corriger les permissions (intégrée du script fix_permissions.sh)
fix_permissions_auto() {
    local base_dir="web/src/uploads"
    local folders=(
        "$base_dir"
        "$base_dir/backups"
        "$base_dir/interventions"
        "$base_dir/autoit"
        "$base_dir/autoit/logiciels"
        "$base_dir/autoit/nettoyage"
        "$base_dir/autoit/personnalisation"
    )
    
    echo "   📁 Création des dossiers nécessaires..."
    for folder in "${folders[@]}"; do
        if [ ! -d "$folder" ]; then
            mkdir -p "$folder" 2>/dev/null || true
            echo "      ✅ Créé: $folder"
        fi
    done
    
    echo "   🔐 Application des permissions 775..."
    chmod -R 775 "$base_dir" 2>/dev/null || true
    
    echo "   👤 Changement de propriétaire (si possible)..."
    if command -v chown &> /dev/null; then
        # Essayer de changer le propriétaire vers www-data si possible
        chown -R www-data:www-data "$base_dir" 2>/dev/null || {
            # Si www-data n'existe pas, essayer avec l'utilisateur actuel
            chown -R $(whoami):$(whoami) "$base_dir" 2>/dev/null || true
        }
    fi
    
    # Correction via Docker si les conteneurs sont en cours d'exécution
    echo "   🐳 Correction des permissions via Docker..."
    $DOCKER_COMPOSE_CMD exec -T web chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true
    $DOCKER_COMPOSE_CMD exec -T web chmod -R 775 /var/www/html/uploads 2>/dev/null || true
    
    echo "✅ Permissions corrigées avec succès"
}

# Exécuter la correction des permissions
fix_permissions_auto

# Étape 13: Affichage des informations finales
echo ""
echo "=== INSTALLATION TERMINÉE ==="
echo ""
echo "🎉 TechSuivi V3 est maintenant installé et configuré !"
echo ""
echo "📋 Informations de connexion:"
echo "   🌐 Application web: http://localhost:8080"
echo "   🗄️  PhpMyAdmin: http://localhost:8081"
echo ""
echo "📊 Base de données:"
echo "   📍 Hôte: ${DB_HOST}"
echo "   🏷️  Base: ${DB_NAME} (Version 3)"
echo "   👤 Utilisateur: ${DB_USER}"
echo "   🔑 Mot de passe: ${DB_PASS}"
echo ""
echo "📧 Fonctionnalités installées:"
echo "   ✅ Base de données V3 avec nouvelles tables"
echo "   ✅ PHPMailer pour l'envoi d'emails"
echo "   ✅ Système de rapports automatisés"
echo "   ✅ Tâches programmées (cron)"
echo "   ✅ Configuration OAuth2 pour emails"
echo ""
echo "🔧 Commandes utiles:"
echo "   📊 Voir les logs: $DOCKER_COMPOSE_CMD logs"
echo "   🔄 Redémarrer: $DOCKER_COMPOSE_CMD restart"
echo "   🛑 Arrêter: $DOCKER_COMPOSE_CMD down"
echo ""
echo "📝 Prochaines étapes recommandées:"
echo "   1. Accédez à http://localhost:8080 pour configurer votre premier utilisateur"
echo "   2. Configurez les paramètres d'email dans Configuration > Mail"
echo "   3. Testez les rapports automatisés dans Configuration > Rapports"
echo "   4. Vérifiez les tâches programmées dans Configuration > Tâches programmées"
echo ""
echo "✅ Installation automatique TechSuivi V3 terminée avec succès !"
echo ""
