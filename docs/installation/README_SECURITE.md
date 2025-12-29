# 🛡️ Suite de Sécurité TechSuivi

Cette suite d'outils vous permet de vérifier, corriger et monitorer la sécurité de votre application web TechSuivi de manière complète et automatisée.

## 📋 Vue d'ensemble des outils

| Outil | Description | Usage |
|-------|-------------|-------|
| [`GUIDE_AUDIT_SECURITE.md`](GUIDE_AUDIT_SECURITE.md) | Guide complet d'audit de sécurité | Documentation |
| [`security_audit.php`](security_audit.php) | Audit automatisé du code source | `php security_audit.php` |
| [`security_fixes.php`](security_fixes.php) | Corrections automatiques des vulnérabilités | `php security_fixes.php` |
| [`penetration_test.php`](penetration_test.php) | Tests de pénétration automatisés | `php penetration_test.php [URL]` |
| [`security_monitor.php`](security_monitor.php) | Monitoring en temps réel | `php security_monitor.php` |

## 🚀 Guide de démarrage rapide

### 1. Audit initial
```bash
# Analyser le code source pour identifier les vulnérabilités
php security_audit.php
```

### 2. Corrections automatiques
```bash
# Appliquer les corrections de sécurité (avec sauvegarde)
php security_fixes.php
```

### 3. Tests de pénétration
```bash
# Tester la sécurité de l'application en ligne
php penetration_test.php http://localhost
```

### 4. Monitoring continu
```bash
# Démarrer le monitoring en temps réel
php security_monitor.php
```

## 📊 Détail des outils

### 🔍 Security Audit (`security_audit.php`)

**Fonctionnalités :**
- Détection des inclusions de fichiers non sécurisées (LFI)
- Recherche d'injections SQL potentielles
- Identification des vulnérabilités XSS
- Vérification de la sécurité des uploads
- Analyse de la configuration des sessions
- Contrôle des fichiers de configuration exposés
- Vérification du .htaccess

**Exemple de sortie :**
```
🔍 Audit de sécurité TechSuivi
================================

🔍 Vérification des inclusions de fichiers...
   Trouvé 1 vulnérabilité(s)

🔍 Vérification des injections SQL...
   Trouvé 0 vulnérabilité(s)

📊 RAPPORT D'AUDIT DE SÉCURITÉ
===============================

🔴 FILE INCLUSIONS
--------------------------------------------------
  • Potential LFI [ÉLEVÉ]
    Fichier: web/src/index.php
    Description: Paramètre page utilisé pour inclusion - vérifier la validation
```

### 🔧 Security Fixes (`security_fixes.php`)

**Corrections appliquées :**
- Liste blanche pour les inclusions de fichiers
- Protection CSRF pour les formulaires
- Configuration sécurisée des sessions
- Headers de sécurité HTTP
- .htaccess sécurisé avec règles de protection

**Utilisation :**
```bash
php security_fixes.php
# Tapez 'oui' pour confirmer les modifications
```

**Fichiers de sauvegarde :**
Les fichiers originaux sont sauvegardés dans `security_backup_YYYY-MM-DD_HH-MM-SS/`

### 🎯 Penetration Test (`penetration_test.php`)

**Tests effectués :**
- Directory Traversal (LFI/RFI)
- Injection SQL
- Cross-Site Scripting (XSS)
- Sécurité des uploads de fichiers
- Sécurité des sessions
- Headers de sécurité HTTP
- Exposition de fichiers sensibles
- Protection contre la force brute

**Utilisation :**
```bash
# Test sur localhost
php penetration_test.php http://localhost

# Test sur un autre domaine
php penetration_test.php https://votre-site.com
```

**⚠️ ATTENTION :** N'utilisez cet outil que sur vos propres applications !

### 🛡️ Security Monitor (`security_monitor.php`)

**Monitoring en temps réel :**
- Tentatives de connexion suspectes
- Modifications de fichiers critiques
- Analyse des logs d'accès
- Détection de patterns suspects
- Monitoring des ressources système
- Alertes en temps réel

**Configuration :**
Le fichier `monitor_config.json` est créé automatiquement avec les paramètres par défaut :

```json
{
    "check_interval": 60,
    "max_login_attempts": 5,
    "login_attempt_window": 300,
    "file_integrity_check": true,
    "log_analysis": true,
    "real_time_alerts": true,
    "email_alerts": false,
    "admin_email": "admin@techsuivi.com"
}
```

**Utilisation :**
```bash
# Démarrer le monitoring
php security_monitor.php

# Arrêter avec Ctrl+C
```

## 🔧 Configuration avancée

