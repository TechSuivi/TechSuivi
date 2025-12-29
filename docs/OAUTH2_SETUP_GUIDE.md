# Guide de Configuration OAuth2 pour TechSuivi

## 🔐 Introduction à OAuth2

OAuth2 est devenu la norme pour l'authentification des services email modernes. Les fournisseurs comme Gmail et Outlook exigent maintenant OAuth2 au lieu des mots de passe d'application pour des raisons de sécurité.

### Pourquoi OAuth2 ?

- **Sécurité renforcée** : Pas de stockage de mots de passe
- **Tokens temporaires** : Accès limité dans le temps
- **Permissions granulaires** : Contrôle précis des accès
- **Révocation facile** : Possibilité de révoquer l'accès à tout moment

## 📋 Prérequis

- TechSuivi installé avec Docker
- Accès administrateur à TechSuivi
- Compte Google ou Microsoft selon le provider choisi
- Accès aux consoles de développement (Google Cloud Console ou Azure Portal)

## 🚀 Configuration Google OAuth2

### Étape 1 : Créer un projet Google Cloud

1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créez un nouveau projet ou sélectionnez un projet existant
3. Notez l'ID du projet

### Étape 2 : Activer l'API Gmail

1. Dans le menu de navigation, allez à **APIs & Services** > **Library**
2. Recherchez "Gmail API"
3. Cliquez sur **Enable**

### Étape 3 : Créer des identifiants OAuth2

1. Allez à **APIs & Services** > **Credentials**
2. Cliquez sur **Create Credentials** > **OAuth 2.0 Client IDs**
3. Configurez l'écran de consentement OAuth si demandé :
   - **Application type** : Web application
   - **Name** : TechSuivi Mail
   - **User Type** : External (pour usage général)
4. Créez les identifiants OAuth 2.0 :
   - **Application type** : Web application
   - **Name** : TechSuivi
   - **Authorized redirect URIs** : `http://votre-domaine:8080/api/oauth2_callback.php?provider=google`

### Étape 4 : Récupérer les identifiants

1. Notez le **Client ID** (format : `123456789-abcdefg.apps.googleusercontent.com`)
2. Notez le **Client Secret** (format : `GOCSPX-...`)

### Étape 5 : Configuration dans TechSuivi

1. Connectez-vous à TechSuivi
2. Allez dans **Paramètres** > **OAuth2 / Auth Moderne**
3. Onglet **Google/Gmail** :
   - **Client ID** : Collez votre Client ID
   - **Client Secret** : Collez votre Client Secret
   - **URI de Redirection** : Vérifiez qu'elle correspond à celle configurée dans Google Cloud
   - **Scopes** : `https://www.googleapis.com/auth/gmail.send`
   - Cochez **Activer cette configuration**
4. Cliquez sur **Sauvegarder Google OAuth2**

## 🔵 Configuration Outlook OAuth2

### Étape 1 : Créer une application Azure

