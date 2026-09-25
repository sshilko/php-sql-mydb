<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Secure identifier handling in MydbQueryBuilder (SQL injection hardening)

## Motivation

This is item 1 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is a
security edge: table and column names are interpolated into SQL without
identifier-safe quoting, leaving parts of the builder surface injectable.

Evidence found during the audit:

- `MydbQueryBuilder::insertOne()` interpolates `$table` raw
  (`src/sql/MydbQueryBuilder.php:101`).
- `buildUpdateWhere()` interpolates `$table` raw (`:197`).
- `buildUpdateWhereMany()` interpolates `$table` and column names raw
  (`:118`, `:137`, `:150`).
- `buildInsertMany()` interpolates `$table` and every column in `$cols` raw
  (`:340-341`).
- `showColumnsLike()`, `showKeys()`, `buildDeleteWhere()` use
  `escape($table, '')` (`:66`, `:79`, `:218`), but that is not identifier-safe:
  with an empty quote `escape()` returns `real_escape_string()` output, which
  does not quote with backticks and passes backticks and semicolons through
  (`escape()` at `:358-399`). Composed output like
  `SHOW KEYS FROM `a`-- ` is injectable.

## Scope

- Add an identifier-quoting API to the builder and use it for every table and
  column name; keep value escaping unchanged.
- Implementation order: this item and item 3 ("Uniform, non-silent error
  handling and safe shutdown") land first and are independently releasable.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Changing the raw-SQL default execution path semantics beyond this builder
  surface.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. Add `quoteIdentifier` to the builder interface and implementation

- Add `MydbQueryBuilderInterface::quoteIdentifier(string $identifier): string`
  and a runtime implementation that validates the identifier against a strict
  pattern (for example `^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$` for `db.table`
  forms) and backtick-quotes it, throwing `QueryBuilderException` on invalid
  input. Do not fall back to `real_escape_string` for identifiers.

### 2. Use `quoteIdentifier()` for every table and column name

- Apply it across the builder: `insertOne`, `replaceOne`, `insertMany` (also
  the `$cols` list, keeping `MydbExpression` passthrough untouched),
  `buildUpdateWhere`, `buildUpdateWhereMany`, `buildDeleteWhere`,
  `showColumnsLike`, `showKeys`.

### 3. Keep value escaping unchanged

- `escape()` for values stays as is.

## Affected files

- `src/sql/MydbQueryBuilderInterface.php`
- `src/sql/MydbQueryBuilder.php`
- `test/phpunit/QueryBuilderTest.php`
- `test/phpunit/MydbModernizationTest.php` (escape/identifier regression)

## Verification

- New unit tests: identifier acceptance/rejection matrix (single identifier,
  `db.table`, backticked input, semicolon input, empty, whitespace) and one
  integration test proving a malicious `$table` is rejected with
  `QueryBuilderException` instead of producing executable SQL.
- Existing QueryBuilderTest data sets still pass unchanged.

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