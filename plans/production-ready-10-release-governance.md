<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Release and ecosystem governance

## Motivation

This is item 10 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is
release/ecosystem governance: versioning hygiene, security reporting,
dependency updates, and CI cleanup.

Evidence found during the audit:

- README version table lists 2.0.0 as latest stable and 3.x as unreleased
  (`README.md:105-109`) while the codebase is already PHP 8.3/3.x.
- No `CHANGELOG.md`, no `SECURITY.md`, no dependabot config, no concurrency
  control on CI.
- Dead config: `test/phpunit.xml:91` `<includePath>phpunit/base/</includePath>`
  points at a non-existent directory.
- Each workflow repeats a "pages" badge job.

## Scope

- Tag/release 3.0.0 once items 1-6 land, add governance files, remove dead
  config, and consolidate CI duplication.
- Implementation order: last, just before tagging 3.0.0.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Releasing before items 1-6 are done.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. Release the 3.x line

- Tag and release `3.0.0` on Packagist once items 1-6 land; update the README
  version table and "what's new in 3.x" notes (remove "not released yet").

### 2. Add governance files

- Add `CHANGELOG.md` (Keep a Changelog format) and `SECURITY.md` (how to
  report; note the adversarial-priority items 1-3).
- Add `.github/dependabot.yml` for `composer` and `github-actions`.

### 3. Clean up dead config and credentials

- Remove the dead `<includePath>` entry; document test DB credentials via env
  with `.env`/CI secrets rather than hardcoded constants where feasible.

### 4. Consolidate CI

- Add `concurrency` groups to CI workflows to cancel superseded runs, and
  consolidate the duplicated badge/pages jobs into one reusable workflow
  (or a shared composite action).

## Affected files

- new `CHANGELOG.md`, `SECURITY.md`, `.github/dependabot.yml`
- `README.md`, `test/phpunit.xml`, `.github/workflows/*.yml`

## Verification

- `composer validate --strict` passes; `composer app-quality` +
  `app-phan` + `app-phpunit-mydb-mysql80` green after the config cleanup; a dry-run
  `composer release` (or manual tag) produces the documented 3.x artifact.

### Full gates (after the change)

Run in the container in this order (never on the Windows host, per AGENTS.md):

1. `docker compose up -d mydb-app-php83 mydb-mysql80`
2. `docker compose exec -w /app mydb-app-php83 composer app-quality`
3. `docker compose exec -w /app mydb-app-php83 composer app-phan`
4. `docker compose exec -w /app mydb-app-php83 composer app-phpunit-mydb-mysql80`
   - Expect 284+ tests, 877+ assertions, zero errors; the one expected
     `mysqli::real_connect()` "Connection timed out" warning from
     `ExceptionTest` remains.
5. New and changed files pass PHPCS (PSR-12), PHPStan, Psalm (100% inference),
   Psalm taint, Phan, PHPMD, PHPCPD, PDepend.
6. Update AGENTS.md whenever a documented workaround or expected-findings list
   changes.