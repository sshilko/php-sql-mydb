<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: CI - integration PHPUnit on pull requests and a runtime/DB matrix

## Motivation

This is item 7 of the TOP-10 production-readiness plan from the repository
audit performed 2026-09-23. The library already has strong foundations: 284
tests / 877 assertions against a live MySQL 8.0, six static analyzers in CI,
interface-driven DI, and opinionated safe defaults. The gap closed here is CI
gating: the integration suite never runs before merge, and only one runtime/DB
combination is exercised.

Evidence found during the audit:

- `.github/workflows/phpunit83.yml` triggers only on
  `push: branches: [master]`. `phpstan.yml`, `phppsalm.yml`, `phpphan.yml`,
  `phpmd.yml`, `phpcs.yml` all run on `pull_request`. The most important gate
  (integration suite against real MySQL) therefore never runs before merge.
- Only PHP 8.3 and MySQL 8.0 are exercised; `composer.json` permits
  `>=8.3` (PHP 8.4/8.5 are current), README claims MySQL 5.7 support, and
  MariaDB is documented as incompatible with no CI signal.

## Scope

- Trigger the integration PHPUnit workflow on pull requests, add a PHP/DB
  matrix, and make an explicit 5.7/MariaDB compatibility decision.
- Implementation order: independent; can proceed in parallel with item 6.

## Out of scope

- ORM, active record, or a query-language abstraction (deliberately out of
  scope per README).
- Rewriting the compatibility list until the 5.7/MariaDB decision is recorded.
- Migration of the codebase to another runtime or extension.

## Steps

### 1. Trigger integration PHPUnit on pull requests

- Add `pull_request` to the `phpunit83.yml` trigger (keep the `build-containers`
  job shared), and add `workflow_dispatch`.

### 2. Add a runtime/DB matrix

- Add a matrix job for PHP runtimes (8.3, 8.4, 8.5) against MySQL 8.0 and an
  additional MySQL 8.4 service; each matrix cell runs PHPUnit with coverage
  disabled to keep the runtime bounded.

### 3. Decide the 5.7/MariaDB story explicitly

- Either add a `mydb-mysql57` / `mydb-mariadb` CI job or document these as
  unsupported and remove the claims from the README and AGENTS.md.
- Record the decision in a follow-up commit; this plan does not rewrite the
  compatibility list until the decision is recorded (prefer adding a 5.7 job
  since it is currently claimed supported).

### 4. Keep badge generation single

- Do not re-run badge generation from every matrix cell: only the 8.3/MySQL
  8.0 cell produces coverage badges.

## Affected files

- `.github/workflows/phpunit83.yml`
- new or extended `test/docker-compose.yaml` services (mydb-mysql84, optional
  mydb-mysql57/mydb-mariadb)
- `composer.json` (matrix helper scripts only if needed)
- `README.md`, `AGENTS.md` (compatibility table)

## Verification

- A PR branch pushes green CI with integration tests running; matrix report
  shows each PHP/DB cell executed the suite; badge job still runs once from
  the 8.3/8.0 cell.

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