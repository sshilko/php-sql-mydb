<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Prepared statement API (opt-in)

## Motivation

This is item 2 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is the
missing prepared-statement surface: production workloads that pass
user-controlled values at high concurrency need parameterized execution as
defense-in-depth and to remove manual `escape()` responsibility.

Evidence found during the audit:

- No `prepare`, `stmt`, `mysqli_stmt` usage anywhere in `src/sql` (grep
  confirms). README positions raw SQL as the only execution path
  (`README.md:67`, `README.md:271`).

## Scope

- Add prepared-statement support behind a new interface so the existing raw
  path is untouched; the prepared path stays optional.
- Implementation order: this item builds on the hardened builder (item 1) and
  lands after items 1 and 3.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Replacing the raw-SQL execution path: `query()`/`command()` remain the
  default fast path.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. Add the prepared-statement interfaces and default implementation

- `sql\MydbPreparedStatementInterface` wrapping `mysqli_stmt`: bind, execute,
  fetch all, affected rows, insert id, and close.
- `MydbInterface::prepare(string $sql): MydbPreparedStatementInterface` (new
  default impl using `MydbMysqli`), plus
  `MydbInterface::execute(string $sql, array $params): ?array` convenience for
  single-shot execution.
- `MydbMysqliInterface::prepare(string $sql): ?mysqli_stmt` delegating to
  `mysqli::prepare()`, with a `prepare()` mock path in tests.

### 2. Types

- `$params` as `list<float|int|string|null|bool>`, bound with `bind_param()`;
  return the same `list<array<array-key, float|int|string|null>>` shape as
  `query()`.

### 3. Document

- The prepared path is optional; raw `query()`/`command()` remain the fast
  path; prepared statements handle user values and repeated execution-heavy
  pipelines.

## Affected files

- new `src/sql/MydbPreparedStatement.php`,
  `src/sql/MydbPreparedStatementInterface.php`
- `src/sql/MydbInterface.php`, `src/sql/Mydb.php`
- `src/sql/MydbMysqliInterface.php`, `src/sql/MydbMysqli.php`
- new `test/phpunit/PreparedStatementTest.php` (integration + mocked)
- `examples/example.php`, README (update the "no prepared statements" notes)

## Verification

- Integration tests against mydb-mysql80: parameterized insert/select with int,
  string, null and bool values; verify `getInsertId()` and affected rows;
  verify the injection attempt `'; DROP TABLE x; --` is executed as a single
  parameter, never as SQL.
- All six analyzers green; psalm/phpstan infer the new types at 100%.

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