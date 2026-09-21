<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# AGENTS.md

Guidance for AI agents and contributors working in this repository.

## Project

- PHP client/library for MySQL, built on `mysqli`/`mysqlnd`
- PSR-4 autoload namespace `sql\` mapped to `src/sql`
- Currently supports PHP 8.0 - 8.2 (incrementally moving to PHP 8.3)
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

CI and quality tooling usually runs inside Docker containers
(`app.php80`, `app.php81`, `app.php82`), see `CONTRIBUTING`.

Run quality checks and tests:

- `composer install`
- `composer app-quality` - PHPCS/CBF, PHPMD, PHPCPD, PDepend, PHPStan, Psalm, Phan
- `composer app-phpunit` - PHPUnit against MySQL 5.7 and 8.0
- `composer app-phpunit-mysql80 -- --filter <TestFilter>` - single test filter
- `composer app-pre-commit` - pre-commit hooks from `build/.pre-commit-config.yaml`

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