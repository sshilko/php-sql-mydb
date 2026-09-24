<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: TOP-10 improvements to make MyDb production ready

## Motivation

Repository audit performed 2026-09-23. The library already has strong
foundations: 284 tests / 877 assertions against a live MySQL 8.0, six static
analyzers in CI, interface-driven DI, and opinionated safe defaults. The gaps
below are production-readiness items: security edges in identifier handling and
the prepared-statement surface, silent DML failure paths, shutdown fatal-error
risk, dependency health (`psr/log`), missing TLS support, integration tests not
gating pull requests, a broken canonical quality command, test-coverage holes,
and missing release/ecosystem governance.

Each item lists the concrete evidence found during the audit, the change, the
affected files, and how to verify it. Implement each item in its own commit.

## Scope

Items 1-10 below, in the listed implementation order (security and reliability
first, then compatibility, CI, tooling, tests, governance).

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Changing the raw-SQL default execution path semantics beyond the error
  handling described in item 3.
- Migration of the codebase to another runtime or extension.

---

## 1. Secure identifier handling in MydbQueryBuilder (SQL injection hardening)

### Evidence

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

### Change

- Add `MydbQueryBuilderInterface::quoteIdentifier(string $identifier): string`
  (and a runtime implementation) that validates the identifier against a strict
  pattern (for example `^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$` for
  `db.table` forms) and backtick-quotes it, throwing `QueryBuilderException` on
  invalid input. Do not fall back to `real_escape_string` for identifiers.
- Use `quoteIdentifier()` for every table and column name across the builder:
  `insertOne`, `replaceOne`, `insertMany` (also the `$cols` list, keeping
  `MydbExpression` passthrough untouched), `buildUpdateWhere`,
  `buildUpdateWhereMany`, `buildDeleteWhere`, `showColumnsLike`, `showKeys`.
- Keep value escaping unchanged (`escape()` for values stays as is).

### Affected files

- `src/sql/MydbQueryBuilderInterface.php`
- `src/sql/MydbQueryBuilder.php`
- `test/phpunit/QueryBuilderTest.php`
- `test/phpunit/MydbModernizationTest.php` (escape/identifier regression)

### Verification

- New unit tests: identifier acceptance/rejection matrix (single identifier,
  `db.table`, backticked input, semicolon input, empty, whitespace) and one
  integration test proving a malicious `$table` is rejected with
  `QueryBuilderException` instead of producing executable SQL.
- Existing QueryBuilderTest data sets still pass unchanged.
- Full gates (see Verification section).

## 2. Prepared statement API (opt-in)

### Evidence

- No `prepare`, `stmt`, `mysqli_stmt` usage anywhere in `src/sql` (grep
  confirms). README positions raw SQL as the only execution path
  (`README.md:67`, `README.md:271`). For production workloads that pass
  user-controlled values at high concurrency, parameterized execution is the
  standard defense-in-depth and removes manual `escape()` responsibility.

### Change

- Add prepared-statement support behind a new interface so the existing raw
  path is untouched:
  - `sql\MydbPreparedStatementInterface` wrapping `mysqli_stmt`: bind, execute,
    fetch all, affected rows, insert id, and close.
  - `MydbInterface::prepare(string $sql): MydbPreparedStatementInterface`
    (new default impl using `MydbMysqli`), plus
    `MydbInterface::execute(string $sql, array $params): ?array` convenience for
    single-shot execution.
  - `MydbMysqliInterface::prepare(string $sql): ?mysqli_stmt` delegating to
    `mysqli::prepare()`, with a `prepare()` mock path in tests.
- Types: `$params` as `list<float|int|string|null|bool>`, bound with
  `bind_param()`; return the same
  `list<array<array-key, float|int|string|null>>` shape as `query()`.
- Document: prepared path is optional; raw `query()`/`command()` remain the
  fast path; prepared statements handle user values and repeated
  execution-heavy pipelines.

### Affected files

