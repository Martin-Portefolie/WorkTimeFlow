# Hest Test Calender
A free symfony time-registration project.

## config
Make sure to check [symfony requirements](https://symfony.com/doc/current/setup.html) , and install and start [docker desktop](https://www.docker.com/products/docker-desktop/).

## server setup
## server docker setup
## local setup
## local docker setup 
Getting started:  


### First time build
```shell
# 1. (once) start docker
docker-compose up  -d #only build once
# 2(one-time-required) install dependencies
docker compose exec php composer install
# 3(one-time-required) migrate database.
docker compose exec php bin/console doctrine:migrations:migrate
# 4 (always) initiate tailwind
docker compose exec php bin/console tailwind:build --watch --poll
```

###  start
```shell
# 1.  start docker
docker-compose up  -d 
# 2 (always) initiate tailwind
docker compose exec php bin/console tailwind:build --watch --poll

```

### Test
Before a pull request, make sure that your code passes the:
twig-lint,
php-cs-fixer, 
and unit tests

```shell
docker compose exec php bin/console lint:twig templates/
docker compose exec php ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

### helpful commands
```shell

## to miggrate new data to database
docker compose exec php bin/console doctrine:migration:migrate
## to initiate 

    docker compose exec php bin/console doctrine:schema:drop --full-database --force; 
    docker compose exec php bin/console doctrine:migration:migrate
    docker compose exec php bin/console create-user
    docker compose exec php bin/console create-client
    docker compose exec php bin/console create-project
    docker compose exec php bin/console create-team "Pegasus Team" a@a.com b@b.com --projectName="Project Pegasus"
    docker compose exec php bin/console create-todo 1 "Storyboard Development"  "2025-02-20" "2025-02-22"
    docker compose exec php bin/console create-timelog "admin" 1 2 30 "2024-11-22" "Completed the storyboard initial draft"
    docker compose exec php bin/console create-timelog "admin" 1 1 30 "2024-11-20" "Completed the storyboard initial draft 2"
    docker compose exec php bin/console create-timelog "admin" 1 1 30 "2025-02-20" "Completed the storyboard initial draft 3"
```

```sh
## To initiate symfony messenger
docker compose exec php bin/console messenger:consume async -vv
```

### helpful tips
- syntax when install is always= docker compose exec php composer <command> <command>
- syntax when running command in docker is always dockker compose exec php bin/console <command> <command>

