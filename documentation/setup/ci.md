# Continuous integration

`.github/workflows/ci.yml` runs on pushes and pull requests targeting `alpha-1`
or `main`. It checks the code on the branch being built; the old workflows on
`main` will only be replaced when the updated branch is merged there.

The job uses PHP 8.4, Composer 2 and MariaDB 11.4. It validates Composer files,
PHP syntax, Symfony configuration, Twig templates, database migrations and PHPUnit
tests. An empty test suite fails the build. It also builds ImportMap and Tailwind
assets before rendering pages in the tests.

The `Changelog updated` job requires each pull request to add a nonempty bullet
under `## [Unreleased]` in `CHANGELOG.md`, compared with the merge base. Changing
only older release notes or whitespace is insufficient. Push and manual runs do
not enforce a PR-specific changelog diff.

The site tests render Danish and English home/login pages, submit the login form,
open the admin page and execute Todo list/show against the migrated test database.
They also verify that an anonymous visitor cannot execute admin commands. These
are Symfony HTTP tests; they do not execute browser JavaScript or validate every
application feature.

Branch protection should require both `Changelog updated` and
`Symfony and tests (PHP 8.4)` on `alpha-1` and `main`, including administrators.
Branches must be current with the target before merging. Changes go through pull
requests; a separate approving reviewer is not required for this solo workflow.

MariaDB is an isolated GitHub Actions service with disposable credentials.
Doctrine's test suffix changes the configured database name `worktimeflow` to
`worktimeflow_test`, which the service creates before migrations run.
No development or production database is used.

Composer scripts are disabled during installation because they also install
frontend assets. Symfony validation is run explicitly. This workflow does not
deploy the application or publish releases.

Run the checks locally after installing dependencies:

```sh
composer validate --strict --no-check-publish
php bin/console lint:yaml config .github --parse-tags
php bin/console lint:container --env=test
php bin/console lint:twig templates --env=test
php bin/phpunit --fail-on-empty-test-suite
```

Only run migrations against a separate test database. The GitHub Actions job
always starts with a fresh database and applies all committed migrations.
