<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Fix the canonical `composer app-quality` command

## Motivation

This is item 8 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is a
broken canonical quality command: contributors following the documented
workflow cannot complete it.

Evidence found during the audit:

- AGENTS.md "Static analysis gotchas" states the default `composer app-psalm`
  invocation aborts in the supported image with "Fatal Error Insufficient
  shared memory!", and only a manual `php -n -d zend_extension=opcache
  -d opcache.jit=0202 -d opcache.jit_buffer_size=100M
  -d extension=mysqli -d extension=pcntl -d extension=ast -d extension=xdebug
  ./vendor/bin/psalm.phar --config build/psalm.xml --no-cache --threads=1`
  works. `composer app-quality` therefore cannot complete for contributors or
  in docs, undermining the primary contribution workflow.

## Scope

- Make the default `composer app-quality` runnable end to end inside the
  supported container, preserving the existing report outputs.
- Implementation order: independent; may land in any order after item 3.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Changing Psalm configuration, error level, or the report format.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. Fix the psalm invocations in composer scripts

- Update the `app-psalm`, `app-psalm-alter`, and `app-psalm-taint` composer
  scripts (or their psalm invocation) in `composer.json` to the working
  invocation, preserving the existing report outputs
  (`--report=$PWD/build/tmp/psalm.txt`, `--stats`).

### 2. Verify `composer app-quality` runs end to end

- Verify `composer app-quality` in the `mydb-app-php83` container runs every
  gate end to end without manual workaround.

### 3. Update AGENTS.md

- Remove the workaround note or replace it with "no workaround needed".

## Affected files

- `composer.json`
- `build/Dockerfile.php83` (only if opcache JIT buffer must be raised for
  psalm)
- `AGENTS.md` (documentation of the working invocation)

## Verification

- Inside the container:
  `docker compose exec -w /app mydb-app-php83 composer app-quality` exits 0; all
  gates reach `app-phan` without manual commands.

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