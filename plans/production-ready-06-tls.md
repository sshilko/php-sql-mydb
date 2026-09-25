<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: TLS/SSL support for connections

## Motivation

This is item 6 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is
missing TLS support: production MySQL (cloud RDS, managed, or same-DC
TLS-gated) commonly requires TLS.

Evidence found during the audit:

- No `ssl`, `tls`, or `ssl_set` anywhere in `src/sql`. Only
  `MydbCredentials::$flags` can carry `MYSQLI_CLIENT_SSL`; there is no way to
  configure CA/cert, or to require or verify server certificates.

## Scope

- Add TLS settings to options, wire them into `MydbMysqli::setTransportOptions()`
  before `real_connect()`, and exercise the TLS handshake in integration tests.
- Implementation order: independent; can proceed in parallel with item 7.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Replacing the connection handshake beyond TLS configuration.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. Add TLS settings to options

- `MydbOptionsInterface`:
  - `getSslKey/setSslKey`, `getSslCert/setSslCert`, `getSslCa/setSslCa`,
    `getSslVerify/setSslVerify`, `getSslEnabled/setSslEnabled`.
  - Default: TLS enabled when the server supports it, verification off by
    default (documented), with safe flags for strict verification.
- `src/sql/MydbFactory.php` (defaults).

### 2. Wire TLS into the connection

- `MydbMysqli::setTransportOptions()`: call `mysqli->ssl_set()` with the
  configured key/cert/ca before `real_connect()` when TLS is enabled, and add
  `MYSQLI_CLIENT_SSL` to the connection flags.

### 3. Enable TLS in the test environment

- Update the docker MySQL service to enable TLS with a self-signed CA so
  integration tests exercise the TLS handshake.

## Affected files

- `src/sql/MydbOptionsInterface.php`, `src/sql/MydbOptions.php`
- `src/sql/MydbMysqli.php`
- `src/sql/MydbFactory.php` (defaults)
- `test/docker-compose.yaml` (TLS certs + `--require-secure-transport` where
  feasible)
- `test/phpunit/OptionsTest.php`, new `test/phpunit/TlsTest.php`
- `README.md`

## Verification

- Options boundary tests (empty CA vs path) and an integration test that
  connects over TLS with verification on and off; `SHOW STATUS LIKE
  'Ssl_cipher'` returns non-empty for the TLS connection.

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