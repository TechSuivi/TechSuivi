# Configuration de la base de données TechSuivi

## Problème des tables manquantes

Si vous n'avez pas toutes les tables après le démarrage Docker, suivez ces étapes :

### 1. Vérifier la configuration de la base de données

Le fichier `web/src/config/database.php` doit utiliser les bonnes variables :

```php
// Configuration correcte
$host = 'db';
$dbName = 'TechSuivi'; // Doit correspondre à MYSQL_DATABASE dans .env
$dbUser = 'techsuivi_user'; // Doit correspondre à MYSQL_USER dans .env
$dbPass = 'techsuivi_pass_2024'; // Doit correspondre à MYSQL_PASSWORD dans .env
```

### 2. Fichier .env requis

Créez un fichier `.env` à la racine avec :

```env
# Configuration de la base de données MariaDB
MYSQL_ROOT_PASSWORD=techsuivi_root_2024
MYSQL_DATABASE=TechSuivi
MYSQL_USER=techsuivi_user
MYSQL_PASSWORD=techsuivi_pass_2024

# Configuration FTP
FTP_USER=ftpuser
FTP_PASS=ftppass_2024
```

### 3. Redémarrer complètement Docker

```bash
# Arrêter et supprimer les volumes (ATTENTION : supprime les données)
docker-compose down -v

# Relancer
docker-compose up -d
```

### 4. Vérifier les tables

- PhpMyAdmin : http://localhost:8081
- Utilisateur : `techsuivi_user`
- Mot de passe : `techsuivi_pass_2024`
- Base : `TechSuivi`

### 5. Tables qui doivent être créées

- `liens` - Gestion des liens
- `inter` - Interventions
- `FC_*` - Tables fiche de caisse
- `autoit_*` - Tables AutoIT
- `intervention_photos` - Photos d'interventions
- `intervention_statuts_historique` - Historique des statuts

### 6. Script de diagnostic

Utilisez le script `test_db_connection.php` pour diagnostiquer :

```bash
docker-compose exec web php /var/www/html/../test_db_connection.php
```

## Fichiers importants

- `db/init_complete.sql` - Script d'initialisation complet
- `web/src/config/database.example.php` - Exemple de configuration
- `docker-compose.yml` - Configuration Docker

## Support

Si le problème persiste, vérifiez les logs Docker :

```bash
docker-compose logs db
docker-compose logs web