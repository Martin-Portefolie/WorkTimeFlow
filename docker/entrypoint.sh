#!/usr/bin/env sh
set -eu

APP_ENV="${APP_ENV:-prod}"

# Writable dirs
mkdir -p var/cache var/log var/sessions public/bundles
chown -R www-data:www-data var public/bundles || true
chmod -R ug+rwX var public/bundles var/cache var/log || true

# Wait for DB (parsed from DATABASE_URL)
if [ -n "${DATABASE_URL:-}" ]; then
  echo "Waiting for database…"
  php -r '
    $u = getenv("DATABASE_URL"); if (!$u) exit(0);
    $p = parse_url($u); if (!$p) exit(0);
    $db = ltrim($p["path"] ?? "", "/");
    parse_str(parse_url($u, PHP_URL_QUERY) ?? "", $q);
    $charset = $q["charset"] ?? "utf8mb4";
    $scheme = $p["scheme"] ?? "mysql";
    $host   = $p["host"]   ?? "localhost";
    $port   = $p["port"]   ?? 3306;
    $user   = $p["user"]   ?? null;
    $pass   = $p["pass"]   ?? null;
    $dsn = sprintf("%s:host=%s;port=%d;dbname=%s;charset=%s", $scheme, $host, $port, $db, $charset);
    for ($i=0; $i<30; $i++) {
      try { new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); echo "DB OK\n"; exit(0); }
      catch (Throwable $e) { echo "DB not ready: ".$e->getMessage()."\n"; sleep(2); }
    }
    exit(1);
  ' || { echo "Database not reachable, exiting"; exit 1; }
fi

# Safe: create DB & run migrations (no-op if already applied)
bin/console doctrine:database:create --if-not-exists || true
bin/console doctrine:migrations:migrate --no-interaction || true

# Cache & assets
bin/console cache:clear --env="$APP_ENV" || true
bin/console cache:warmup --env="$APP_ENV" || true
bin/console assets:install --symlink --relative public || bin/console assets:install public || true

# Build Tailwind CSS (required by base.html.twig)
NEED_TW=0
[ ! -f public/build/tailwind.css ] && NEED_TW=1
[ "${TAILWIND_ALWAYS_BUILD:-0}" = "1" ] && NEED_TW=1
if [ "$NEED_TW" = "1" ]; then
  echo "Building Tailwind CSS…"
  bin/console tailwind:build --minify --no-interaction
fi
if [ ! -f public/build/tailwind.css ]; then
  echo "ERROR: Tailwind CSS not generated at public/build/tailwind.css"
  exit 1
fi

echo "Starting: $*"
exec "$@"
