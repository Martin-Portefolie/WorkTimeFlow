# --- Dockerfile (prod-ready) ---
FROM dunglas/frankenphp:latest

WORKDIR /app

# PHP extensions
RUN install-php-extensions intl zip pdo_mysql opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dev-friendly Composer cache path (safe for builds too)
ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    APP_ENV=prod

# 1) deps first (no scripts) for better layer caching
COPY composer.json composer.lock symfony.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

# 2) now add the app and run scripts
COPY . /app
COPY Caddyfile /etc/frankenphp/Caddyfile

RUN composer dump-env prod \
 && composer install --no-dev --no-interaction --no-progress --prefer-dist \
 && php bin/console importmap:install --no-interaction \
 && php bin/console asset-map:compile || true \
 && php bin/console tailwind:build   || true \
 && php bin/console cache:clear --env=prod || true \
 && chown -R www-data:www-data var

EXPOSE 80
