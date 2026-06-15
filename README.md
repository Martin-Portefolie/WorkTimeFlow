# WorkTimeFlow

WorkTimeFlow is a free and open-source time registration and project management platform released under the MIT License.

The project is designed with simplicity, fast deployment, speed, and maintainability as its primary goals. It is intentionally built to be easy to run on a VPS using Docker with minimal setup and configuration.

WorkTimeFlow is built around a terminal-first workflow with a complementary GUI.

The long-term vision is to create a lightweight workspace experience where users can work through either:

- Terminal
- GUI

without duplicating logic or maintaining separate workflows.

The documentation follows a developer-first approach. The goal is to make it easy for developers to understand the codebase, perform maintenance, and apply hotfixes quickly.

---

# Requirements

WorkTimeFlow can be run either locally or on a server.

## Local Development

The recommended setup is [Docker Desktop](https://www.docker.com/products/docker-desktop/) together with Docker Compose.

Optional tools that can make development easier:

- [Git](https://git-scm.com/downloads)
- [Symfony CLI](https://symfony.com/download)
- [Composer](https://getcomposer.org/download/)
- [PHP](https://www.php.net/downloads.php)
- [TablePlus](https://tableplus.com/)
- [DBeaver](https://dbeaver.io/)

See:

- [Local Setup](documentation/setup/local-setup.md)

---

## Server Deployment

The recommended server setup is a Linux VPS running Docker Engine and Docker Compose.

See:

- [Server Setup](documentation/setup/server.md)

---

## Symfony Requirements

If you plan to run WorkTimeFlow without Docker, verify that your environment meets the Symfony requirements:

- https://symfony.com/doc/current/setup.html

---

# Documentation

## New Developer

Start here:

- [Local Setup](documentation/setup/local-setup.md)
- [Server Setup](documentation/setup/server.md)

---

## Troubleshooting

Something broken?

- [Quick Hotfix](documentation/quick-hotfix/README.md)

---

## Application Areas

### Admin

Administrative workspace.

- [Admin Documentation](documentation/admin/README.md)

### Profile

User workspace.

- [Profile Documentation](documentation/profile/README.md)

---

## Shared Symfony Logic

Controllers, Services, Forms, Entities and Repositories.

- [Backend Documentation](documentation/backend/README.md)

---

## Project Direction

Project vision, architecture, design decisions and long-term roadmap.

- [Architecture Documentation](documentation/architecture/README.md)
