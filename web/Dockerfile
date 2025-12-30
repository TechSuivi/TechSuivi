FROM php:8.2-apache

# Installer les dépendances nécessaires pour ZIP, GD et Composer
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    unzip \
    git \
    iputils-ping \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installer Docker CLI
COPY --from=docker:dind /usr/local/bin/docker /usr/local/bin/

# Configurer et installer les extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip gd

# Configuration PHP personnalisée pour les uploads (2GB)
RUN echo 'file_uploads = On\n\
    memory_limit = 512M\n\
    upload_max_filesize = 512M\n\
    post_max_size = 512M\n\
    max_execution_time = 600\n\
    max_input_time = 600' > /usr/local/etc/php/conf.d/uploads.ini

# Activer mod_rewrite
RUN a2enmod rewrite

# Configuration Apache pour autoriser le listing de répétoires (pour /Download/Install/)
# et les .htaccess. On utilise un fichier séparé pour ne pas écraser la conf PHP par défaut.
RUN echo '<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>' > /etc/apache2/conf-available/techsuivi.conf \
    && a2enconf techsuivi

# Copier le script d'initialisation PHPMailer
COPY init-phpmailer.sh /usr/local/bin/init-phpmailer.sh
RUN chmod +x /usr/local/bin/init-phpmailer.sh

# Copier tout le code de web/src/ dans /var/www/html
COPY src/ /var/www/html/

WORKDIR /var/www/html

# Créer les dossiers nécessaires avec les bonnes permissions
RUN mkdir -p /var/www/html/uploads/backups \
    && mkdir -p /var/www/html/uploads/documents \
    && mkdir -p /var/www/html/uploads/temp \
    && mkdir -p /var/www/html/uploads/interventions \
    && mkdir -p /var/www/html/uploads/autoit/logiciels \
    && mkdir -p /var/www/html/uploads/autoit/nettoyage \
    && mkdir -p /var/www/html/uploads/stock \
    && mkdir -p /var/www/html/uploads/downloads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads \
    && groupadd -f docker \
    && usermod -aG docker www-data

# Script de démarrage personnalisé
RUN echo '#!/bin/bash\n\
    \n\
    # Adaptation dynamique du GID du groupe docker au socket monté\n\
    SOCKET="/var/run/docker.sock"\n\
    if [ -e "$SOCKET" ]; then\n\
    GID=$(stat -c "%g" "$SOCKET")\n\
    echo "Socket Docker trouvé avec GID $GID. Mise à jour du groupe docker..."\n\
    groupmod -g $GID docker || true\n\
    usermod -aG docker www-data || true\n\
    fi\n\
    \n\
    # Initialiser PHPMailer au démarrage\n\
    /usr/local/bin/init-phpmailer.sh\n\
    \n\
    # Exporter les variables d\'environnement pour que le CRON puisse y accéder (DB_HOST, etc.)\n\
    printenv | grep -v "no_proxy" > /etc/environment\n\
    \n\
    # Créer le fichier de log et donner les droits\n\
    touch /var/www/html/cron/advanced_cron.log\n\
    chown www-data:www-data /var/www/html/cron/advanced_cron.log\n\
    \n\
    # Configurer le cron TechSuivi (Chargement de /etc/environment avant PHP)\n\
    echo "*/5 * * * * . /etc/environment; /usr/local/bin/php /var/www/html/cron/advanced_scheduled_tasks.php >> /var/www/html/cron/advanced_cron.log 2>&1" | crontab -\n\
    \n\
    # Démarrer le service cron\n\
    cron\n\
    \n\
    # Initialiser l\'installeur (Renommage HEX + DB)\n\
    php /var/www/html/install/init_installer.php\n\
    \n\
    # Démarrer Apache\n\
    exec apache2-foreground' > /usr/local/bin/start-techsuivi.sh \
    && chmod +x /usr/local/bin/start-techsuivi.sh

CMD ["/usr/local/bin/start-techsuivi.sh"]
