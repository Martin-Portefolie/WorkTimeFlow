# Local Setup

This guide explains how to run WorkTimeFlow locally for development.
The recommended setup is [Docker Desktop](https://www.docker.com/products/docker-desktop/) together with [Docker Compose](https://docs.docker.com/compose/).
Optional tools that can make development easier depending on your workflow are [Git](https://git-scm.com/downloads), [Symfony CLI](https://symfony.com/download), [Composer](https://getcomposer.org/download/), [PHP](https://www.php.net/downloads.php), [TablePlus](https://tableplus.com/) and [DBeaver](https://dbeaver.io/).
If you plan to run WorkTimeFlow without Docker, make sure your environment meets the [Symfony Requirements](https://symfony.com/doc/current/setup.html).


### The documentation primarily uses Docker commands.

The container name may vary depending on your `compose.yaml` configuration. Throughout the documentation, `app` refers to the main Symfony application container.

If your project uses a different container name, replace `app` with the appropriate container name from your Docker configuration.

If you are running WorkTimeFlow without Docker, simply use the native Symfony, Composer, or PHP commands instead.

---

### Initial Setup

```sh
# Clone repository
git clone https://github.com/Martin-Portefolie/WorkTimeFlow.git

# Enter project directory
cd WorkTimeFlow
```
### Start the project.

```sh
# Build and start containers
docker compose up -d --build

# Install dependencies
docker compose exec app composer install

# Run database migrations
docker compose exec app bin/console doctrine:migrations:migrate

# Build frontend assets
docker compose exec app bin/console tailwind:build
docker compose exec app bin/console asset-map:compile

# Clear cache
docker compose exec app bin/console cache:clear

# Optional load Data fixtures 
# user:a@a.com
# password:admin123
docker compose exec app bin/console doctrine:fixtures:load

```
---

### Daily Development

```sh
# Start containers
docker compose up -d

# Watch Tailwind changes
docker compose exec app bin/console tailwind:build --watch

# Clear cache
docker compose exec app bin/console cache:clear
```

---

### Asset Troubleshooting

```sh
# Remove generated AssetMapper files only
docker compose exec app sh -lc 'rm -rf public/assets/*'

# Rebuild Tailwind
docker compose exec app bin/console tailwind:build

# Rebuild AssetMapper
docker compose exec app bin/console asset-map:compile

# Clear Symfony cache
docker compose exec app bin/console cache:clear
```

Never delete:

```text
public/index.php
public/bundles/
```

Only remove:

```text
public/assets/*
```

WorkTimeFlow uses Symfony AssetMapper. Rebuilding assets should only affect files inside `public/assets/`.

---

### Application URLs

```text
Home:
http://localhost:8081/en/

Login:
http://localhost:8081/en/login

Admin:
http://localhost:8081/en/admin/

Profile:
http://localhost:8081/en/profile/
```

---

### Database Commands

```sh
# Run migrations
docker compose exec app bin/console doctrine:migrations:migrate

# Validate Doctrine mapping
docker compose exec app bin/console doctrine:schema:validate

# Reset database
docker compose exec app bin/console doctrine:schema:drop --full-database --force
docker compose exec app bin/console doctrine:migrations:migrate

# Optional Load fixtures
docker compose exec app bin/console doctrine:fixtures:load
```

---

### Useful Development Commands

```sh
# Show all routes
docker compose exec app bin/console debug:router

# Match a specific route
docker compose exec app bin/console router:match /en/admin/

# Docker logs
docker compose logs app --tail=100

# Symfony development log
docker compose exec app tail -n 100 var/log/dev.log

# Check Symfony front controller
docker compose exec app ls -la public/index.php

# Check generated assets
docker compose exec app ls -la public/assets

# Check container status
docker compose ps
```

---

### Git Identity

If Git asks for your identity:

```sh
# Configure Git identity
git config --global user.name "Your Name"
git config --global user.email "your@email.com"
```

If you already committed with the wrong author:

```sh
# Reset author on latest commit
git commit --amend --reset-author
```
