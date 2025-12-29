#!/bin/bash

# 🤖 Script d'installation automatique du cron pour TechSuivi - Version Docker
# Ce script configure automatiquement le cron pour les tâches programmées dans un environnement Docker

echo "🚀 Installation du cron pour TechSuivi - Tâches programmées (Docker)"
echo "=================================================================="

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages colorés
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

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

# Détecter Docker Compose
DOCKER_COMPOSE_CMD=$(detect_docker_compose)
if [ $? -ne 0 ]; then
    print_error "Docker Compose n'est pas installé"
    exit 1
fi

print_info "Utilisation de: $DOCKER_COMPOSE_CMD"

# Vérifier que les conteneurs sont en cours d'exécution
if ! $DOCKER_COMPOSE_CMD ps | grep -q "web.*Up"; then
    print_error "Le conteneur web n'est pas en cours d'exécution"
    print_info "Démarrez les conteneurs avec: $DOCKER_COMPOSE_CMD up -d"
    exit 1
fi

print_success "Conteneur web détecté et en cours d'exécution"

# Vérifier que le script cron existe dans le conteneur
CRON_SCRIPT="/var/www/html/cron/advanced_scheduled_tasks.php"
if ! $DOCKER_COMPOSE_CMD exec -T web test -f "$CRON_SCRIPT"; then
    print_error "Script cron non trouvé dans le conteneur: $CRON_SCRIPT"
    print_info "Assurez-vous que le projet TechSuivi est correctement monté"
    exit 1
fi

print_success "Script cron trouvé dans le conteneur: $CRON_SCRIPT"

# Tester le script PHP dans le conteneur
print_info "Test du script cron dans le conteneur..."
if $DOCKER_COMPOSE_CMD exec -T web php "$CRON_SCRIPT" > /dev/null 2>&1; then
    print_success "Script cron testé avec succès dans le conteneur"
else
    print_error "Erreur lors du test du script cron dans le conteneur"
    print_info "Exécution du test en mode verbose:"
    $DOCKER_COMPOSE_CMD exec -T web php "$CRON_SCRIPT"
    exit 1
fi

# Configuration automatique pour Docker (toutes les 5 minutes par défaut)
CRON_EXPRESSION="*/5 * * * *"
DESCRIPTION="toutes les 5 minutes"

print_info "Configuration cron Docker: $CRON_EXPRESSION"

# Créer la ligne cron pour Docker
# Obtenir le répertoire actuel du script pour s'assurer que Docker Compose s'exécute depuis le bon répertoire
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRON_LINE="$CRON_EXPRESSION cd $SCRIPT_DIR && $DOCKER_COMPOSE_CMD exec -T web php $CRON_SCRIPT"
CRON_COMMENT="# TechSuivi Docker - Tâches programmées ($DESCRIPTION)"

print_info "Commande cron: $CRON_LINE"

# Vérifier si une tâche cron TechSuivi Docker existe déjà
if crontab -l 2>/dev/null | grep -q "advanced_scheduled_tasks.php"; then
    print_warning "Une tâche cron TechSuivi existe déjà"
    # Supprimer l'ancienne tâche automatiquement en mode Docker
    crontab -l 2>/dev/null | grep -v "advanced_scheduled_tasks.php" | grep -v "TechSuivi.*Tâches programmées" | crontab -
    print_success "Ancienne tâche cron supprimée"
fi

# Ajouter la nouvelle tâche cron
(crontab -l 2>/dev/null; echo "$CRON_COMMENT"; echo "$CRON_LINE") | crontab -

if [ $? -eq 0 ]; then
    print_success "Tâche cron Docker installée avec succès!"
    print_info "Fréquence: $DESCRIPTION"
    print_info "Commande: $CRON_LINE"
else
    print_error "Erreur lors de l'installation de la tâche cron Docker"
    exit 1
fi

# Vérifier que le service cron est actif
if systemctl is-active --quiet cron 2>/dev/null || systemctl is-active --quiet crond 2>/dev/null; then
    print_success "Service cron actif"
elif service cron status >/dev/null 2>&1; then
    print_success "Service cron actif"
else
    print_warning "Le service cron ne semble pas être actif"
    print_info "Démarrez le service cron avec: sudo systemctl start cron"
fi

# Afficher les tâches cron actuelles
echo ""
print_info "Tâches cron actuelles:"
crontab -l 2>/dev/null | grep -E "(TechSuivi|advanced_scheduled_tasks)" || print_warning "Aucune tâche TechSuivi trouvée"

# Créer un fichier de log pour les tests (dans le conteneur)
LOG_DIR="/var/www/html/cron"
LOG_FILE="$LOG_DIR/advanced_cron.log"

print_info "Création du fichier de log dans le conteneur..."
$DOCKER_COMPOSE_CMD exec -T web mkdir -p "$LOG_DIR" 2>/dev/null || true
$DOCKER_COMPOSE_CMD exec -T web touch "$LOG_FILE" 2>/dev/null || true

if $DOCKER_COMPOSE_CMD exec -T web test -f "$LOG_FILE"; then
    print_success "Fichier de log créé dans le conteneur: $LOG_FILE"
else
    print_warning "Impossible de créer le fichier de log dans le conteneur"
fi

# Test d'exécution immédiat
print_info "Test d'exécution immédiat..."
if $DOCKER_COMPOSE_CMD exec -T web php "$CRON_SCRIPT" > /dev/null 2>&1; then
    print_success "Test d'exécution réussi"
else
    print_warning "Erreur lors du test d'exécution (vérifiez la configuration)"
fi

# Instructions finales
echo ""
print_success "🎉 Installation Docker terminée avec succès!"
echo ""
print_info "📋 Configuration:"
echo "• Fréquence: $DESCRIPTION"
echo "• Conteneur: web"
echo "• Script: $CRON_SCRIPT"
echo "• Log: $LOG_FILE"
echo ""
print_info "🔧 Commandes utiles:"
echo "• Voir les tâches cron: crontab -l"
echo "• Éditer les tâches cron: crontab -e"
echo "• Voir les logs TechSuivi: $DOCKER_COMPOSE_CMD exec web tail -f $LOG_FILE"
echo "• Test manuel: $DOCKER_COMPOSE_CMD exec web php $CRON_SCRIPT"
echo "• Logs du conteneur: $DOCKER_COMPOSE_CMD logs web"
echo ""
print_info "🌐 Interface de monitoring:"
echo "Accédez à: http://localhost:8080/cron/advanced_scheduled_tasks.php"
echo ""
print_success "Le système de tâches programmées TechSuivi Docker est maintenant opérationnel!"