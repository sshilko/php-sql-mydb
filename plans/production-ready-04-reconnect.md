<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Dead-connection recovery (server has gone away) with safe retry

## Motivation

This is item 4 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is
automatic recovery from a dead connection for long-lived workers (queue
consumers, daemons).

Evidence found during the audit:

- `isServerGone()` maps errno 2002/2006 (`src/sql/MydbMysqli.php:499-502`); on
  a gone server the library closes the connection and throws
  `ServerGoneException` (`src/sql/Mydb.php:575-580`).
- Reconnect exists only in `connect()` for the initial connect (`:613-668`).
- Long-lived workers (queue consumers, daemons) must catch and reconnect
  manually today.

## Scope

- When auto-reconnect is enabled, recover from a gone server by reconnecting
  through the existing `connect()` path and re-running the statement once for
  reads. Never retry non-idempotent DML.
- Implementation order: independent; may land in any order after item 3.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Retrying DML (`update`, `delete`, `insert`, DDL) - these are non-idempotent
  and stay untouched.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. Add the auto-reconnect option

- Add `MydbOptionsInterface::isAutoReconnect()` / `setAutoReconnect(bool)`
  (default `false` to preserve current behavior).

### 2. Implement safe retry on `ServerGoneException`

- When auto-reconnect is enabled and `ServerGoneException` fires:
  - retry once only when no transaction is open (`!isTransactionOpen()`);
  - never retry DML (`update`, `delete`, `insert`, DDL) - these are
    non-idempotent; document this clearly;
  - reconnect through the existing `connect()` path and re-run the statement
    once for `query()`/`select()`/`escape()` usage; surface the reconnect via
    the existing listener (`InternalConnectionBegin/End` carry `host`/`dbname`
    and a `success` flag) by adding a `reconnect: true` marker in metadata.
- If the retried attempt fails again, throw the original `ServerGoneException`.

### 3. Document

- Update the README "Reliability" section: auto-reconnect is opt-in, reads
  only retried, never inside a transaction.

## Affected files

- `src/sql/MydbOptionsInterface.php`, `src/sql/MydbOptions.php`
- `src/sql/Mydb.php`
- new `test/phpunit/ReconnectTest.php` (mock `realQuery` throwing gone, then a
  connected path; assert single retry, no retry inside transaction, no DML
  retry)
- `README.md` (Reliability section)

## Verification

- Unit tests listed above; integration test using
  `KILL <connection_id>` via the root connection to force errno 2006 and
  observe successful automatic retry of a read.

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