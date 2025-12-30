FROM mariadb:10.11

# Copier le script SQL dans le dossier d'initialisation automatique de MariaDB
COPY techsuivi_db.sql /docker-entrypoint-initdb.d/techsuivi_db.sql

# Permissions pour s'assurer que MariaDB peut lire le fichier
RUN chmod 644 /docker-entrypoint-initdb.d/techsuivi_db.sql
