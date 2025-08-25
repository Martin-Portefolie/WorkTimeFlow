#!/bin/sh
set -e

APP_ENV="${APP_ENV:-prod}"

# Writable dirs
mkdir -p var/log var/cache var/sessions public/bundles
chown -R www-data:www-data var public/bundles || true
chmod -R ug+rwX var public/bundles var/cache var/log || true

# If a compiled env sneaks in, ignore it
[ -f .env.local.php ] && rm -f .env.local.php || true

# DB: create & migrate (only if DATABASE_URL is set)
if [ -n "${DATABASE_URL:-}" ]; then
  php -r 'try{new PDO(getenv("DATABASE_URL")); echo "DB OK\n";}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n"); exit(1);}'
  bin/console doctrine:database:create --if-not-exists -n || true
  bin/console doctrine:migrations:migrate -n || true
fi

# Cache
bin/console cache:clear --env="$APP_ENV" || true
bin/console cache:warmup --env="$APP_ENV" || true

# Assets
bin/console assets:install --symlink --relative public || bin/console assets:install public || true
bin/console asset-map:compile || true
bin/console importmap:install --no-interaction || true
bin/console tailwind:build || true

exec "$@"
