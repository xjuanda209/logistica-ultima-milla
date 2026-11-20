# Imagen de producción del backend (Render, plan gratuito: 512 MB de RAM).
# Servidor: nginx + php-fpm en el mismo contenedor; nginx escucha en $PORT (lo da Render).
# Se construye desde la carpeta backend/:  docker build -t logistica-api .

FROM composer:2 AS composer

FROM php:8.5-fpm-bookworm

# Exige PHP 8.5.12 o mayor (la etiqueta 8.5 siempre trae el último parche de 8.5).
RUN php -r 'if (version_compare(PHP_VERSION, "8.5.12", "<")) { fwrite(STDERR, "Se requiere PHP >= 8.5.12, hay ".PHP_VERSION."\n"); exit(1); }'

# Paquetes del sistema y extensiones de PHP. mbstring y fileinfo vienen en la imagen oficial;
# se comprueban al final junto con las que se compilan aquí.
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx libpq-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip \
    && apt-get purge -y --auto-remove libpq-dev libzip-dev \
    && apt-get install -y --no-install-recommends libpq5 libzip4 \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default \
    && php -r 'foreach (["pdo_pgsql","pgsql","mbstring","zip","fileinfo"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "Falta la extensión $e\n"); exit(1); } }'

# Configuración de PHP, php-fpm y nginx.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-produccion.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-produccion.conf
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf.plantilla
COPY docker/iniciar.sh /usr/local/bin/iniciar
RUN chmod +x /usr/local/bin/iniciar

WORKDIR /var/www/html

# Dependencias primero (capa en caché mientras composer.lock no cambie).
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

# Código de la aplicación y autoloader optimizado.
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=8080

EXPOSE 8080

CMD ["iniciar"]
