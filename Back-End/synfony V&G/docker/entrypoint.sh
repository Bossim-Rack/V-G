#!/bin/sh
# Lancé à chaque démarrage du conteneur sur Render
set -e

# Render impose le port d'écoute via la variable PORT
PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Certificat de la base MySQL (Aiven), fourni dans la variable MYSQL_CA_CERT
if [ -n "$MYSQL_CA_CERT" ]; then
    printf '%s\n' "$MYSQL_CA_CERT" > /etc/ssl/certs/mysql-ca.pem
    export MYSQL_SSL_CA=/etc/ssl/certs/mysql-ca.pem
fi

# Diagnostic : affiche à quelle base MySQL on se connecte (sans le mot de passe)
php -r '$u = parse_url(getenv("DATABASE_URL") ?: ""); echo "Base MySQL utilisée : ", ($u["host"] ?? "DATABASE_URL NON DÉFINIE sur Render"), ":", ($u["port"] ?? "?"), PHP_EOL;'

# Cache de production
php bin/console cache:clear --no-interaction

# Base de données : tables + rôles + admin si la base est vide, puis nouvelles migrations
php bin/console app:init-database --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Statistiques MongoDB (ne bloque pas le démarrage si MongoDB est indisponible)
php bin/console app:stats:sync --no-interaction || echo "MongoDB indisponible : statistiques non synchronisées"

# Fichiers de la doc API (Swagger)
php bin/console assets:install public --no-interaction

chown -R www-data:www-data var

exec apache2-foreground
