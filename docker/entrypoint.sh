#!/usr/bin/env sh
set -eu

APP_ENV="${APP_ENV:-prod}"

# Writable dirs
mkdir -p var/cache var/log var/sessions public/bundles public/build
chown -R www-data:www-data var public/bundles public/build || true
chmod -R ug+rwX var public/bundles public/build || true

# Optional: wait for DB using parsed DATABASE_URL
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
    $dsn = sprintf("%s:host=%s;port=%d;dbname=%s;charset=%s",$scheme,$host,$port,$db,$charset);
    for($i=0;$i<30;$i++){
      try{ new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); echo "DB OK\n"; exit(0);}
      catch(Throwable $e){ echo "DB not ready: ".$e->getMessage()."\n"; sleep(2); }
    }
    exit(1);
  ' || { echo "Database not reachable, exiting"; exit 1; }
fi

# DB setup (no-op if already done)
bin/console doctrine:database:create --if-not-exists || true
bin/console doctrine:migrations:migrate --no-interaction || true

# Cache & assets
bin/console cache:clear --env="$APP_ENV" || true
bin/console cache:warmup --env="$APP_ENV" || true
bin/console assets:install --symlink --relative public || bin/console assets:install public || true

# Build Tailwind to match base.html.twig
echo "Building Tailwind CSS…"
bin/console tailwind:build || true

# Verify the expected output (do NOT crash the container if missing)
if [ -f public/styles/app.css ]; then
  echo "Tailwind CSS ready: public/styles/app.css"
else
  echo "WARN: Tailwind CSS not generated at public/styles/app.css"
fi

echo "Starting: $*"
exec "$@"
