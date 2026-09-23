# AGENTS.md

Guidance for AI agents and contributors working in this repository.

## Project

- PHP client/library for MySQL, built on `mysqli`/`mysqlnd`
- PSR-4 autoload namespace `sql\` mapped to `src/sql`
- Currently supports PHP 8.3
  - supported MySQL 5.7.8+, 8.0; MariaDB not yet compatible
- MIT licensed, authored by Sergei Shilko <contact@sshilko.com>

## Repository layout

- `src/sql/` - library source, PSR-4 namespace `sql\`
- `test/` - PHPUnit tests, fixtures, config
- `build/` - quality tool configs, Dockerfiles, docker-compose
- `.github/workflows/` - CI actions (PHPUnit, PHPCS, PHPStan, Psalm, Phan, PHPMD, PDepend, PHPDoc, Docker)
- `examples/` - usage examples
- `plans/` - implementation plans for upcoming work

## Development workflow

CI and quality tooling runs inside Docker containers (see `CONTRIBUTING`). The
default runtime is `app.php83` (PHP 8.3.33 with mysqli, pcntl, ast, xdebug,
opcache); MySQL 8.0 runs as `mysql80`. Do not run the quality tools directly on
a Windows host: the host PHP interpreter is not the supported runtime and
breaks several tools.

Start the containers:

- `docker compose up -d app.php83 mysql80`
  (uses `.env`: project name `app`,
  `COMPOSE_FILE=build/docker-compose.yaml:test/docker-compose.yaml`)

Run tests and quality checks inside the container:

- `docker compose exec -w /app app.php83 composer app-quality` - all quality gates
- `docker compose exec -w /app app.php83 composer app-phpunit-mysql80` - PHPUnit against MySQL 8.0
- `docker compose exec -w /app app.php83 composer app-phpunit-mysql80 -- --filter <TestFilter>` - filtered tests
- `docker compose exec -w /app app.php83 pre-commit run --all-files --config build/.pre-commit-config.yaml` - pre-commit hooks

Or locally after `composer install`: `composer app-phpunit`, `composer app-pre-commit`, `composer app-quality`.

### Full verification run

Once the containers are up, run everything in this order:

- `docker compose exec -w /app app.php83 composer app-quality` - PHPCS/CBF,
  PHPCPD, PDepend, PHPMD, PHPStan, Psalm alter, Psalm taint, Psalm main
- `docker compose exec -w /app app.php83 composer app-phan` - Phan (not reached
  when `app-quality` stops early)
- `docker compose exec -w /app app.php83 composer app-phpunit-mysql80` - PHPUnit
  against MySQL 8.0

A green run means:

- PHPCS, PHPCPD, PDepend, PHPMD, PHPStan, Psalm alter/taint, Psalm main, Phan
  and PHPUnit report no errors (284 tests, 877 assertions); Psalm infers types
  for 100% of the codebase.
- Phan exits 0 with six informational stub/type findings in `MydbEnvironment`,
  `MydbExpressionInterface`, `MydbMysqli` and `MydbQueryBuilder`.
- PHPUnit emits the one expected `mysqli::real_connect()` "Connection timed out"
  warning from the failure-path tests in `ExceptionTest`.

### Static analysis gotchas

- `src/sql/pcntl-polyfill.php` is a Windows-only fallback for `pcntl_signal()` /
  `posix_kill()` and is excluded from PHPStan (`build/phpstan.neon`
  `excludePaths`), Psalm (`build/psalm.xml` `ignoreFiles`) and PHPUnit coverage
  (`test/phpunit.xml` source exclude). Inside the file, PHPCS rules are relaxed
  via scoped `// phpcs:disable` comments (Slevomat `DisallowSuperGlobalVariable`,
  PSR1 `SideEffects`).
- Composer 2 loads `autoload.files` inside a closure, so a top-level `$x = []`
  is closure-local and never a global. The polyfill seeds `$GLOBALS[...]` at
  load time so it exists before PHPUnit takes its first global-state snapshot
  (`beStrictAboutChangesToGlobalState` and `failOnRisky` are enabled).
- PHPCS and the pre-commit hooks skip `examples/` (`phpcs-ruleset.xml`
  `<exclude-pattern>*/examples/*</exclude-pattern>` and top-level pre-commit
  `exclude: ^(vendor/.*|examples/.*)$`); demos are not gated.
- PHPMD `<exclude-pattern>` entries only work as direct children of `<ruleset>`;
  `@SuppressWarnings` annotations do not work on global functions.
- The default `composer app-psalm` invocation aborts in this image with
  "Fatal Error Insufficient shared memory!". Working invocation:

  ```
  php -n -d zend_extension=opcache -d opcache.enable_cli=1 -d opcache.jit=0202 \
    -d opcache.jit_buffer_size=100M -d extension=mysqli -d extension=pcntl \
    -d extension=ast -d extension=xdebug ./vendor/bin/psalm.phar \
    --config build/psalm.xml --no-cache --threads=1
  ```

- Phan needs `ext-ast`, which is installed in the container (`composer app-phan`).
- Calling `docker exec` from Windows PowerShell mangles `$?`, variables, and
  nested quotes; prefer simple top-level commands or a bash wrapper.
- The full PHPUnit run emits one expected `mysqli::real_connect()`
  "Connection timed out" warning from the failure-path tests in `ExceptionTest`.

## Conventions

- PHP code: `declare(strict_types = 1);`, file header license block (see existing files), PSR-12 via PHPCS
- Every source file begins with the standard MIT header used across `src/sql/`
- Prefer native type hints, typed properties, constructor property promotion, and union types
- Static analysis is enforced: PHPStan, Psalm, Phan, PMD, PHPCS. New code must keep these passing
- Do not use emojis in documentation or code comments
- Tests live in `test/phpunit/` and follow existing naming/patterns
- Composer scripts are the canonical entry points for all quality gates

## Version policy

- `composer.json:"php"` requirement and supported versions are reflected in README "Compatibility" section
- Adding support for a new PHP runtime requires updating (in lockstep):
  1. `composer.json` platform requirement
  2. `build/Dockerfile.phpXX` + `build/docker-compose.yaml`
  3. `.github/workflows/phpunitXX.yml`
  4. README compatibility + badges
  5. `CONTRIBUTING` container list

## Working on plans

- Plans for large or multi-step work are committed under `plans/` before implementation
- A plan defines scope, motivation, concrete steps, affected files, and verification
- Implement plan steps in separate commits / merge requests

## Communication

- Commit in the style of existing history; scope changes deliberately
- Keep responses and commit messages concise and factual
