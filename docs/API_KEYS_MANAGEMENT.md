# 🔑 Gestion des Clés API - TechSuivi

## Vue d'ensemble

Le système de gestion des clés API de TechSuivi permet de sécuriser l'accès à l'API AutoIt en gérant dynamiquement les clés d'authentification via une interface web intuitive. Les clés sont stockées dans la table `configuration` existante, garantissant une intégration parfaite avec l'architecture TechSuivi.

## 🚀 Installation

### Aucune installation requise !

Le système utilise la table `configuration` existante de TechSuivi. Aucune création de table supplémentaire n'est nécessaire.

### Vérification du système

Après l'installation de TechSuivi, vous pouvez immédiatement :
1. Accéder à l'interface de gestion des clés API
2. Créer vos premières clés API
3. Utiliser l'API avec authentification

## 🎯 Accès à l'interface

L'interface de gestion des clés API est accessible via :

**URL :** `http://votre-serveur/index.php?page=api_keys_config`

**Navigation :** Paramètres → Configuration → 🔑 Clés API

## 📋 Fonctionnalités

### ➕ Ajouter une clé API

1. **Nom de la clé** : Identifiant unique (ex: `client_mobile_2025`)
2. **Valeur de la clé** : Clé sécurisée (générée automatiquement ou personnalisée)
3. **Description** : Usage prévu de la clé
4. **Génération automatique** : Bouton "🎲 Générer" pour créer une clé aléatoirement

### ✏️ Modifier une clé API

- Modification de la valeur de la clé et de la description
- **Note :** Le nom de la clé ne peut pas être modifié pour des raisons de cohérence

### 🗑️ Supprimer une clé API

- Suppression définitive avec confirmation
- **Attention :** Cette action est irréversible

### 👁️ Visualisation des clés

- Affichage masqué par défaut (ex: `********2025`)
- Bouton pour révéler/masquer la clé complète
- Informations de création et description

## 🔐 Sécurité

### Bonnes pratiques

1. **Clés complexes** : Utilisez des clés d'au moins 8 caractères
2. **Rotation régulière** : Changez les clés périodiquement
3. **Nettoyage** : Supprimez les clés inutilisées
4. **Confidentialité** : Ne partagez jamais les clés en dehors de votre organisation

### Fonctionnalités de sécurité

- **Unicité** : Chaque nom et valeur de clé doit être unique
- **Validation** : Contrôle de la longueur minimale des clés
- **Logging** : Enregistrement des accès dans les logs serveur
- **Fallback** : Mode de secours avec clés codées en dur si aucune clé n'est configurée

## 🔌 Utilisation de l'API

### Méthodes d'authentification

L'API accepte la clé via 3 méthodes :

#### 1. Paramètre GET
```bash
curl "http://votre-serveur/api/autoit_api.php?type=logiciels&api_key=votre_cle_api"
```

#### 2. Paramètre POST
```bash
curl -X POST -d "type=logiciels&api_key=votre_cle_api" http://votre-serveur/api/autoit_api.php
```

#### 3. Header HTTP (Recommandé)
```bash
curl -H "X-API-Key: votre_cle_api" "http://votre-serveur/api/autoit_api.php?type=logiciels"
```

### Réponses d'erreur

#### Clé manquante (HTTP 401)
```json
{
  "error": true,
  "message": "Clé API manquante"
}
```

#### Clé invalide (HTTP 401)
```json
{
  "error": true,
  "message": "Clé API invalide"
}
```

## 🛠️ Administration

### Stockage dans la table `configuration`

Les clés API sont stockées dans la table `configuration` existante avec :
- **config_key** : `api_key_[nom_de_la_cle]`
- **config_value** : Valeur de la clé API
- **config_type** : `api_key`
- **category** : `api_keys`
- **description** : Description de l'usage

### Requêtes utiles

#### Lister toutes les clés API
```sql
SELECT 
    REPLACE(config_key, 'api_key_', '') as key_name,
    config_value as key_value,
    description,
    created_at 
FROM configuration 
WHERE category = 'api_keys' AND config_type = 'api_key'
ORDER BY created_at DESC;
```

#### Supprimer une clé
```sql
DELETE FROM configuration 
WHERE config_key = 'api_key_nom_de_la_cle' 
AND category = 'api_keys';
```

#### Statistiques d'usage
```sql
SELECT 
    COUNT(*) as total_keys,
    category,
    config_type
FROM configuration 
WHERE category = 'api_keys' AND config_type = 'api_key'
GROUP BY category, config_type;
```

## 🔄 Mode Fallback

Le système inclut un mode de fallback automatique :

1. **Priorité** : Table `configuration` d'abord
2. **Fallback** : Si aucune clé n'est configurée, utilisation des clés par défaut
3. **Logging** : Enregistrement du mode utilisé dans les logs

### Clés de fallback
- `autoit_key_2025` : AutoIt Client Access (Fallback)

## 📊 Monitoring

### Logs d'accès

Les accès API sont enregistrés dans les logs serveur :

```
AutoIt API - Accès autorisé avec la clé: client_mobile_2025 (Application mobile client)
AutoIt API - Mode fallback activé, table configuration non disponible: [erreur]
```

### Surveillance recommandée

1. **Tentatives d'accès non autorisées** : Surveiller les erreurs 401
2. **Usage des clés** : Analyser les logs d'accès
3. **Clés inutilisées** : Nettoyer régulièrement les clés obsolètes

## 🆘 Dépannage

### Problèmes courants

#### "Clé API invalide"
- **Vérifier** : La clé existe dans la table `configuration`
- **Catégorie** : `category = 'api_keys'` et `config_type = 'api_key'`
- **Solution** : Vérifier la clé via l'interface web

#### Interface non accessible
- **Vérifier** : Permissions utilisateur pour accéder aux paramètres
- **URL directe** : `index.php?page=api_keys_config`
- **Logs** : Vérifier les logs d'erreur du serveur web

#### Mode fallback activé
- **Cause** : Aucune clé configurée dans la table `configuration`
- **Solution** : Ajouter des clés via l'interface web
- **Temporaire** : Les clés par défaut fonctionnent

### Support

Pour toute question ou problème :
1. Vérifiez les logs serveur
2. Consultez cette documentation
3. Testez avec les clés de fallback
4. Contactez l'administrateur système

## 🏗️ Architecture

### Intégration avec TechSuivi

- **Table utilisée** : `configuration` (existante)
- **Catégorie** : `api_keys`
- **Type** : `api_key`
- **Interface** : Intégrée dans les paramètres de configuration
- **Navigation** : Menu Configuration → Clés API

### Avantages de cette approche

- ✅ **Pas de nouvelle table** : Utilise l'infrastructure existante
- ✅ **Cohérence** : Même système que les autres configurations
- ✅ **Simplicité** : Installation immédiate, pas de migration
- ✅ **Maintenance** : Gestion unifiée avec les autres paramètres

---

**Version :** 2.0  
**Dernière mise à jour :** Novembre 2025  
**Compatibilité :** TechSuivi v4+  
**Architecture :** Table `configuration` existante