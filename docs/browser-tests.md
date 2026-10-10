# Browser tests

Panther runs real headless Chrome tests for Company, Clients and Users in Danish and English.
PHP needs `pdo_sqlite`, `curl` and `gd`; Google Chrome must be installed.

From the project directory (on your host machine):

```sh
composer install
vendor/bin/bdi detect drivers
php bin/console tailwind:build --env=test
php bin/console asset-map:compile --env=test
composer test:browser
```

Run `vendor/bin/bdi detect drivers` again after updating Chrome. Docker's PHP container
does not include Chrome; the commands above run with local PHP. CI installs the driver
and runs the same tests after building assets.

The browser suite has its own `phpunit.panther.xml`. The default PHPUnit suite excludes
`tests/Browser`, so it does not require a browser. A focused run:

```sh
php bin/phpunit -c phpunit.panther.xml --filter testCurrencyAndRateCrud
```

The bootstrap creates a unique SQLite database under `var/browser-tests/` and passes
its URL to Panther's local server on port 9080. Schema resets are guarded to only allow
that database. Your development database and `.env.local` are not modified.
Test logo uploads use random filenames and are removed during teardown.

Coverage includes company name and logo, sidebar updates, currency filtering without
amount conversion, rate creation/editing/deletion, inline client updates and validation,
new-client insertion/cancellation, project modals and dashboard navigation. A document
marker checks that saves and navigation do not reload the whole page.

Failure screenshots are saved in `var/panther-screenshots/` and uploaded as a CI artifact.
Stable selectors are documented in [testing-selectors.md](testing-selectors.md).

Users coverage includes inline name/email edits, duplicate-email rejection without partial
updates, creating and cancelling a draft, and checking that created accounts have ordinary user access. Service tests verify
that the generated password matches the stored hash and the welcome email context.
Browser tests use a null mail transport and an in-memory queue; no real email is sent.

Welcome emails use the existing asynchronous Messenger routing. In development, run
`docker compose exec app php bin/console messenger:consume async -vv` to process the
queue. Delivery uses the configured `MAILER_DSN`; local Mailpit captures email rather
than sending it to an external inbox.
