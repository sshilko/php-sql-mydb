<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Modernize codebase to PHP 8.3

## Motivation

- PHP 8.0 and PHP 8.1 reached end of life; PHP 8.2 enters end of life in
  December 2026. PHP 8.3 is the current stable runtime that is still actively
  maintained (security support until December 2027).
- Standardizing on a single supported runtime (PHP 8.3) removes the need to
  keep tooling, Docker images and CI matrix entries for EOL runtimes.
- The codebase can be modernized with guaranteed-safe PHP 8.x language
  features that improve clarity, reduce boilerplate and harden static analysis.

## Scope

- Raise the minimum supported PHP version from `^8.0 || ^8.1 || ^8.2` to `^8.3`.
- Update runtime containers, CI workflows and documentation that list PHP
  version support.
- Refactor library source (`src/sql/`) and tests (`test/`) using PHP 8.x
  language features without behavioural change. Public API must remain
  backwards compatible.
- Keep quality gates passing: PHPCS, PHPMD, PHPCPD, PDepend, PHPStan, Psalm,
  Phan, PHPUnit.

## Out of scope

- Public API/BC breaks (method signatures of external contracts, exceptions,
  interfaces semantics).
- MariaDB compatibility (tracked separately in README roadmap).
- Dropping `mysqli`/`mysqlnd` extension usage.

## Affected files

- `composer.json`, `composer.lock` - PHP requirement and dev tool versions
- `build/Dockerfile.php83`, `build/Dockerfile.php82`, `build/docker-compose.yaml`
- `.github/workflows/phpunit83.yml`, `.github/workflows/phpunit82.yml`,
  `.github/workflows/php-docker.yml`, `.github/workflows/phpunit.yml`
- `README.md` - compatibility section and badges
- `CONTRIBUTING` - container list
- `src/sql/*.php` - language feature modernization
- `test/phpunit/*.php` - test modernization
- `build/phpstan.neon`, `build/psalm.xml`, `build/phan.php`,
  `build/phpcs-ruleset.xml` - analysis baseline for PHP 8.3

## Concrete steps

### 1. Runtime and dependency bump

