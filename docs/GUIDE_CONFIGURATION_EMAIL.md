# Guide de Configuration Email - TechSuivi

## 🎯 Résolution de l'erreur "SMTP Error: Could not authenticate"

### Diagnostic Actuel
✅ **Système fonctionnel** : L'API détecte la configuration et tente l'envoi réel
❌ **Problème** : Authentification SMTP échoue

### Solutions par Fournisseur Email

#### 📧 Outlook/Hotmail (@outlook.fr, @hotmail.com)

**Configuration recommandée :**
- **Serveur SMTP** : `smtp-mail.outlook.com` ou `smtp.live.com`
- **Port** : `587`
- **Chiffrement** : `TLS`
- **Authentification** : Activée

**⚠️ Problèmes courants :**
1. **Authentification à deux facteurs** : Utilisez un "mot de passe d'application"
2. **Sécurité renforcée** : Activez "Applications moins sécurisées" dans les paramètres Outlook

**🔧 Étapes de résolution :**
1. Connectez-vous à votre compte Outlook
2. Allez dans Paramètres > Sécurité
3. Générez un "mot de passe d'application" pour TechSuivi
4. Utilisez ce mot de passe dans la configuration (pas votre mot de passe principal)

#### 📧 Gmail (@gmail.com)

**Configuration recommandée :**
- **Serveur SMTP** : `smtp.gmail.com`
- **Port** : `587`
- **Chiffrement** : `TLS`

**🔧 Étapes de résolution :**
1. Activez l'authentification à deux facteurs
2. Générez un "mot de passe d'application"
3. Utilisez ce mot de passe dans TechSuivi

#### 📧 Autres fournisseurs

**Orange :**
- Serveur : `smtp.orange.fr`
- Port : `587` ou `465`

**Free :**
- Serveur : `smtp.free.fr`
- Port : `587`

**SFR :**
- Serveur : `smtp.sfr.fr`
- Port : `587`

### 🔍 Test de Diagnostic

Pour tester votre configuration, utilisez cette commande dans le terminal :

```bash
curl -X POST -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=test_config&test_email=VOTRE_EMAIL_DE_TEST" \
  http://192.168.10.248:8080/api/mail_actions.php
```

### 📋 Checklist de Vérification

- [ ] Serveur SMTP correct pour votre fournisseur
- [ ] Port correct (généralement 587 pour TLS)
- [ ] Chiffrement TLS activé
- [ ] Nom d'utilisateur = adresse email complète
- [ ] Mot de passe d'application (si 2FA activé)
- [ ] Paramètres de sécurité du compte email configurés

### 🚀 Une fois configuré correctement

Le système TechSuivi pourra :
- ✅ Envoyer des emails de test
- ✅ Générer des rapports automatiques
- ✅ Programmer l'envoi quotidien/hebdomadaire/mensuel
- ✅ Notifier plusieurs destinataires

### 💡 Conseil

Si vous continuez à avoir des problèmes, essayez avec un compte Gmail temporaire pour valider que le système fonctionne, puis revenez à votre configuration Outlook avec les bons paramètres.

---

**Interface de configuration :** http://192.168.10.248:8080/index.php?page=settings → Onglet "Mail"