# Solution Mail TechSuivi - Documentation technique

## Vue d'ensemble

TechSuivi utilise **PHPMailer** comme solution d'envoi d'emails, la bibliothèque PHP la plus populaire et fiable pour l'envoi d'emails avec support SMTP complet.

## Architecture

### 🏗️ **Structure des fichiers**

```
web/src/
├── utils/
│   ├── mail_helper.php          # Classe principale de gestion des emails
│   └── phpmailer_setup.php      # Configuration et utilitaires PHPMailer
├── pages/config/
│   └── mail_config.php          # Interface de configuration
├── api/
│   └── mail_actions.php         # API pour tests et actions mail
├── cron/
│   └── send_scheduled_reports.php # Script automatisation rapports
└── vendor/                      # Dépendances Composer (PHPMailer)
```

### 📊 **Base de données**

Table `mail_config` :
- Configuration SMTP (host, port, authentification, chiffrement)
- Paramètres expéditeur
- Configuration rapports automatiques
- Historique des envois

## Solution technique : PHPMailer

### ✅ **Pourquoi PHPMailer ?**

1. **Standard de l'industrie** : Utilisé par WordPress, Drupal, et des millions de sites
2. **Support SMTP complet** : Authentification, TLS/SSL, tous les fournisseurs
3. **Fiabilité** : Gestion avancée des erreurs et des retours
4. **Sécurité** : Protection contre l'injection d'en-têtes, validation stricte
5. **Fonctionnalités** : Pièces jointes, HTML/texte, Unicode, bounces

### 🔧 **Fonctionnalités implémentées**

#### Configuration SMTP
- **Serveurs supportés** : Gmail, Outlook, Yahoo, serveurs personnalisés
- **Chiffrement** : TLS, SSL, ou non chiffré
- **Authentification** : SMTP-AUTH avec nom d'utilisateur/mot de passe
- **Ports** : 587 (TLS), 465 (SSL), 25 (non chiffré)

#### Rapports automatiques
- **Fréquences** : Quotidien, hebdomadaire, mensuel
- **Contenu** : Statistiques interventions, messages helpdesk, sessions cyber
- **Destinataires multiples** : Support de listes d'emails
- **Automatisation** : Via script cron

#### Tests et validation
- **Test de configuration** : Envoi d'email de test
- **Test de rapport** : Génération et envoi de rapport d'exemple
- **Validation** : Vérification des paramètres SMTP

## Installation

### 🚀 **Installation automatique**

```bash
# Depuis la racine du projet TechSuivi
./install_phpmailer.sh
```

### 📦 **Installation manuelle**

```bash
cd web/src
composer require phpmailer/phpmailer
```

### 🔍 **Vérification**

La page de configuration affiche automatiquement :
- ✅ "PHPMailer" si installé
- ❌ "Non installé" avec instructions si manquant

## Configuration

### 📧 **Exemples de configuration**

#### Gmail
```
Serveur SMTP : smtp.gmail.com
Port : 587
Chiffrement : TLS
Utilisateur : votre-email@gmail.com
Mot de passe : mot-de-passe-application
```

#### Outlook/Hotmail
```
Serveur SMTP : smtp-mail.outlook.com
Port : 587
Chiffrement : TLS
Utilisateur : votre-email@outlook.com
Mot de passe : votre-mot-de-passe
```

#### Serveur personnalisé
```
Serveur SMTP : mail.votre-domaine.com
Port : 587 ou 465
Chiffrement : TLS ou SSL
Utilisateur : noreply@votre-domaine.com
Mot de passe : mot-de-passe-smtp
```

## Utilisation

### 🎯 **Interface utilisateur**

1. **Configuration** : `index.php?page=mail_config`
2. **Paramètres SMTP** : Serveur, port, authentification
3. **Expéditeur** : Email et nom d'affichage
4. **Rapports** : Activation, fréquence, destinataires
5. **Tests** : Boutons de test intégrés

### 🤖 **Automatisation**

```bash
# Cron quotidien à 8h00
0 8 * * * /usr/bin/php /path/to/techsuivi/web/src/cron/send_scheduled_reports.php

# Logs disponibles dans
/path/to/techsuivi/web/src/cron/cron.log
```

### 💻 **API programmatique**

```php
// Utilisation de la classe MailHelper
$mailHelper = new MailHelper();

// Envoyer un email
$mailHelper->sendMail(
    'destinataire@example.com',
    'Sujet du message',
    '<h1>Contenu HTML</h1>',
    true // isHtml
);

// Envoyer un rapport
$mailHelper->sendScheduledReport();
```

## Sécurité

### 🔒 **Bonnes pratiques implémentées**

1. **Mots de passe d'application** : Recommandés pour Gmail
2. **Chiffrement obligatoire** : TLS/SSL par défaut
3. **Validation des emails** : Filtres PHP intégrés
4. **Protection injection** : PHPMailer gère automatiquement
5. **Logs sécurisés** : Pas de mots de passe dans les logs

### 🛡️ **Recommandations**

- Utilisez des comptes email dédiés pour l'application
- Activez l'authentification à deux facteurs
- Surveillez les logs d'envoi
- Limitez les permissions du compte SMTP
- Utilisez des mots de passe d'application quand possible

## Dépannage

### ❌ **Erreurs courantes**

#### "PHPMailer n'est pas installé"
```bash
cd web/src
composer require phpmailer/phpmailer
```

#### "SMTP connect() failed"
- Vérifiez le serveur et le port
- Testez le chiffrement (TLS/SSL)
- Vérifiez les credentials

#### "Authentication failed"
- Utilisez un mot de passe d'application (Gmail)
- Vérifiez les identifiants
- Activez "Accès moins sécurisé" si nécessaire

### 📋 **Diagnostic**

1. **Page de diagnostic** : `debug_mail_config.php`
2. **Logs cron** : `web/src/cron/cron.log`
3. **Test de configuration** : Bouton dans l'interface
4. **Logs serveur** : Vérifiez les logs PHP/Apache

## Performance

### ⚡ **Optimisations**

- **Connexions persistantes** : PHPMailer réutilise les connexions SMTP
- **Envoi en lot** : Support des destinataires multiples
- **Gestion mémoire** : Libération automatique des ressources
- **Cache configuration** : Configuration chargée une seule fois

### 📊 **Monitoring**

- Logs d'envoi avec timestamps
- Statistiques de succès/échec
- Temps de réponse SMTP
- Alertes en cas d'erreur critique

## Évolutions futures

### 🚀 **Améliorations possibles**

1. **Templates d'emails** : Système de templates personnalisables
2. **Pièces jointes** : Support des fichiers joints aux rapports
3. **Bounces** : Gestion des retours et emails invalides
4. **Statistiques** : Dashboard de statistiques d'envoi
5. **Multi-comptes** : Support de plusieurs configurations SMTP
6. **Queue** : Système de file d'attente pour gros volumes

Cette solution offre une base solide et professionnelle pour tous les besoins d'envoi d'emails de TechSuivi.