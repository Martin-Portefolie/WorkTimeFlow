# syntax=docker/dockerfile:1
FROM dunglas/frankenphp:latest

WORKDIR /app

RUN install-php-extensions intl zip pdo_mysql opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Layered deps install
COPY composer.json composer.lock symfony.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

# App code
COPY . /app
COPY Caddyfile /etc/frankenphp/Caddyfile

# Optimize autoloader, prep writable dirs (no bin/console here!)
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
 && mkdir -p var/cache var/log \
 && chown -R www-data:www-data var

# Entry point runs migrations/cache/assets at container start
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["frankenphp","run","--config","/etc/frankenphp/Caddyfile"]
