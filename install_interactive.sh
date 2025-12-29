#!/bin/bash

# Script d'installation interactive TechSuivi
# Version interactive permettant la personnalisation complète

set -e

echo "=== INSTALLATION INTERACTIVE TECHSUIVI ==="
echo ""
echo "🎯 Ce script vous permet de personnaliser votre installation TechSuivi"
echo "   Vous pouvez configurer les ports, utilisateurs et mots de passe"
echo ""

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

# Fonction pour valider un port
validate_port() {
    local port=$1
    if [[ $port =~ ^[0-9]+$ ]] && [ $port -ge 1 ] && [ $port -le 65535 ]; then
        return 0
    else
        return 1
    fi
}

# Fonction pour demander une saisie avec valeur par défaut
ask_with_default() {
    local prompt=$1
    local default=$2
    local var_name=$3
    
    echo -n "$prompt [$default]: "
    read input
    if [ -z "$input" ]; then
        eval "$var_name='$default'"
    else
        eval "$var_name='$input'"
    fi
}

# Fonction pour demander un mot de passe
ask_password() {
    local prompt=$1
    local var_name=$2
    local default=$3
    
    echo -n "$prompt [$default]: "
    read -s password
    echo ""
    if [ -z "$password" ]; then
        eval "$var_name='$default'"
    else
        eval "$var_name='$password'"
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

# Étape 2: Configuration interactive
echo ""
echo "2. Configuration personnalisée..."
echo "📝 Personnalisez votre installation TechSuivi"
echo ""

# Configuration des ports
echo "🌐 Configuration des ports :"
WEB_PORT_DEFAULT="8080"
PHPMYADMIN_PORT_DEFAULT="8081"

while true; do
    ask_with_default "Port pour l'application web" "$WEB_PORT_DEFAULT" "WEB_PORT"
    if validate_port "$WEB_PORT"; then
        break
    else
        echo "❌ Port invalide. Utilisez un port entre 1 et 65535."
    fi
done

while true; do
    ask_with_default "Port pour PhpMyAdmin" "$PHPMYADMIN_PORT_DEFAULT" "PHPMYADMIN_PORT"
    if validate_port "$PHPMYADMIN_PORT"; then
        if [ "$PHPMYADMIN_PORT" != "$WEB_PORT" ]; then
            break
        else
            echo "❌ Le port PhpMyAdmin doit être différent du port web."
        fi
    else
        echo "❌ Port invalide. Utilisez un port entre 1 et 65535."
    fi
done

echo ""
echo "🗄️  Configuration de la base de données :"

# Configuration par défaut
DB_HOST="db"
DB_NAME_DEFAULT="techsuivi_db"
DB_USER_DEFAULT="techsuivi_user"
DB_PASS_DEFAULT="techsuivi_pass_$(date +%Y)"
DB_ROOT_PASS_DEFAULT="techsuivi_root_$(date +%Y)"

ask_with_default "Nom de la base de données" "$DB_NAME_DEFAULT" "DB_NAME"
ask_with_default "Utilisateur de la base de données" "$DB_USER_DEFAULT" "DB_USER"
ask_password "Mot de passe utilisateur" "DB_PASS" "$DB_PASS_DEFAULT"
ask_password "Mot de passe root MySQL" "DB_ROOT_PASS" "$DB_ROOT_PASS_DEFAULT"

echo ""
echo "📋 Récapitulatif de la configuration :"
echo "   🌐 Application web : http://localhost:$WEB_PORT"
echo "   🗄️  PhpMyAdmin : http://localhost:$PHPMYADMIN_PORT"
echo "   📍 Base de données : $DB_NAME"
echo "   👤 Utilisateur DB : $DB_USER"
echo "   🔑 Mot de passe DB : ${DB_PASS:0:3}***"
echo ""

read -p "Confirmer l'installation avec ces paramètres ? (o/N): " confirm
if [[ ! $confirm =~ ^[oO]$ ]]; then
    echo "❌ Installation annulée par l'utilisateur"
    exit 0
fi

# Étape 3: Modification du docker-compose.yml
echo ""
echo "3. Configuration des ports Docker..."
echo "🔧 Modification du fichier docker-compose.yml"

# Sauvegarde du fichier original
if [ -f "docker-compose.yml" ]; then
    cp docker-compose.yml docker-compose.yml.backup
    echo "   💾 Sauvegarde créée : docker-compose.yml.backup"
fi

# Modification des ports dans docker-compose.yml
sed -i.tmp "s/\"8080:80\"/\"$WEB_PORT:80\"/g" docker-compose.yml
sed -i.tmp "s/\"8081:80\"/\"$PHPMYADMIN_PORT:80\"/g" docker-compose.yml
rm -f docker-compose.yml.tmp

echo "✅ Ports configurés dans docker-compose.yml"

# Étape 4: Création du fichier .env
echo ""
echo "4. Création des fichiers de configuration..."
echo "📝 Génération des fichiers .env"

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

# Étape 5: Création du fichier .env pour l'application web
echo ""
echo "5. Configuration de l'application web..."
mkdir -p web/src
cat > web/src/.env << EOF
# Configuration de la base de données pour l'application web
DB_HOST=${DB_HOST}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
EOF

echo "✅ Configuration web créée dans web/src/.env"

# Étape 6: Arrêt des conteneurs existants
echo ""
echo "6. Nettoyage des conteneurs existants..."
$DOCKER_COMPOSE_CMD down --remove-orphans 2>/dev/null || true
echo "✅ Conteneurs arrêtés"

# Étape 7: Construction et démarrage
echo ""
echo "7. Construction et démarrage des conteneurs..."
echo "⏳ Cette étape peut prendre quelques minutes..."

if $DOCKER_COMPOSE_CMD up -d --build; then
    echo "✅ Conteneurs démarrés avec succès"
else
    echo "❌ Erreur lors du démarrage des conteneurs"
    echo "💡 Vérifiez les logs avec: $DOCKER_COMPOSE_CMD logs"
    
    # Restauration du docker-compose.yml original en cas d'erreur
    if [ -f "docker-compose.yml.backup" ]; then
        echo "🔄 Restauration du fichier docker-compose.yml original..."
        mv docker-compose.yml.backup docker-compose.yml
    fi
    exit 1
fi

# Étape 8: Attente que la base de données soit prête
echo ""
echo "8. Attente de la base de données..."
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

# Étape 9: Vérification de l'utilisateur
echo ""
echo "9. Configuration de l'utilisateur de base de données..."

# Créer l'utilisateur s'il n'existe pas
$DOCKER_COMPOSE_CMD exec -T db mariadb -h localhost -u root -p${DB_ROOT_PASS} -e "
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
" 2>/dev/null || echo "⚠️  Utilisateur déjà configuré"

echo "✅ Utilisateur de base de données configuré"

# Étape 10: Test de connexion
echo ""
echo "10. Test de connexion..."
if $DOCKER_COMPOSE_CMD exec -T db mariadb -h localhost -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "SELECT 1;" &>/dev/null; then
    echo "✅ Connexion à la base de données réussie"
else
    echo "❌ Impossible de se connecter à la base de données"
    echo "💡 Vérifiez la configuration et les logs"
    exit 1
fi

# Étape 11: Correction des permissions des fichiers uploads
echo ""
echo "11. Correction des permissions des fichiers..."
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
        "web/src/vnc_tokens"
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
    $DOCKER_COMPOSE_CMD exec -T web chown -R www-data:www-data /var/www/html/uploads /var/www/html/vnc_tokens 2>/dev/null || true
    $DOCKER_COMPOSE_CMD exec -T web chmod -R 775 /var/www/html/uploads /var/www/html/vnc_tokens 2>/dev/null || true
    
    echo "✅ Permissions corrigées avec succès"
}

