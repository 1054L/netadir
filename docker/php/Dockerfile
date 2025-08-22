# syntax=docker/dockerfile:1.7
FROM php:8.3-fpm-alpine

# Paquetes útiles y extensiones PHP necesarias para Symfony
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev git unzip bash \
 && docker-php-ext-install intl opcache pdo pdo_mysql zip

# Opcache recomendado en dev (validación activa para que recargue al guardar)
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 \
    PHP_OPCACHE_REVALIDATE_FREQ=0

WORKDIR /var/www/html

# Usuario no-root (mejor para bind mounts en Linux/WSL)
RUN addgroup -g 1000 app && adduser -D -G app -u 1000 app
USER app

# El código lo montará docker compose como volumen
CMD ["php-fpm","-F"]
