# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Admin Timelog list, show, add wizard, update and confirmed delete commands, with list/detail GUI views.
- Required CI checks for changelog updates, database migrations and site smoke tests before merging.
- Admin Todo list and detail commands with terminal and GUI integration.
- Translations en and da
- Clients in general was updated
- Pagination on client sites
- search feature

### Changed
- Time registration now checks project access and keeps each user's daily and weekly logs separate.

- Design


### Removed

## [Alpha 0.1]

### TODO
- reduce the admin section to a singlepage
- reduce the timeregistration section to a singlepage
  - timeregistration and todo should be cl/ci experince.

### Added
- docker-compose.prod.yml with traefik labels, and server setup
- frankenPhp, and Caddy

### Changed
- updated to symfony 7.3, and updated recipes.
- redesigned 


### Removed
- nginx
