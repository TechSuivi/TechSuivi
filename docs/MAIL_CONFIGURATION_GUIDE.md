# Guide de Configuration Mail - TechSuivi

## Vue d'ensemble

La fonctionnalité de configuration mail permet d'envoyer automatiquement des rapports d'activité par email à intervalles réguliers. Cette fonctionnalité comprend :

- Configuration des paramètres SMTP
- Gestion des destinataires des rapports
- Envoi automatique de rapports (quotidien, hebdomadaire, mensuel)
- Tests de configuration

## Installation

### 1. Création de la table de base de données

Exécutez le script SQL suivant pour créer la table de configuration mail :

```sql
-- Exécuter le contenu du fichier db/mail_config_table.sql
```

### 2. Accès à la configuration

1. Connectez-vous à TechSuivi
2. Allez dans **Paramètres** (⚙️)
3. Cliquez sur l'onglet **Configuration**
4. Sélectionnez **Configuration Mail** (📧)

## Configuration SMTP

### Paramètres requis

- **Serveur SMTP** : Adresse du serveur mail (ex: smtp.gmail.com)
- **Port SMTP** : Port de connexion (587 pour TLS, 465 pour SSL)
- **Nom d'utilisateur** : Votre adresse email
- **Mot de passe** : Mot de passe ou mot de passe d'application
- **Chiffrement** : TLS (recommandé), SSL ou Aucun

### Exemples de configuration

#### Gmail
- Serveur : `smtp.gmail.com`
- Port : `587`
- Chiffrement : `TLS`
- Utilisateur : `votre-email@gmail.com`
- Mot de passe : Mot de passe d'application (pas votre mot de passe Gmail)

#### Outlook/Hotmail
- Serveur : `smtp-mail.outlook.com`
- Port : `587`
- Chiffrement : `TLS`
- Utilisateur : `votre-email@outlook.com`
- Mot de passe : Votre mot de passe Outlook

## Configuration des rapports automatiques

### Activation
1. Cochez **"Activer l'envoi automatique de rapports"**
2. Choisissez la fréquence :
   - **Quotidien** : Tous les jours
   - **Hebdomadaire** : Chaque semaine
   - **Mensuel** : Chaque mois

### Destinataires
- Saisissez les adresses email séparées par des virgules
- Exemple : `admin@entreprise.com, manager@entreprise.com`

## Tests

### Test de configuration
1. Cliquez sur **"🧪 Tester la configuration"**
2. Saisissez une adresse email de test
3. Un email de test sera envoyé pour vérifier la configuration

### Test de rapport
1. Cliquez sur **"📊 Envoyer un rapport de test"**
2. Saisissez une adresse email
3. Un rapport d'exemple sera généré et envoyé

## Automatisation (Cron)

Pour l'envoi automatique des rapports, configurez une tâche cron :

```bash
# Tous les jours à 8h00
0 8 * * * /usr/bin/php /path/to/techsuivi/web/src/cron/send_scheduled_reports.php

# Toutes les heures (pour test)
0 * * * * /usr/bin/php /path/to/techsuivi/web/src/cron/send_scheduled_reports.php
```

### Logs
Les logs d'exécution sont disponibles dans :
`/path/to/techsuivi/web/src/cron/cron.log`

## Contenu des rapports

Les rapports automatiques incluent :

### Statistiques des interventions
- Nombre total d'interventions
- Interventions en cours
- Interventions terminées

### Messages Helpdesk
- Nombre total de messages
- Messages non traités
- Messages traités

### Sessions Cyber
- Nombre de sessions
- Chiffre d'affaires généré

## Dépannage

### Problèmes courants

#### "Configuration mail non valide"
- Vérifiez que tous les champs obligatoires sont remplis
- Testez la configuration avec le bouton de test

#### "Échec de l'envoi de l'email"
- Vérifiez les paramètres SMTP
- Assurez-vous que le serveur autorise les connexions externes
- Pour Gmail, utilisez un mot de passe d'application

#### "Erreur de connexion SMTP"
- Vérifiez le port et le type de chiffrement
- Assurez-vous que le firewall autorise les connexions sortantes

### Activation des mots de passe d'application (Gmail)

1. Allez dans votre compte Google
2. Sécurité → Validation en deux étapes
3. Mots de passe des applications
4. Générez un mot de passe pour "Mail"
5. Utilisez ce mot de passe dans TechSuivi

## Sécurité

- Les mots de passe sont stockés en base de données (considérez le chiffrement pour la production)
- Utilisez des mots de passe d'application quand possible
- Limitez les permissions du compte email utilisé
- Surveillez les logs pour détecter les tentatives d'accès non autorisées

## API

### Endpoints disponibles

- `POST api/mail_actions.php?action=test_config` : Tester la configuration
- `POST api/mail_actions.php?action=send_test_report` : Envoyer un rapport de test
- `GET api/mail_actions.php?action=check_config` : Vérifier la configuration
- `POST api/mail_actions.php?action=send_scheduled_report` : Envoyer le rapport programmé

## Support

En cas de problème :
1. Vérifiez les logs du serveur web
2. Consultez le fichier `cron.log`
3. Testez la configuration étape par étape
4. Vérifiez les paramètres de votre fournisseur email