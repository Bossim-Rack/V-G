# Image de production de l'API Symfony (utilisée par Render)
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
RUN { echo 'variables_order = "EGPCS"'; echo 'expose_php = Off'; echo 'opcache.validate_timestamps = 0'; } > /usr/local/etc/php/conf.d/app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Dépendances d'abord (mises en cache par Docker tant que composer.lock ne change pas)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-scripts --no-autoloader

# Puis le code
COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p var \
    && chown -R www-data:www-data var

# Apache sert le dossier public/ de Symfony
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
