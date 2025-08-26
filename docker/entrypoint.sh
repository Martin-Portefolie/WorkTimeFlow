#!/usr/bin/env sh
set -eu

APP_ENV="${APP_ENV:-prod}"

echo "▶️  Entrypoint starting (APP_ENV=$APP_ENV)"

# ------------------------------------------------------------------------------
# Writable dirs & permissions
# ------------------------------------------------------------------------------
mkdir -p var/cache var/log var/sessions public/bundles
# chown may fail on some hosts (rootless/docker-for-mac) – ignore errors
chown -R www-data:www-data var public/bundles 2>/dev/null || true
chmod -R ug+rwX var public/bundles || true

# ------------------------------------------------------------------------------
# Optional: wait for DB if DATABASE_URL is set
# ------------------------------------------------------------------------------
if [ -n "${DATABASE_URL:-}" ]; then
  echo "⏳ Waiting for database…"
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
      try { new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); echo "✅ DB OK\n"; exit(0); }
      catch (Throwable $e) { echo "… DB not ready: ".$e->getMessage()."\n"; sleep(2); }
    }
    exit(1);
  ' || { echo "❌ Database not reachable, exiting"; exit 1; }
fi

# ------------------------------------------------------------------------------
# Database schema/migrations (no-op when up-to-date)
# ------------------------------------------------------------------------------
bin/console doctrine:database:create --if-not-exists --no-interaction || true
bin/console doctrine:migrations:migrate --no-interaction || true

# ------------------------------------------------------------------------------
# Cache & framework assets
# ------------------------------------------------------------------------------
bin/console cache:clear --env="$APP_ENV" || true
bin/console cache:warmup --env="$APP_ENV" || true
bin/console assets:install --symlink --relative public || bin/console assets:install public || true

# ------------------------------------------------------------------------------
# ImportMap vendor assets (Stimulus/Turbo, etc.) – required before asset-map:compile
# ------------------------------------------------------------------------------
echo "📦 Installing ImportMap vendor assets…"
bin/console importmap:install --no-interaction || echo "⚠️  WARN: importmap:install failed (continuing)"

# ------------------------------------------------------------------------------
# Tailwind CSS (one-shot, prod)
# ------------------------------------------------------------------------------
echo "🎨 Building Tailwind CSS (env=$APP_ENV)…"
bin/console tailwind:build --minify || echo "⚠️  WARN: Tailwind build failed (continuing)"

# ------------------------------------------------------------------------------
# AssetMapper compile (writes public/assets + manifest.json)
# ------------------------------------------------------------------------------
echo "🧰 Compiling AssetMapper…"
bin/console asset-map:compile || echo "⚠️  WARN: asset-map:compile failed (continuing)"

# ------------------------------------------------------------------------------
# Sanity checks
# ------------------------------------------------------------------------------
if [ -f public/assets/manifest.json ]; then
  echo "✅ Manifest present at public/assets/manifest.json"
  if ls public/assets/styles/app*.css >/dev/null 2>&1; then
    echo "✅ Tailwind output: $(ls -1 public/assets/styles/app*.css | head -n1)"
  else
    echo "⚠️  WARN: no Tailwind CSS output under public/assets/styles"
  fi
else
  echo "⚠️  WARN: public/assets/manifest.json missing"
fi

echo "🚀 Starting main process: $*"
exec "$@"