- new `src/sql/MydbPreparedStatement.php`,
  `src/sql/MydbPreparedStatementInterface.php`
- `src/sql/MydbInterface.php`, `src/sql/Mydb.php`
- `src/sql/MydbMysqliInterface.php`, `src/sql/MydbMysqli.php`
- new `test/phpunit/PreparedStatementTest.php` (integration + mocked)
- `examples/example.php`, README (update the "no prepared statements" notes)

### Verification

- Integration tests against mydb-mysql80: parameterized insert/select with int,
  string, null and bool values; verify `getInsertId()` and affected rows;
  verify injection attempt `'; DROP TABLE x; --` is executed as a single
  parameter, never as SQL.
- All six analyzers green; psalm/phpstan infer the new types at 100%.

## 3. Uniform, non-silent error handling and safe shutdown

### Evidence

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

### Change

- `command()`: always read the server response, exactly like `query()`, so SQL
  errors surface as typed exceptions (`MydbException\InternalException` or
  `ServerGoneException`) and warnings reach the PSR-3 logger. Keep returning
  `false` only when no connection could be established or the packet is `null`
  for non-error reasons; document the remaining nullable contracts.
- `query()`: ensure an error packet with an error message always raises, never
  returns a silent `null` (close the `getErrNo() === 0` gap).
- Destructor safety: wrap the `__destruct()` body in `try/catch Throwable`,
  log a warning via `$this->logger` and never throw. Add a protected
  `closeOrLog()` used only by the destructor; keep public `close()` exception
  behavior for explicit callers.
- Update README "Reliability" wording so the error model matches reality.

### Affected files

- `src/sql/Mydb.php`
- `test/phpunit/ResourceTest.php`, `test/phpunit/UpdateTest.php`,
  `test/phpunit/DeleteTest.php`, `test/phpunit/InsertTest.php`
- new `test/phpunit/ShutdownTest.php` (mock mysqli throwing on close ->
  destructor must not throw)
- `README.md`

### Verification

- New tests: `command("UPDATE missing_table ...")` raises
  `InternalException`; `insert/update/delete` on a SQL error throw; a mock
  `close()` that throws does not propagate out of `__destruct()`; warnings are
  logged on the DML failure path.
- Re-run `ExceptionTest` (existing expectations must not change for SELECT).

## 4. Dead-connection recovery (server has gone away) with safe retry

### Evidence

- `isServerGone()` maps errno 2002/2006 (`src/sql/MydbMysqli.php:499-502`);
  on a gone server the library closes the connection and throws
  `ServerGoneException` (`src/sql/Mydb.php:575-580`). Reconnect exists only in
  `connect()` for the initial connect (`:613-668`). Long-lived workers
  (queue consumers, daemons) must catch and reconnect manually today.

### Change

- Add `MydbOptionsInterface::isAutoReconnect()` /
  `setAutoReconnect(bool)` (default `false` to preserve current behavior).
- When auto-reconnect is enabled and `ServerGoneException` fires:
  - retry once only when no transaction is open (`!isTransactionOpen()`);
  - never retry DML (`update`, `delete`, `insert`, DDL) — these are
    non-idempotent; document this clearly;
  - reconnect through the existing `connect()` path and re-run the statement
    once for `query()`/`select()`/`escape()` usage; surface the reconnect via
    the existing listener (`InternalConnectionBegin/End` carry `host`/`dbname`
    and a `success` flag) by adding a `reconnect: true` marker in metadata.
- If the retried attempt fails again, throw the original `ServerGoneException`.

### Affected files

- `src/sql/MydbOptionsInterface.php`, `src/sql/MydbOptions.php`
- `src/sql/Mydb.php`
- new `test/phpunit/ReconnectTest.php` (mock `realQuery` throwing gone, then a
  connected path; assert single retry, no retry inside transaction, no DML
  retry)
- `README.md` (Reliability section)

### Verification

