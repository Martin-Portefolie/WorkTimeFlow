#!/bin/sh
set -e

# ------- dev-friendly env for Composer (avoids / root-owned cache) -------
: "${HOME:=/tmp}"
: "${COMPOSER_HOME:=$HOME/.composer}"
: "${COMPOSER_CACHE_DIR:=$COMPOSER_HOME/cache}"
mkdir -p "$COMPOSER_CACHE_DIR" || true

# ------- ensure Symfony dirs exist & are writable -------
mkdir -p /app/var/cache /app/var/log || true

# Try to make var/ writable for the current user (works with bind mounts)
# chown may fail if not root; that's fine—we ignore errors.
chown -R "$(id -u)":"(id -g)" /app/var 2>/dev/null || true
chmod -R u+rwX,go+rX /app/var 2>/dev/null || true

# ------- Tailwind: make downloaded binary executable if present -------
if [ -d /app/var/tailwind ]; then
  # v3.4.17 path looks like /app/var/tailwind/vX.Y.Z/tailwindcss-linux-*
  find /app/var/tailwind -type f -name 'tailwindcss-*' -exec chmod +x {} + 2>/dev/null || true
fi

# ------- hand over to FrankenPHP (Caddy) -------
exec frankenphp php-server
