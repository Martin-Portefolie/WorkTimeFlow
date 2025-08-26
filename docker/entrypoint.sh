#!/usr/bin/env sh
set -eu

APP_ENV="${APP_ENV:-prod}"

# Writable dirs & perms
mkdir -p var/cache var/log var/sessions public/bundles
chown -R www-data:www-data var public/bundles || true
chmod -R ug+rwX var public/bundles || true

# Wait for DB if configured
if [ -n "${DATABASE_URL:-}" ]; then
  echo "Waiting for database…"
  php -r '
    $u = getenv("DATABASE_URL"); if(!$u) exit(0);
    $p = parse_url($u); if(!$p) exit(0);
    $db = ltrim($p["path"] ?? "", "/");
    parse_str(parse_url($u, PHP_URL_QUERY) ?? "", $q);
    $charset = $q["charset"] ?? "utf8mb4";
    $scheme  = $p["scheme"] ?? "mysql";
    $host    = $p["host"]   ?? "localhost";
    $port    = $p["port"]   ?? 3306;
    $user    = $p["user"]   ?? null;
    $pass    = $p["pass"]   ?? null;
    $dsn = sprintf("%s:host=%s;port=%d;dbname=%s;charset=%s", $scheme, $host, $port, $db, $charset);
    for ($i=0; $i<30; $i++) {
      try { new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); echo "DB OK\n"; exit(0); }
      catch (Throwable $e) { echo "DB not ready: ".$e->getMessage()."\n"; sleep(2); }
    }
    exit(1);
  ' || { echo "Database not reachable, exiting"; exit 1; }
fi

# DB schema (no-op when up-to-date)
bin/console doctrine:database:create --if-not-exists --no-interaction || true
bin/console doctrine:migrations:migrate --no-interaction || true

# Cache & framework assets
bin/console cache:clear --env="$APP_ENV" || true
bin/console cache:warmup --env="$APP_ENV" || true
bin/console assets:install --symlink --relative public || bin/console assets:install public || true

# Ensure importmap vendor assets exist (Stimulus/Turbo, etc.)
echo "Installing Importmap vendor assets…"
bin/console importmap:install --prefer-offline || echo "WARN: importmap:install failed (continuing)"

# Tailwind build (prod, one-shot)
echo "Building Tailwind CSS (env=$APP_ENV)…"
bin/console tailwind:build --minify || echo "WARN: Tailwind build failed (continuing)"

# AssetMapper build
echo "Compiling AssetMapper…"
bin/console asset-map:compile || echo "WARN: asset-map:compile failed (continuing)"

# Sanity check for the CSS output (either our configured path or the default)
if [ -f public/styles/app.css ]; then
  echo "Tailwind output: public/styles/app.css"
elif [ -f public/build/tailwind.css ]; then
  echo "Tailwind output: public/build/tailwind.css"
else
  echo "WARN: No Tailwind CSS output found."
fi

echo "Starting: $*"
exec "$@"
