<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Close test-coverage gaps

## Motivation

This is item 9 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is
undirected coverage: several classes and accessor methods are never directly
asserted.

Evidence found during the audit:

- `MydbEnvironment` has 7 of 9 public methods untested directly (only
  `startSignalsTrap`/`endSignalsTrap`, per `test/phpunit/MydbEnvironmentTest.php`)
  - with Phan reporting findings there per AGENTS.md.
- `MydbMysqli` accessors `getConnectErrno`, `getConnectError`, `getError`,
  `getErrNo`, `getInsertId`, `isServerGone`, `setTransactionIsolationLevel`,
  `mysqliReport` are not directly asserted.
- The ~25 `MydbException\*` subclasses have no dedicated battery
  (`MydbExceptionTest` covers 2).
- No destructor/shutdown tests (item 3) and no SQL-injection regression tests
  (item 1).

## Scope

- Direct tests for the untested `MydbEnvironment` and `MydbMysqli` methods, a
  table-driven exception battery, and the shutdown/injection tests referenced
  by items 3 and 1.
- Implementation order: independent; may land in any order after item 3.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Raising the coverage threshold in tooling config (only the report output is
  expected to improve).
- Migration of the codebase to another runtime or extension.

## Steps

### 1. `MydbEnvironmentTest`

- Direct tests for `gc_collect_cycles`,
  `set_error_handler`/`restore_error_handler`, `setMysqlndNetReadTimeout`,
  `error_reporting`, `ignore_user_abort`, `ini_set`, and the signal trap error
  paths (cover the `@codeCoverageIgnore` gaps where practical).

### 2. `MysqliTest`

- Direct assertions for all accessor methods using a connected real server and
  mocks for the failure branches.

### 3. `MydbExceptionTest`

- Table-driven test that instantiates every subclass, inherits `MydbException`,
  and passes message/code/previous through.

### 4. Add the shutdown and injection tests

- Add `ShutdownTest` (item 3) and builder identifier-injection tests (item 1).

## Affected files

- `test/phpunit/MydbEnvironmentTest.php`, `test/phpunit/MysqliTest.php`,
  `test/phpunit/MydbExceptionTest.php`
- new `test/phpunit/ShutdownTest.php`, additions to `QueryBuilderTest.php`
- Coverage badge output is expected to improve; refresh badge expectations in
  CI artifacts only via the normal pipeline.

## Verification

- `composer app-phpunit-mydb-mysql80` green with `failOnSkipped`/`failOnIncomplete`
  still enabled; coverage text report shows the directly-tested methods now
  covered; Phan/Psalm findings in `MydbEnvironment` resolved or explicitly
  justified.

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