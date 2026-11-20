#!/bin/sh
# Arranque del contenedor en Render. Solo AGREGA a la base: nunca fresh, reset, refresh, rollback ni wipe.
set -e
cd /var/www/html

php artisan config:cache
php artisan storage:link --force
php artisan migrate --force

# Datos ficticios de demostración: solo con SEMBRAR_DEMO=true. Los seeders son idempotentes.
if [ "$SEMBRAR_DEMO" = "true" ]; then
    php artisan db:seed --force
    php artisan db:seed --class=DemoPedidosSeeder --force
    php artisan db:seed --class=DemoOperacionSeeder --force
fi

# Lo que artisan haya creado como root debe poder escribirlo php-fpm (www-data).
chown -R www-data:www-data storage bootstrap/cache

sed "s/__PORT__/${PORT:-8080}/g" /etc/nginx/conf.d/default.conf.plantilla > /etc/nginx/conf.d/default.conf

php-fpm -D
exec nginx -g 'daemon off;'
