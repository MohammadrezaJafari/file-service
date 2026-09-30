# Laravel API + Filament admin, served by nginx + php-fpm (serversideup image)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM serversideup/php:8.4-fpm-nginx
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    PHP_UPLOAD_MAX_FILE_SIZE=2G \
    PHP_POST_MAX_SIZE=2G \
    NGINX_CLIENT_MAX_BODY_SIZE=2G
USER root
RUN install-php-extensions zip gd intl
USER www-data
WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=vendor /app /var/www/html