### Variables d'environnement
Créez un fichier `.env` à la racine pour configurer :
```env
# Base de données
MYSQL_ROOT_PASSWORD=votre_mot_de_passe
MYSQL_DATABASE=TechSuivi
MYSQL_USER=monuser
MYSQL_PASSWORD=motdepasseuser

# Sécurité
SECURITY_ALERTS_EMAIL=admin@votre-domaine.com
MONITORING_ENABLED=true
```

### Configuration Apache/Nginx

#### Apache (.htaccess)
Le script `security_fixes.php` génère automatiquement un `.htaccess` sécurisé.

#### Nginx
Ajoutez ces règles à votre configuration Nginx :
```nginx
# Headers de sécurité
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";
add_header X-Content-Type-Options nosniff;
add_header Referrer-Policy "strict-origin-when-cross-origin";

# Bloquer l'accès aux fichiers sensibles
location ~ /\.(env|git|htaccess) {
    deny all;
}

# Désactiver PHP dans uploads
location ^~ /uploads/ {
    location ~ \.php$ {
        deny all;
    }
}
```

## 📈 Interprétation des résultats

### Niveaux de sévérité
- 🔴 **CRITIQUE** : Corrigez immédiatement
- 🟠 **ÉLEVÉ** : Corrigez dans les 24h
- 🟡 **MOYEN** : Corrigez dans la semaine
- 🟢 **FAIBLE** : Améliorations recommandées

### Actions recommandées par sévérité

#### Vulnérabilités CRITIQUES
1. **Arrêtez l'application** si elle est en production
2. **Appliquez les corrections** immédiatement
3. **Testez** les corrections
4. **Redémarrez** l'application
5. **Surveillez** les logs

#### Vulnérabilités ÉLEVÉES
1. **Planifiez** une maintenance
2. **Appliquez** les corrections
3. **Testez** en environnement de développement
4. **Déployez** les corrections

## 🔄 Workflow de sécurité recommandé

### Audit initial (une fois)
```bash
# 1. Audit complet
php security_audit.php > audit_initial.txt

# 2. Corrections automatiques
php security_fixes.php

# 3. Test de pénétration
php penetration_test.php http://localhost > pentest_initial.txt
```

### Maintenance régulière (hebdomadaire)
```bash
# 1. Audit de routine
php security_audit.php

# 2. Test de pénétration
php penetration_test.php http://votre-site.com
```

### Monitoring continu (permanent)
```bash
# Démarrer le monitoring (en arrière-plan)
nohup php security_monitor.php > monitor.log 2>&1 &
```

## 🚨 Gestion des incidents

### En cas d'alerte critique
1. **Isolez** l'application (maintenance mode)
2. **Analysez** les logs : `tail -f security_monitor.log`
3. **Identifiez** la source de l'attaque
4. **Bloquez** l'IP suspecte
5. **Corrigez** la vulnérabilité
6. **Restaurez** le service

### Commandes utiles
```bash
# Voir les alertes récentes
tail -n 50 security_monitor.log

# Analyser les tentatives de connexion
grep "BRUTE_FORCE" security_alerts.json

# Bloquer une IP (iptables)
sudo iptables -A INPUT -s IP_SUSPECTE -j DROP
```

## 📚 Ressources supplémentaires

### Documentation de référence
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Guide ANSSI](https://www.ssi.gouv.fr/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)

### Outils complémentaires
- **OWASP ZAP** : Scanner de vulnérabilités web
- **Nikto** : Scanner de serveur web
- **Burp Suite** : Proxy d'interception
- **Fail2Ban** : Protection contre la force brute

## 🤝 Support et contribution

### Signaler un problème
Si vous trouvez un bug ou une fausse alerte :
1. Vérifiez les logs : `security_monitor.log`
2. Consultez la configuration : `monitor_config.json`
3. Documentez le problème avec les détails techniques

### Améliorer les outils
Les scripts sont modulaires et peuvent être étendus :
- Ajoutez de nouveaux patterns de détection
- Intégrez d'autres sources de logs
- Personnalisez les alertes

## ⚖️ Avertissements légaux

- Ces outils sont destinés à **vos propres applications**
- N'utilisez **jamais** ces scripts sur des sites tiers sans autorisation
- Les tests de pénétration peuvent déclencher des alertes de sécurité
- Respectez les lois locales sur la cybersécurité

## 📝 Changelog

### Version 1.0
- Audit automatisé du code source
- Corrections automatiques des vulnérabilités communes
- Tests de pénétration de base
- Monitoring en temps réel
- Documentation complète

---

**🛡️ Sécurisez votre application TechSuivi dès maintenant !**

Pour toute question technique, consultez le guide détaillé [`GUIDE_AUDIT_SECURITE.md`](GUIDE_AUDIT_SECURITE.md).