- Unit tests listed above; integration test using
  `KILL <connection_id>` via the root connection to force errno 2006 and
  observe successful automatic retry of a read.

## 5. Support psr/log ^2 and ^3

### Evidence

- `composer.json:142` requires `"psr/log": "^1"` only. Current ecosystem
  loggers (Monolog 3, modern Symfony/Laravel bridges) use `psr/log:^2|^3`,
  which blocks adoption and can force dependency clashes in production apps.

### Change

- Change the constraint to `"psr/log": "^1 || ^2 || ^3"`.
- Verify every `Psr\Log\LoggerInterface` call site (all `MydbLogger` methods
  and `Mydb::onWarning/onError`) still type-checks against the v3 interfaces.
- Run the full quality suite and PHPUnit with both `psr/log` v2 and v3
  resolved in CI (see item 7 for the matrix), to prove compatibility.

### Affected files

- `composer.json`, `composer.lock` (regenerated)
- CI workflow additions (item 7)
- `README.md` (dependency note)

### Verification

- `composer update psr/log` resolves v3; `composer app-quality` +
  `app-phan` + `app-phpunit-mydb-mysql80` green.
- CI job with `"psr/log": "^3"` forced in `composer.json` (temporary matrix
  axis) passes.

## 6. TLS/SSL support for connections

### Evidence

- No `ssl`, `tls`, or `ssl_set` anywhere in `src/sql`. Only
  `MydbCredentials::$flags` can carry `MYSQLI_CLIENT_SSL`; there is no way to
  configure CA/cert, or to require or verify server certificates. Production
  MySQL (cloud RDS, managed, or same-DC TLS-gated) commonly requires TLS.

### Change

- Add TLS settings to options:
  - `MydbOptionsInterface`: `getSslKey/setSslKey`,
    `getSslCert/setSslCert`, `getSslCa/setSslCa`, `getSslVerify/setSslVerify`,
    `getSslEnabled/setSslEnabled`.
  - Default: TLS enabled when the server supports it, verification off by
    default (documented), with safe flags for strict verification.
- `MydbMysqli::setTransportOptions()`: call `mysqli->ssl_set()` with the
  configured key/cert/ca before `real_connect()` when TLS is enabled, and add
  `MYSQLI_CLIENT_SSL` to the connection flags.
- Update the docker MySQL service to enable TLS with a self-signed CA so
  integration tests exercise the TLS handshake.

### Affected files

- `src/sql/MydbOptionsInterface.php`, `src/sql/MydbOptions.php`
- `src/sql/MydbMysqli.php`
- `src/sql/MydbFactory.php` (defaults)
- `test/docker-compose.yaml` (TLS certs + `--require-secure-transport` where
  feasible)
- `test/phpunit/OptionsTest.php`, new `test/phpunit/TlsTest.php`
- `README.md`

### Verification

- Options boundary tests (empty CA vs path) and an integration test that
  connects over TLS with verification on and off; `SHOW STATUS LIKE
  'Ssl_cipher'` returns non-empty for the TLS connection.

## 7. CI: integration PHPUnit on pull requests and a runtime/DB matrix

### Evidence

- `.github/workflows/phpunit83.yml` triggers only on
  `push: branches: [master]`. `phpstan.yml`, `phppsalm.yml`, `phpphan.yml`,
  `phpmd.yml`, `phpcs.yml` all run on `pull_request`. The most important gate
  (integration suite against real MySQL) therefore never runs before merge.
- Only PHP 8.3 and MySQL 8.0 are exercised; `composer.json` permits
  `>=8.3` (PHP 8.4/8.5 are current), README claims MySQL 5.7 support, and
  MariaDB is documented as incompatible with no CI signal.

### Change

- Add `pull_request` to the `phpunit83.yml` trigger (keep the `build-containers`
  job shared), and add `workflow_dispatch`.
- Add a matrix job for PHP runtimes (8.3, 8.4, 8.5) against MySQL 8.0 and an
  additional MySQL 8.4 service; each matrix cell runs PHPUnit with coverage
  disabled to keep the runtime bounded.