1. Allez sur [Azure Portal](https://portal.azure.com/)
2. Recherchez et sélectionnez **Azure Active Directory**
3. Allez à **App registrations** > **New registration**

### Étape 2 : Configurer l'application

1. **Name** : TechSuivi Mail
2. **Supported account types** : Accounts in any organizational directory and personal Microsoft accounts
3. **Redirect URI** : 
   - Type : Web
   - URI : `http://votre-domaine:8080/api/oauth2_callback.php?provider=outlook`
4. Cliquez sur **Register**

### Étape 3 : Configurer les permissions

1. Dans votre application, allez à **API permissions**
2. Cliquez sur **Add a permission**
3. Sélectionnez **Microsoft Graph**
4. Choisissez **Delegated permissions**
5. Ajoutez : `Mail.Send`
6. Cliquez sur **Grant admin consent** (si vous êtes admin)

### Étape 4 : Créer un secret client

1. Allez à **Certificates & secrets**
2. Cliquez sur **New client secret**
3. **Description** : TechSuivi Secret
4. **Expires** : 24 months (recommandé)
5. Cliquez sur **Add**
6. **IMPORTANT** : Copiez immédiatement la valeur du secret (elle ne sera plus visible)

### Étape 5 : Récupérer les identifiants

1. Dans **Overview**, notez :
   - **Application (client) ID** (format : `12345678-1234-1234-1234-123456789012`)
   - **Directory (tenant) ID** (ou utilisez `common` pour multi-tenant)
2. Le **Client Secret** copié à l'étape précédente

### Étape 6 : Configuration dans TechSuivi

1. Connectez-vous à TechSuivi
2. Allez dans **Paramètres** > **OAuth2 / Auth Moderne**
3. Onglet **Outlook/Hotmail** :
   - **Application (client) ID** : Collez votre Client ID
   - **Client Secret** : Collez votre Client Secret
   - **Directory (tenant) ID** : `common` ou votre tenant ID spécifique
   - **URI de Redirection** : Vérifiez qu'elle correspond à celle configurée dans Azure
   - **Scopes** : `https://graph.microsoft.com/Mail.Send`
   - Cochez **Activer cette configuration**
4. Cliquez sur **Sauvegarder Outlook OAuth2**

## 🧪 Test de la Configuration

### Test d'authentification

1. Dans l'onglet **État des Configurations**, cliquez sur **Tester** pour le provider configuré
2. Une nouvelle fenêtre s'ouvrira pour l'authentification
3. Connectez-vous avec votre compte Google ou Microsoft
4. Accordez les permissions demandées
5. Vous devriez voir un message de succès

### Test d'envoi d'email

1. Allez dans **Paramètres** > **Configuration Mail**
2. Utilisez la fonction de test d'email
3. Le système utilisera automatiquement OAuth2 si disponible

## 🔧 Dépannage

### Erreurs courantes

#### "Configuration OAuth2 non trouvée"
- Vérifiez que la configuration est **active** dans TechSuivi
- Vérifiez que tous les champs obligatoires sont remplis

#### "URI de redirection non autorisée"
- Vérifiez que l'URI dans TechSuivi correspond exactement à celle configurée dans la console du provider
- Attention aux protocoles (http vs https) et aux ports

#### "Permissions insuffisantes"
- Google : Vérifiez que l'API Gmail est activée
- Outlook : Vérifiez que `Mail.Send` est accordé et consenti

#### "Token expiré"
- Les tokens OAuth2 expirent automatiquement
- TechSuivi tente de les rafraîchir automatiquement
- Si le problème persiste, re-authentifiez-vous

### Logs de débogage

Les erreurs OAuth2 sont enregistrées dans les logs PHP. Pour les consulter :

```bash
docker exec web tail -f /var/log/apache2/error.log
```

## 📊 Surveillance et Maintenance

### Vérification du statut

1. **Paramètres** > **OAuth2 / Auth Moderne** > **État des Configurations**
2. Vérifiez que les configurations sont actives
3. Surveillez les dates d'expiration des tokens

### Renouvellement des secrets

- **Google** : Les secrets n'expirent pas automatiquement
- **Outlook** : Les secrets expirent selon la durée configurée (max 24 mois)
- Planifiez le renouvellement avant expiration

### Révocation d'accès

Pour révoquer l'accès OAuth2 :

1. **Google** : [myaccount.google.com/permissions](https://myaccount.google.com/permissions)
2. **Microsoft** : [account.microsoft.com/privacy/app-access](https://account.microsoft.com/privacy/app-access)

## 🔒 Sécurité

### Bonnes pratiques

1. **Principe du moindre privilège** : N'accordez que les permissions nécessaires
2. **Rotation des secrets** : Renouvelez régulièrement les secrets clients
3. **Surveillance** : Surveillez les logs pour détecter les tentatives d'accès suspectes
4. **Sauvegarde** : Sauvegardez vos configurations OAuth2

### Protection des données

- Les tokens sont stockés chiffrés en base de données
- Les secrets clients ne sont jamais exposés dans l'interface
- Les communications utilisent HTTPS en production

## 📞 Support

En cas de problème :

1. Consultez les logs d'erreur
2. Vérifiez la configuration étape par étape
3. Testez avec un compte de test d'abord
4. Consultez la documentation officielle des providers :
   - [Google OAuth2](https://developers.google.com/identity/protocols/oauth2)
   - [Microsoft OAuth2](https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow)

## 🎯 Conclusion

OAuth2 offre une sécurité renforcée pour l'envoi d'emails. Bien que la configuration initiale soit plus complexe que SMTP classique, les avantages en termes de sécurité et de fiabilité en valent la peine.

Une fois configuré, OAuth2 fonctionne de manière transparente et TechSuivi gère automatiquement le renouvellement des tokens et les fallbacks nécessaires.