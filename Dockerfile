# Image de production de l'API Symfony (utilisée par Render)
# Placé à la racine du dépôt car Render n'accepte pas d'espace ni de "&" dans "Root Directory" :
# c'est ce fichier qui va chercher le code dans "Back-End/synfony V&G".
FROM php:8.3-apache

# Extensions PHP : MySQL (pdo_mysql), MongoDB (statistiques), intl, zip, opcache
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libssl-dev ca-certificates \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Les variables d'environnement de Render doivent être visibles par Symfony ($_ENV)
RUN { echo 'variables_order = "EGPCS"'; echo 'expose_php = Off'; echo 'opcache.validate_timestamps = 0'; echo 'date.timezone = Europe/Paris'; } > /usr/local/etc/php/conf.d/app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Dépendances d'abord (mises en cache par Docker tant que composer.lock ne change pas)
COPY ["Back-End/synfony V&G/composer.json", "Back-End/synfony V&G/composer.lock", "Back-End/synfony V&G/symfony.lock", "./"]
RUN composer install --prefer-dist --no-interaction --no-scripts --no-autoloader

# Puis le code de l'API
COPY ["Back-End/synfony V&G/", "./"]
RUN composer dump-autoload --optimize \
    && mkdir -p var \
    && chown -R www-data:www-data var

# Apache sert le dossier public/ de Symfony
COPY ["Back-End/synfony V&G/docker/apache.conf", "/etc/apache2/sites-available/000-default.conf"]
COPY ["Back-End/synfony V&G/docker/entrypoint.sh", "/usr/local/bin/entrypoint.sh"]
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
