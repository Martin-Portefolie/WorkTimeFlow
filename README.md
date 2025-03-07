# Hest Test Calendar

A free Symfony-based time-registration project designed to simplify tracking time logs and project management.

## Prerequisites
Before setting up the project, ensure you have the following installed:
- Check [Symfony requirements](https://symfony.com/doc/current/setup.html)
- Install and start [Docker Desktop](https://www.docker.com/products/docker-desktop/) if using Docker
- PHP, Composer, and Symfony CLI (if running without Docker)

## Local Development Setup

### Setup Without Docker
```sh
# 1. Clone the repository
git clone https://github.com/Martin-Portefolie/hest-test-calender.git
cd hest-test-calender

# 2. Install dependencies
composer install

# 3. Configure the environment
cp .env .env.local  # Modify DB connection if needed

# 4. Set up the database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Start the development server
symfony server:start

# 6. Build Tailwind CSS
php bin/console tailwind:build --watch --poll
```

### Setup With Docker
```sh
# 1. Start Docker and build the containers
docker-compose up --build -d #(only on install)
docker-compose up  -d #(if already installed)

# 2. Install dependencies
docker-compose exec php composer install  #(only on install)

# 3. Migrate the database
docker-compose exec php bin/console doctrine:migrations:migrate  #(only on install)

# 4. Start Tailwind CSS compilation
docker-compose exec php bin/console tailwind:build --watch --poll
```

## Production Setup

### Deploying to a Production Server
```sh
# 1. Clone the repository
git clone https://github.com/Martin-Portefolie/hest-test-calender
cd hest-test-calender

# 2. Install dependencies in production mode
composer install --no-dev --optimize-autoloader

# 3. Set environment variables
cp .env .env.local
# Modify DB connection, APP_ENV=prod

# 4. Clear cache
php bin/console cache:clear --env=prod --no-debug

# 5. Migrate database
php bin/console doctrine:migrations:migrate --no-interaction

# 6. Set proper file permissions
chmod -R 775 var/
```

## Running Tests
Before making a pull request, ensure the code passes all tests:
```sh
# Lint Twig templates
docker-compose exec php bin/console lint:twig templates/

# Run PHP-CS-Fixer in dry-run mode
docker-compose exec php ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

## Helpful Commands

### Database Management
```sh
# Migrate new changes to the database
docker-compose exec php bin/console doctrine:migrations:migrate

# Reset and reinitialize the database
docker-compose exec php bin/console doctrine:schema:drop --full-database --force
docker-compose exec php bin/console doctrine:migrations:migrate

# Load test data
docker-compose exec php bin/console doctrine:fixtures:load
```

### User and Project Management
```sh
# Create a new user
docker-compose exec php bin/console create-user

# Create a new client
docker-compose exec php bin/console create-client

# Create a new project
docker-compose exec php bin/console create-project

# Create a team
docker-compose exec php bin/console create-team "Pegasus Team" a@a.com b@b.com --projectName="Project Pegasus"
```

### Task and Time Log Management
```sh
# Create a new task
docker-compose exec php bin/console create-todo 1 "Storyboard Development"  "2025-02-20" "2025-02-22"

# Log time entries
docker-compose exec php bin/console create-timelog "admin" 1 2 30 "2024-11-22" "Completed the storyboard initial draft"
docker-compose exec php bin/console create-timelog "admin" 1 1 30 "2024-11-20" "Completed the storyboard initial draft 2"
docker-compose exec php bin/console create-timelog "admin" 1 1 30 "2025-02-20" "Completed the storyboard initial draft 3"
```

### Running Symfony Messenger
```sh
docker-compose exec php bin/console messenger:consume async -vv
```

## Additional Notes
- Commands inside Docker: `docker-compose exec php bin/console <command>`
- Install dependencies in Docker: `docker-compose exec php composer <command>`
- Access the application at: [http://localhost:8080/en/](http://localhost:8080/en/)

