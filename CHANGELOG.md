# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Editable Projects GUI with inline creation, client/rate/team selection, estimates, payment/archive status, validation and Danish/English browser tests. Client project modals open the editable project panel.
- Editable user role and active status in the admin GUI, including creation, with server-side protection against self-deactivation and removing your own administrator role.
- Inline user name/email editing and user creation in the admin GUI, with duplicate-email validation, automatically generated initial passwords delivered through the existing welcome-email queue, and Danish/English browser tests.
- Panther browser tests for Company and Clients in Danish and English, with an isolated SQLite database and CI screenshots on failure.
- Stable browser-test selectors for Company and Clients, unique field IDs, linked Company labels and a selector reference for upcoming Panther tests.
- Create clients directly in the admin GUI using the same validated form as editing; successful creation opens the saved client without a page reload.
- Editable client details in the admin GUI with AJAX saving, Danish/English feedback and shared validation for GUI and terminal updates.
- Region and currency dropdowns on the Company dashboard, with translated currency names, instant region filtering and server-side validation.
- Danish and English currency translation catalogues from the supplied IBAN table, organized by region, country and currency for use in Twig.
- Company currency selector (DKK default, EUR, USD), saved for rates and future invoices without converting amounts.
- Inline rate creation, editing and deletion on the Company dashboard, with Danish/English feedback, decimal validation and protection against deleting rates used by projects.
- Admin Timelog list, show, add wizard, update and confirmed delete commands, with list/detail GUI views.
- Required CI checks for changelog updates, database migrations and site smoke tests before merging.
- Admin Todo list and detail commands with terminal and GUI integration.
- Translations en and da
- Clients in general was updated
- Pagination on client sites
- search feature

### Changed
- Upgrade to PHP 8.5 and Symfony 8.1 with refreshed Composer dependencies; CI uses PHP 8.5.12 and Docker builds reject older PHP security patches.
- Show project deadlines and overdue warnings directly in compact rows; use purple information buttons, amber editing accents and green save buttons.
- Show budget calculation results and missing-input feedback directly below the calculation button; cover rates of 30 with browser tests.
- Project time estimates use a 0–500 hour slider in five-hour steps, with live budget calculation and minute-based persistence; existing estimates remain unchanged until the slider is moved.
- Compact project rows with separate detail, budget/time, team and todo/log modals; include last update, deadline alerts, time totals, optional budget calculation and the existing Word download.
- Client rows show a clickable project count, opening a modal with active and archived projects grouped separately.
- Restore project counts and clickable project names in client rows, including after inline saves.
- Dashboard navigation loads the overview back into the admin panel and correctly updates the active sidebar item.
- Consistent compact save-status badges for company and clients; success messages dismiss automatically while errors remain visible.
- Client list supports editing directly in each row; creating a client inserts a subtly highlighted draft row at the top and saves in place.
- Reviewed currency catalogues against SIX ISO 4217: exclude nine fund codes from company currency choices and correct Norfolk Island to AUD.
- Currency dropdowns group currencies by their home region, so overseas usage does not put EUR or DKK under North America.
- Company sidebar and dashboard fall back to the default logo when the uploaded file is missing.
- Admin sidebar displays the company name and logo and updates immediately after saving, including name changes from the terminal.
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