- Decide the 5.7/MariaDB story explicitly: either add a `mydb-mysql57` /
  `mydb-mariadb` CI job or document these as unsupported and remove the claims from
  the README and AGENTS.md. Record the decision in a follow-up commit; this
  plan does not rewrite the compatibility list until the decision is recorded
  (prefer adding a 5.7 job since it is currently claimed supported).
- Do not re-run badge generation from every matrix cell: only the 8.3/MySQL
  8.0 cell produces coverage badges.

### Affected files

- `.github/workflows/phpunit83.yml`
- new or extended `test/docker-compose.yaml` services (mydb-mysql84, optional
  mydb-mysql57/mydb-mariadb)
- `composer.json` (matrix helper scripts only if needed)
- `README.md`, `AGENTS.md` (compatibility table)

### Verification

- A PR branch pushes green CI with integration tests running; matrix report
  shows each PHP/DB cell executed the suite; badge job still runs once from
  the 8.3/8.0 cell.

## 8. Fix the canonical `composer app-quality` command

### Evidence

- AGENTS.md "Static analysis gotchas" states the default `composer app-psalm`
  invocation aborts in the supported image with "Fatal Error Insufficient
  shared memory!", and only a manual `php -n -d zend_extension=opcache
  -d opcache.jit=0202 -d opcache.jit_buffer_size=100M
  -d extension=mysqli -d extension=pcntl -d extension=ast -d extension=xdebug
  ./vendor/bin/psalm.phar --config build/psalm.xml --no-cache --threads=1`
  works. `composer app-quality` therefore cannot complete for contributors or
  in docs, underming the primary contribution workflow.

### Change

- Update the `app-psalm`, `app-psalm-alter`, and `app-psalm-taint` composer
  scripts (or their psalm invocation) in `composer.json` to the working
  invocation, preserving the existing report outputs
  (`--report=$PWD/build/tmp/psalm.txt`, `--stats`).
- Verify `composer app-quality` in the `mydb-app-php83` container runs every gate
  end to end without manual workaround.
- Update AGENTS.md: remove the workaround note or replace it with "no
  workaround needed".

### Affected files

- `composer.json`
- `build/Dockerfile.php83` (only if opcache JIT buffer must be raised for
  psalm)
- `AGENTS.md` (documentation of the working invocation)

### Verification

- Inside the container:
  `docker compose exec -w /app mydb-app-php83 composer app-quality` exits 0; all
  gates reach `app-phan` without manual commands.

## 9. Close test-coverage gaps

### Evidence

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

### Change

- `MydbEnvironmentTest`: direct tests for `gc_collect_cycles`,
  `set_error_handler`/`restore_error_handler`, `setMysqlndNetReadTimeout`,
  `error_reporting`, `ignore_user_abort`, `ini_set`, and the signal trap error
  paths (cover the `@codeCoverageIgnore` gaps where practical).
- `MysqliTest`: direct assertions for all accessor methods using a connected
  real server and mocks for the failure branches.
- `MydbExceptionTest`: table-driven test that instantiates every subclass,
  inherits `MydbException`, and passes message/code/previous through.
- Add `ShutdownTest` (item 3) and builder identifier-injection tests (item 1).

### Affected files

- `test/phpunit/MydbEnvironmentTest.php`, `test/phpunit/MysqliTest.php`,
  `test/phpunit/MydbExceptionTest.php`
- new `test/phpunit/ShutdownTest.php`, additions to `QueryBuilderTest.php`
- Coverage badge output is expected to improve; refresh badge expectations in
  CI artifacts only via the normal pipeline.

### Verification

- `composer app-phpunit-mydb-mysql80` green with `failOnSkipped`/`failOnIncomplete`
  still enabled; coverage text report shows the directly-tested methods now
  covered; Phan/Psalm findings in `MydbEnvironment` resolved or explicitly
  justified.

## 10. Release and ecosystem governance

### Evidence

- README version table lists 2.0.0 as latest stable and 3.x as unreleased
  (`README.md:105-109`) while the codebase is already PHP 8.3/3.x. No
  CHANGELOG.md, no SECURITY.md, no dependabot config, no concurrency control
  on CI. Dead config: `test/phpunit.xml:91`
  `<includePath>phpunit/base/</includePath>` points at a non-existent
  directory. Each workflow repeats a "pages" badge job.

### Change

- Tag and release `3.0.0` on Packagist once items 1-6 land; update the README
  version table and "what's new in 3.x" notes (remove "not released yet").
- Add `CHANGELOG.md` (Keep a Changelog format) and `SECURITY.md` (how to
  report; note the adversarial-priority items 1-3).
- Add `.github/dependabot.yml` for `composer` and `github-actions`.
- Remove the dead `<includePath>` entry; document test DB credentials via env
  with `.env`/CI secrets rather than hardcoded constants where feasible.
- Add `concurrency` groups to CI workflows to cancel superseded runs, and
  consolidate the duplicated badge/pages jobs into one reusable workflow
  (or a shared composite action).

### Affected files

- new `CHANGELOG.md`, `SECURITY.md`, `.github/dependabot.yml`
- `README.md`, `test/phpunit.xml`, `.github/workflows/*.yml`

### Verification

- `composer validate --strict` passes; `composer app-quality` +
  `app-phan` + `app-phpunit-mydb-mysql80` green after the config cleanup; a dry-run
  `composer release` (or manual tag) produces the documented 3.x artifact.

---

## Affected files (summary)

- `src/sql/MydbQueryBuilder.php`, `src/sql/MydbQueryBuilderInterface.php`
- `src/sql/Mydb.php`, `src/sql/MydbInterface.php`
- new `src/sql/MydbPreparedStatement.php`,
  `src/sql/MydbPreparedStatementInterface.php`
- `src/sql/MydbMysqli.php`, `src/sql/MydbMysqliInterface.php`
- `src/sql/MydbOptions.php`, `src/sql/MydbOptionsInterface.php`,
  `src/sql/MydbFactory.php`
- `composer.json`, `composer.lock`
- `test/phpunit/*` (see each item), `test/phpunit.xml`,
  `test/docker-compose.yaml`
- `.github/workflows/*.yml`, new `.github/dependabot.yml`
- new `CHANGELOG.md`, `SECURITY.md`
- `README.md`, `AGENTS.md`, `examples/example.php`

## Verification

Run in the container in this order after each implemented item (never on the
Windows host, per AGENTS.md):

1. `docker compose up -d mydb-app-php83 mydb-mysql80`
2. `docker compose exec -w /app mydb-app-php83 composer app-quality`
3. `docker compose exec -w /app mydb-app-php83 composer app-phan`
4. `docker compose exec -w /app mydb-app-php83 composer app-phpunit-mydb-mysql80`
   - Expect 284+ tests, 877+ assertions, zero errors; the one expected
     `mysqli::real_connect()` "Connection timed out" warning from
     `ExceptionTest` remains.
5. Every new file passes PHPCS (PSR-12), PHPStan, Psalm (100% inference),
   Psalm taint, Phan, PHPMD, PHPCPD, PDepend.
6. Update AGENTS.md notes whenever a documented workaround (e.g. the Psalm
   invocation) or expected-findings list changes.

## Implementation order

1. Item 1 (identifier hardening, security) and Item 3 (error surface +
   shutdown) land first and are independently releasable.
2. Item 2 (prepared statements) next; it builds on the hardened builder.
3. Item 6 (TLS) and Item 7 (CI matrix) can proceed in parallel.
4. Item 4 (auto-reconnect), Item 5 (psr/log), Item 8 (app-quality fix), Item 9
   (test gaps) in any order after 3.
5. Item 10 (release/governance) last, just before tagging 3.0.0.