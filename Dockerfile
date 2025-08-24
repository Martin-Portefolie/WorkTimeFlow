FROM dunglas/frankenphp:latest

WORKDIR /app

# PHP extensions you need
RUN install-php-extensions intl zip pdo_mysql opcache

# Composer in the image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Put Composer’s home/cache in a writable location
ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache

# Caddy/FrankenPHP config
COPY Caddyfile /etc/frankenphp/Caddyfile

EXPOSE 80
