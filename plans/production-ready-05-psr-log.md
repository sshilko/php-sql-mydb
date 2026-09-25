<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Support psr/log ^2 and ^3

## Motivation

This is item 5 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is
dependency health: the `psr/log` constraint blocks adoption in current
ecosystem stacks.

Evidence found during the audit:

- `composer.json:142` requires `"psr/log": "^1"` only. Current ecosystem
  loggers (Monolog 3, modern Symfony/Laravel bridges) use `psr/log:^2|^3`,
  which blocks adoption and can force dependency clashes in production apps.

## Scope

- Widen the `psr/log` constraint to `^1 || ^2 || ^3` and prove the call sites
  type-check against the v3 interfaces.
- Implementation order: independent; may land in any order after item 3. The
  CI matrix in item 7 is where the v2/v3 resolution is exercised.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Changing the logging surface beyond the constraint and type check.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. Widen the constraint

- Change `composer.json` to `"psr/log": "^1 || ^2 || ^3"` and regenerate
  `composer.lock`.

### 2. Verify every call site

- Verify every `Psr\Log\LoggerInterface` call site (all `MydbLogger` methods
  and `Mydb::onWarning/onError`) still type-checks against the v3 interfaces.

### 3. Prove compatibility in CI

- Run the full quality suite and PHPUnit with both `psr/log` v2 and v3
  resolved in CI (see item 7 for the matrix).
- Update the README dependency note.

## Affected files

- `composer.json`, `composer.lock` (regenerated)
- CI workflow additions (item 7)
- `README.md` (dependency note)

## Verification

- `composer update psr/log` resolves v3; `composer app-quality` +
  `app-phan` + `app-phpunit-mydb-mysql80` green.
- CI job with `"psr/log": "^3"` forced in `composer.json` (temporary matrix
  axis) passes.

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