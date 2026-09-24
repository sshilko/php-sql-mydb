<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Psalm 100% type inference and zero errors

## Motivation

The current state (verified 2026-09-23, Psalm 6.18.0 on PHP 8.3.33,
`composer app-psalm`) reports:

- `2 errors found`, exit code 2:
  1. `MoreSpecificImplementedParamType` at `src/sql/MydbEnvironment.php:107`
  2. `TypeDoesNotContainType` at `src/sql/MydbMysqli.php:605`
- `Psalm was able to infer types for 99.8586% of the codebase` with two
  remaining mixed statements:
  - `src/sql/Mydb.php` - 1 mixed (99.770%)
  - `src/sql/MydbLogger.php` - 1 mixed (99.363%)

Goal: 0 errors and 100.0000% inference, while keeping all other quality gates
green. Note that Psalm counts mixed statements even when the issue is
suppressed (the mixed-count increment happens before suppression is checked),
so adding `@psalm-suppress` alone does not raise the percentage. The mixed
types must actually be eliminated.

## Scope

- Fix the two Psalm errors at their root cause.
- Remove the last two mixed statements so every file reports 100%.
- Keep PHPCS, PHPMD, PHPCPD, PDepend, PHPStan, Psalm taint, Phan and PHPUnit
  green (per AGENTS.md "Full verification run").

## Out of scope

- Public API / BC changes for consumers.
- Adding new `@psalm-suppress` annotations (they do not help the infer %).
- Changing Psalm version or error level.

## Steps

### 1. Fix `MoreSpecificImplementedParamType` in `MydbEnvironment`

- File: `src/sql/MydbEnvironment.php`
- The `@param (callable(int, string, string=, int=, array<array-key, mixed>=):bool|null)|null`
  docblock on `set_error_handler()` (line 101) declares a parameter type more
  specific than the contract in `src/sql/MydbEnvironmentInterface.php:45`
  (`?callable`).
- Fix: drop the over-specific `@param` docblock line; keep the native
  `?callable $callback` type and the remaining annotations (`@see`,
  `@SuppressWarnings`, `@phpcs`).
- Verify that `getNullErrorHandler()` (line 268) still provides a compatible
  callable for `set_error_handler($newHandler, ...)` at line 111.

### 2. Fix `TypeDoesNotContainType` in `MydbMysqli::getWarnings`

- File: `src/sql/MydbMysqli.php` (lines 595-618)
- Psalm's mysqli stub types `get_warnings()` as always returning
  `mysqli_warning`, but at runtime it returns `false` when there are no
  warnings, so `false === $warnings` (line 605) is legitimate code.
- Fix (pick one, preferred first):
  a. Add a type-narrowing annotation before the guard, e.g.
     `@var mysqli_warning|false $warnings` on the assignment at line 604, so
     the falsability check is valid and the `do/while` body
     (`$warnings->message`, `$warnings->next()`) stays typed.
  b. Add a Psalm stub overriding the mysqli stub, e.g. new file
     `build/psalm-stubs/mysqli.phpstub` registered via `<stubs>` in
     `build/psalm.xml`, declaring
     `public function get_warnings(): mysqli_warning|false;`.
- Keep runtime behavior unchanged.

### 3. Remove the remaining mixed statement in `Mydb.php`

- File: `src/sql/Mydb.php`
- Expected source (confirm with `--stats` after step 1-2): `query()` assigns
  `$payload = $packet->getResult()` (line 139) where
  `MydbMysqliResultInterface::getResult()` is typed only `?array`
  (`src/sql/MydbMysqli/MydbMysqliResultInterface.php:38`, impl at
  `src/sql/MydbMysqli/MydbMysqliResult.php:101`), producing a generic
  `array<array-key, mixed>` value.
- Fix: type the result array at the source
  (`array<array-key, array<array-key, (float|int|string|null)>>|null`) in both
  the interface and the implementation, then drop the now-redundant `@var`
  assertion in `Mydb.php` (lines 147-149). If the actual mixed statement is
  elsewhere, apply the same "type at the source" principle.

### 4. Remove the remaining mixed statement in `MydbLogger.php`

- File: `src/sql/MydbLogger.php`
- Expected source (confirm with `--stats`): `interpolate()` iterates
  `foreach ($context as $key => $value)` (line 251) over a parameter typed
  `array<mixed>` (PSR-3 context); the existing
  `@psalm-suppress MixedAssignment` does not lower the stats count.
- Fix: eliminate the set-aside mixed assignment, e.g. type the context as
  `array<string, mixed>` and narrow inside the loop (`is_scalar()`,
  `instanceof Stringable`) without ever assigning a raw mixed value, or
  restructure the loop to read the value only inside the guard. Keep rendered
  output identical.

## Affected files

- `src/sql/MydbEnvironment.php`
- `src/sql/MydbMysqli.php`
- `src/sql/Mydb.php`
- `src/sql/MydbLogger.php`
- `src/sql/MydbMysqli/MydbMysqliResultInterface.php`
- `src/sql/MydbMysqli/MydbMysqliResult.php`
- Possibly new `build/psalm-stubs/mysqli.phpstub` + `build/psalm.xml` (if
  option 2b is chosen)

## Verification

1. Baseline is recorded above (2 errors, 99.8586%).
2. `docker compose exec -w /app mydb-app-php83 composer app-psalm`
   - Expect: `0 errors found`, exit 0.
   - Expect: `100.0000%` total, every file `100.000% (0 mixed)`.
3. Full gates in order (per AGENTS.md):
   - `docker compose exec -w /app mydb-app-php83 composer app-quality`
   - `docker compose exec -w /app mydb-app-php83 composer app-phan`
   - `docker compose exec -w /app mydb-app-php83 composer app-phpunit-mydb-mysql80`
4. Update AGENTS.md "Full verification run" notes if the two informational
   Psalm gaps and the 99.8586% figure are mentioned anywhere (currently they
   are described as expected findings; they must no longer be emitted).