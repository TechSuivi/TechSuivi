# Installeur TechSuivi - Téléchargement automatique

## Vue d'ensemble

Ce nouvel installeur télécharge les fichiers individuellement depuis un serveur HTTP. 

**DEUX MODES** disponibles :
1. **Mode automatique** (recommandé) ✨ - Scanner automatiquement le serveur web
2. **Mode manuel** - Utiliser un fichier `files.txt` avec la liste des fichiers

## Mode automatique (AUCUN fichier manifest requis!)

### Prérequis

Votre serveur web doit autoriser le "directory listing" :

- **Apache** : Ajoutez `Options +Indexes` dans `.htaccess`
- **nginx** : Activez `autoindex on;` dans la configuration

### Comment ça marche

1. Uploadez simplement vos fichiers sur le serveur dans n'importe quelle structure
2. Lancez l'installeur avec l'URL : `http://192.168.10.248:8080/Download/Install/`
3. L'installeur scanne **automatiquement** tous les fichiers et sous-dossiers
4. Tous les fichiers sont téléchargés et installés dans `C:\TechSuivi\`

**Exemple de structure sur le serveur** :
```
http://192.168.10.248:8080/Download/Install/
├── auto.exe
├── config.ini
├── includes/
│   ├── database.au3
│   └── gui.au3
└── images/
    └── logo.png
```

✅ **Avantages** :
- Aucun fichier manifest à créer ou maintenir
- Détection automatique de tous les fichiers
- Scan récursif des sous-dossiers
- Mise à jour = simplement uploader vos fichiers

## Mode manuel (avec fichier files.txt)

Si le directory listing n'est pas disponible, créez un fichier `files.txt` :

```txt
# Liste des fichiers à télécharger
auto.exe
config.ini
includes\database.au3
includes\gui.au3
images\logo.png
```

### Générer automatiquement files.txt

```bash
python generate_manifest.py "C:\TechSuivi" files.txt
```

Puis uploadez `files.txt` avec vos fichiers sur le serveur.

## Utilisation de l'installeur

### Première utilisation

1. Lancez `installeur.exe`
2. Entrez l'URL : `http://192.168.10.248:8080/Download/Install/`
3. Copiez le nom de fichier encodé proposé
4. Renommez `installeur.exe` pour les utilisations futures

### Utilisations suivantes

Le fichier renommé contient l'URL encodée en hexadécimal.
Double-cliquez simplement dessus pour installer automatiquement !

## Workflow de mise à jour

1. Modifiez vos fichiers TechSuivi
2. Uploadez-les sur le serveur dans `Download/Install/`
3. Les utilisateurs exécutent l'installeur
4. C'est tout ! ✨

**Note** : Avec le mode automatique, PAS besoin de régénérer quoi que ce soit !

## Dépannage

### "Impossible de récupérer la liste des fichiers"

**Vérifiez** :
1. Le directory listing est activé sur votre serveur web
2. L'URL est correcte et se termine par `/`
3. Testez dans un navigateur : `http://192.168.10.248:8080/Download/Install/`

**Solution alternative** : Créez un fichier `files.txt` (mode manuel)

### "Fichiers téléchargés: 0/X"

**Vérifiez** :
- Les fichiers existent sur le serveur
- Les permissions du serveur web sont correctes
- Testez un fichier directement dans le navigateur

## Comparaison des modes

| Caractéristique | Mode automatique | Mode manuel |
|----------------|------------------|-------------|
| Fichier manifest | ❌ Non requis | ✅ Requis (files.txt) |
| Prérequis serveur | Directory listing activé | Aucun |
| Maintenance | Aucune | Régénérer files.txt à chaque modification |
| Flexibilité | ⭐⭐⭐ | ⭐⭐ |
| Simplicité | ⭐⭐⭐ | ⭐⭐ |

**Recommandation** : Utilisez le mode automatique si possible !
