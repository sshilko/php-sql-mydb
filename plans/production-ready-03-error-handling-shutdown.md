<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Uniform, non-silent error handling and safe shutdown

## Motivation

This is item 3 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gaps closed here are
silent DML failure paths and a shutdown fatal-error risk.

Evidence found during the audit:

- `Mydb::command()` returns `false` as soon as `real_query()` fails
  (`src/sql/Mydb.php:165-169`) without calling `readServerResponse()`, so DML
  errors are visible only as `null`/`false` from `insert()`, `update()`,
  `delete()` and friends. `query()` has the explicit comment "We should always
  read server response" (`:129-132`) but `command()` does not implement it.
  `ResourceTest::testQueryBadClientRequest` (line 249) documents the silent
  `null`.
- `__destruct()` calls `close()` unguarded (`:93-97`); `close()` re-throws via
  `onError()` from inside its own `catch` (`:363-367`). An exception escaping a
  destructor during script shutdown is a PHP 8 fatal error.

## Scope

- Make `command()` read the server response like `query()`, guarantee error
  packets always raise, and make `__destruct()` safe.
- Implementation order: this item and item 1 ("Secure identifier handling in
  MydbQueryBuilder") land first and are independently releasable.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Changing the raw-SQL default execution path semantics beyond the error
  handling described here.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. `command()`: always read the server response

- Mirror `query()` so SQL errors surface as typed exceptions
  (`MydbException\InternalException` or `ServerGoneException`) and warnings
  reach the PSR-3 logger. Keep returning `false` only when no connection could
  be established or the packet is `null` for non-error reasons; document the
  remaining nullable contracts.

### 2. `query()`: never return a silent `null` on an error packet

- Ensure an error packet with an error message always raises (close the
  `getErrNo() === 0` gap).

### 3. Destructor safety

- Wrap the `__destruct()` body in `try/catch Throwable`, log a warning via
  `$this->logger` and never throw. Add a protected `closeOrLog()` used only by
  the destructor; keep public `close()` exception behavior for explicit
  callers.

### 4. Update documentation

- Update README "Reliability" wording so the error model matches reality.

## Affected files

- `src/sql/Mydb.php`
- `test/phpunit/ResourceTest.php`, `test/phpunit/UpdateTest.php`,
  `test/phpunit/DeleteTest.php`, `test/phpunit/InsertTest.php`
- new `test/phpunit/ShutdownTest.php` (mock mysqli throwing on close ->
  destructor must not throw)
- `README.md`

## Verification

- New tests: `command("UPDATE missing_table ...")` raises
  `InternalException`; `insert/update/delete` on a SQL error throw; a mock
  `close()` that throws does not propagate out of `__destruct()`; warnings are
  logged on the DML failure path.
- Re-run `ExceptionTest` (existing expectations must not change for SELECT).

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