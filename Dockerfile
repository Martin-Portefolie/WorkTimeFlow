FROM dunglas/frankenphp:latest
WORKDIR /app

RUN install-php-extensions intl zip pdo_mysql opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 1) deps layer
COPY composer.json composer.lock symfony.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

# 2) app code
COPY . /app
COPY Caddyfile /etc/frankenphp/Caddyfile

# 3) optimize autoload, but STILL skip scripts here
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts \
 && mkdir -p var/cache var/log \
 && chown -R www-data:www-data var

# 4) entrypoint (handles cache/migrations/assets at container start)
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint
ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["frankenphp","run","--config","/etc/frankenphp/Caddyfile"]