- `composer.json`: set `"php": "^8.3"` in `require`.
- Update dev dependencies to versions that run on PHP 8.3 and produce
  PHP 8.3-aware analysis:
  - `phpunit/phpunit` to `^10` (or `^11`)
  - `nikic/php-parser` to `^5`
  - `phpstan/phpstan` to a current `^1.11`+ (or `^2` when stable for the
    project's baseline)
  - keep `psalm/phar`, `phan/phan`, `phpmd/phpmd`, `pdepend/pdepend`,
    `squizlabs/php_codesniffer`, `slevomat/coding-standard` current on PHP 8.3
- Regenerate `composer.lock`.

### 2. Runtime containers and CI

- Add `build/Dockerfile.php83` (mirror `Dockerfile.php82`, base image
  `library/php:8.3-cli`, xdebug version compatible with PHP 8.3).
- Add `app.php83` service to `build/docker-compose.yaml`.
- Add `.github/workflows/phpunit83.yml` mirroring `phpunit81.yml`/`phpunit82.yml`.
- Update `php-docker.yml`/`phpunit.yml` references where hardcoded to the
  8.1/8.2 build where appropriate (as the canonical build container).
- Follow the "Version policy" section of `AGENTS.md`.

### 3. Documentation

- `README.md` "Compatibility": `PHP 8.3`.
- `README.md` badges: add `phpunit83.yml` badge/github workflow link.
- `CONTRIBUTING`: add `app.php83` to the container list and update example
  commands to use `app.php83`.

### 4. Source modernization (safe, non-BC-breaking)

Apply the following only where the surrounding code style supports it and where
static analysis stays green:

- Typed class constants (PHP 8.3):
  - `MydbMysqli` constants - some are already typed, ensure all class constants
    declare an explicit type where unambiguous (e.g.
    `protected const string SQL_MODE = 'TRADITIONAL';`).
  - `MydbOptions` buffer bounds constants (`NET_CMD_BUFFER_SIZE_MIN`,
    `NET_CMD_BUFFER_SIZE_MAX`, `NET_READ_BUFFER_MIN`, `NET_READ_BUFFER_MAX`).
- `#[Override]` attribute (PHP 8.3) on methods that implement interface or
  parent-class contracts (e.g. methods in `Mydb`, `MydbMysqli`,
  `MydbOptions`, `MydbCredentials`, `MydbQueryBuilder`, `MydbRegistry`,
  `MydbEnvironment`, event/exception classes). Verify each method truly
  overrides an inherited/interface method; otherwise PHP emits an error.
- Constructor property promotion for value objects that are set only in the
  constructor and read afterwards, e.g. `MydbCredentials`.
- `readonly` classes for immutable value objects - `MydbCredentials` is a
  candidate after promotion.
- `readonly` on properties that are written only once, where the class is not
  otherwise mutated (except `MydbOptions`, which has public setters).
- Replace `0 === strpos($x, $y)` with `str_starts_with($x, $y)`:
  - `src/sql/MydbQueryBuilder.php:353` - `0 === strpos($unescaped, '0x')`
- `src/sql/Mydb.php:202` uses `substr()`/`strpos()` to extract a
  parenthesised suffix - it is not a prefix check and should be left as-is
  (or, if worth it, rewritten with `str_starts_with`/explicit return-string
  logic in a separate, non-behavioural review).
- Consider `str_contains()` where a plain `strpos(...) !== false` is used
  (only if found in current code).
- First-class callable syntax for existing `'method'` string callables where
  discovered by search (no known occurrences today).
- `#[\SensitiveParameter]` on `MydbCredentials` password-related entries where
  applicable (PHP 8.2+; may affect PHPUnit/fixture expectations, evaluate
  carefully).

Do NOT apply purely cosmetic rewrites that churn the diff without value.

### 5. Deprecation hygiene

Audit the codebase for features deprecated as of PHP 8.3 and remove/replace:

- Dynamic properties (deprecated since 8.2): verify no `$this->{$name}`
  assignments outside declared properties (check `MydbRegistry` and any
  `__get`/`__set` usage).
- mysqlnd/mysqli mismatch warnings reported on PHP 8.3 (`mysqli` properties
  access pattern) - verify against `php -l` and CGI startup warnings.
- `assert()` string-argument deprecation (8.3) - not used in current sources,
  but confirm.
- No implicit-nullable parameters (deprecated in 8.4, worth fixing now while
  touching the code): remove `Type $x = null` in favour of explicit
  `?Type $x = null` (scan `src/` and `test/`).

### 6. Quality configuration baseline

- `build/phpstan.neon`: confirm level, `phpVersion` (remove if it pins an old
  runtime, or set to `80300`) and add new rules enabled for PHP 8.3.
- `build/psalm.xml`: set `phpVersion` to 8.3, run `app-psalm-alter` for
  auto-fixable issues, keep suppressions minimal.
- `build/phan.php`: configure PHP 8.3 `target_php_version`.
- `build/phpcs-ruleset.xml`: enable rules that require PHP 8.3 if desired
  (e.g. `SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue`,
  `SlevomatCodingStandard.PHP.RequireExplicitAssertion` is out of scope).
- `build/pre-commit-config.yaml`: update hook env/tool versions if pinned.

### 7. Tests

- Modernize tests to PHP 8.x test style (attributes for data providers/data
  sets available in PHPUnit 10+) only if PHPUnit is updated.
- Keep existing test names/filters (e.g. `--filter SelectTest`, `--filter
  escape`) working.
- Ensure at least one test run per supported MySQL server (5.7 and 8.0) on
  PHP 8.3.

## Verification

- Local (or Docker `app.php83`):
  - `composer validate`
  - `composer app-quality` - PHPCS/CBF, PHPMD, PHPCPD, PDepend, PHPStan,
    Psalm (incl. taint), Phan - all pass with no new findings
  - `composer app-phpunit` - PHPUnit against MySQL 5.7 and 8.0 passes
  - `php -l` on all modified source files
- CI: `.github/workflows/phpunit83.yml` green on PHP 8.3.
- Confirm zero `E_DEPRECATED`/`E_WARNING` from the library sources when run
  under PHP 8.3 with error reporting `E_ALL`.

## Suggested commit/merge sequence

1. `plans/` - this plan
2. `composer.json` + `composer.lock` dependency/runtime bump
3. Dockerfiles + `docker-compose.yaml` + CI workflows (`phpunit83.yml`)
4. README/CONTRIBUTING documentation
5. Source modernization (step 4) split into small semantic commits
6. Deprecation hygiene and quality baseline, keeping gates green
7. Final verification run

Each step should keep `composer app-quality` and `composer app-phpunit` green
independently.