# Exécuter la correction des permissions
fix_permissions_auto

# Nettoyage des fichiers temporaires
if [ -f "docker-compose.yml.backup" ]; then
    rm -f docker-compose.yml.backup
fi

# Étape 12: Affichage des informations finales
echo ""
echo "=== INSTALLATION TERMINÉE ==="
echo ""
echo "🎉 TechSuivi est maintenant installé et configuré avec vos paramètres personnalisés !"
echo ""
echo "📋 Informations de connexion:"
echo "   🌐 Application web: http://localhost:$WEB_PORT"
echo "   🗄️  PhpMyAdmin: http://localhost:$PHPMYADMIN_PORT"
echo ""
echo "📊 Base de données:"
echo "   📍 Hôte: ${DB_HOST}"
echo "   🏷️  Base: ${DB_NAME}"
echo "   👤 Utilisateur: ${DB_USER}"
echo "   🔑 Mot de passe: ${DB_PASS}"
echo ""
echo "🔧 Commandes utiles:"
echo "   📊 Voir les logs: $DOCKER_COMPOSE_CMD logs"
echo "   🔄 Redémarrer: $DOCKER_COMPOSE_CMD restart"
echo "   🛑 Arrêter: $DOCKER_COMPOSE_CMD down"
echo ""
echo "✅ Installation interactive terminée avec succès !"
echo ""
echo "💡 Note: Vos paramètres personnalisés ont été sauvegardés dans les fichiers .env"
echo "   Pour une réinstallation rapide, utilisez: ./install_auto.sh"