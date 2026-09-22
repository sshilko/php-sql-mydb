<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
# Plan: Full repository review and improvement

## Motivation

An end-to-end review of every file in the repository was performed (purpose,
outdatedness, improvement potential). It found:

- One genuine SQL-generation bug that is green only because the unit test
  pins the broken SQL string (`updateWhereMany` with 2+ columns).
- A `TypeError` path in `MydbRegistry::key()` for integer keys.
- `MydbLogger` that is not PSR-3 compliant, crashes under php-fpm, and breaks
  under `strict_types` on scalar messages.
- Stale tooling/CI (missed action bumps, a Docker image-tag mismatch that
  defeats the CI build cache, phantom MariaDB/5.7 references, PHPStan level 0).
- Documentation that no longer matches reality (MySQL 5.7 support, "no 3rd
  party dependencies", "no repository pattern", obsolete `app.php*` containers).
- Missing standard project files (dependabot, changelog, issue/PR templates).

This plan captures the full inventory assessment and the remediation steps in
priority order.

## Scope

- Fix confirmed bugs and their tests.
- Align CI, tooling and docs with the current state (PHP 8.3 only, MySQL 8.0
  only, MariaDB out of scope).
- Safe PHP 8.3 modernization and stale-comment hygiene across `src/`, `test/`.
- Add missing project files (`.github/dependabot.yml`, `CHANGELOG.md`,
  issue/PR templates, `SECURITY.md`).
- Keep all quality gates green: PHPCS, PHPMD, PHPCPD, PDepend, PHPStan,
  Psalm (incl. taint), Phan, PHPUnit.

## Out of scope

- Public API / BC breaks (external contracts, exception semantics).
- MariaDB compatibility (tracked separately in README roadmap).
- Dropping `mysqli`/`mysqlnd`.
- Reintroducing MySQL 5.7 support (deprecated, see commit `8f4d6dc`).

## Repository inventory (file purpose / outdated / improvement)

### Core library `src/sql/`

| File | Purpose | Outdated / issues | Improve |
|---|---|---|---|
| `Mydb.php` (709 ln) | Main facade: connection, query, transactions, events | Stale `@phpcs:disable ...ReturnTypeHint` L113-114, L399; `@todo` L229, L608; L703-704 throws `TransactionAutocommitException` for readonly-begin failure (semantically `TransactionBeginReadonlyException`; `ResourceTest.php:385` pins current type - verify intent) | Promote readonly collaborators L57-67; `str_replace` for `preg_replace("/'/", ...)` L219; guard `__destruct` throw path L594-599; extract event emission helper L540/626 |
| `MydbMysqli.php` (640 ln) | mysqli/mysqlnd facade | Stale notes L77, L154-155, L204; stale `@psalm-suppress UnusedClosureParam` L587; trailing whitespace L306 | Stop casting away null in `realConnect` L439-448 (`?int`/`?string`); guard `get_warnings()` false L604-617; consolidate 12 `MYSQLI_*` consts L56-144 |
| `MydbOptions.php` (384 ln) | Mutable options container | Dead `<8.1.0` doc branch L119-132; stale php-src `@see` pins L58-62; 5.7 doc URLs L44, L230 | XOR default report L133-135 -> `& ~`; drop unused isolation constants (see interface) |
| `MydbCredentials.php` (96 ln) | Readonly credentials VO | None - fully modernized | - |
| `MydbQueryBuilder.php` (411 ln) | SQL builder + `escape()` | **BUG L117-161 `buildUpdateWhereMany()` emits `SET` per column + single `END` -> invalid SQL for 2+ cols**; test pins broken SQL (`QueryBuilderTest.php:214,228`); `@todo` L219, L243, L347; obsolete PHP<=7.4 branch L370-384; L309 phpcs disable for arrow fn; `QueryBuilderEscapeException` used only as message formatter L406 | `str_starts_with` L353 already done; `ctype_alnum` L364; guard `IN ()` L246-287; validate `$type` in `insertOne` L100; INF/NAN -> invalid literal L353-355 |
| `MydbRegistry.php` (217 ln) | Container for `Mydb` instances | Legacy `serialize()`/`unserialize()` L51-63 (only `__serialize` needed); redundant `Traversable` L40 | **`key(): ?string` L83-91 returns `int` for integer keys -> `TypeError`**; constrain offsets or widen return type |
| `MydbEnvironment.php` (274 ln) | Global/ini/pcntl testability wrapper | Typo `$originalNandler` L253; `E_STRICT` default L105; stale psalm-var L59-63; php 7.1 blog `@see` L193-194 | - |
| `MydbLogger.php` (353 ln) | PSR-3 logger to STDOUT/STDERR | **Not PSR-3 compliant** (no placeholder interpolation L345-352); **`STDOUT`/`STDERR` defaults crash php-fpm L80**; **`formatter()` `TypeError` on scalar messages under strict_types L345-352**; **dead retry loop L51, L261-306**; `log()` ignores level L139-151 | single `write()` via match; extract named stream-writable helper L245 |
| `MydbExpression.php` | Raw SQL expression VO | - | cosmetic `#[Override]` vs Stringable |
| `MydbEvent.php` | Abstract event dispatch | Redundant `instanceof` re-check L41-49 | tighten contract |
| `MydbListener.php` | Abstract listener adapter | Clean | - |
| `MydbRepository.php` | Abstract repository | Hard-depends on concrete `MydbRegistry` L23 | depend on interface |
| `MydbException.php` | Base exception | Clean | - |
| `MydbInterface.php` | Aggregate marker interface | - | document `RemoteResourceInterface` exclusion (no `open/close`) |
| `MydbOptionsInterface.php` | Options contract | **4 of 5 isolation constants unused L28-32** | keep `READ_COMMITTED`, drop or document rest |
| `MydbCredentialsInterface.php` | Credentials contract | Clean | - |
| `MydbEnvironmentInterface.php` | Env-indirection contract | Repeated per-method phpcs disables L31-65 | single class-level disable |
| `MydbExpressionInterface.php` | Raw expression contract | Comment about Stringable L26 | `extends \Stringable` directly |
| `MydbEventInterface.php` / `MydbListenerInterface.php` / `MydbRepositoryInterface.php` | Contracts | Clean; `getDatabaseIdentifier()` only on class, not interface | add to interface |
| `MydbEventMetadataInterface.php` | Event metadata contract | `?array` too loose L20-23 | per-event `array{...}` shapes |
| `MydbQueryBuilderInterface.php` | Builder contract | `escape()` `$unescaped` untyped L69-72; `SQL_INSERT`/`SQL_REPLACE` typed consts L25-26 | native param union; consider enum |
| `MydbMysqliInterface.php` | mysqli facade contract | Stale `@phpcs:disable ...ReturnTypeHint` L84-86 | refactor by-ref `&$events` L92-94 |
| `MydbInterface/QueryInterface.php` | Query contract | **Stale `@phpcs:disable ...ReturnTypeHint` L27** | remove |
| `MydbInterface/EncoderInterface.php` | Escape contract | **`escape()` missing native param type L32**; fcqn disable via use-import L23 | add `float|int|string|MydbExpressionInterface|null` (lockstep impl) |
| `MydbInterface/TransactionInterface.php` | Transaction contract | - | document state asymmetry |
| `MydbInterface/CommandInterface.php` | Bare command contract | Clean | - |
| `MydbInterface/AdministrationStatementsInterface.php` | Reflection queries | legacy `?array` idiom L29-31 | `?array<string>` |
| `MydbInterface/DataManipulationStatementsInterface.php` | DML contract | Docblock claims "do not implicitly commit" yet includes `select()` L18-24; `updateWhereMany(): void` drops affected-rows L40; union order inconsistent L34/L55 | fix docblock; consider `?int` |
| `MydbInterface/RemoteResourceInterface.php` | Connection lifecycle | Clean | - |

### Support classes `src/sql/`

| File | Purpose | Outdated / issues | Improve |
|---|---|---|---|
| `MydbEvent/InternalEvent.php` | Internal event base | `$listeners` only psalm-typed L28 | shared `__construct(array $data)`; drop 4 duplicate constructors |
| `MydbEvent/InternalConnectionBegin/End.php`, `InternalQueryBegin/End.php` | Event DTOs | duplicate `$this->data` bodies | inherit base ctor; `final` |
| `MydbException/*` (22 files) | Marker exceptions | `TransactionBeginException.php:26` never thrown (grouping parent only); `QueryBuilderEscapeException.php:28` never thrown - used as message factory at `MydbQueryBuilder.php:406`; repeated `@category`/`@author`/`@license` noise 22x | make `QueryBuilderEscapeException extends QueryBuilderException` and throw directly (MydbQueryBuilder.php:406); keep `TransactionBeginException` as documented grouping base; strip obsolete tags |
| `MydbListener/InternalListener.php` | Default listener | `is_null()` style L46; query events silently unlogged | `null ===`; `final`; document asymmetry |
| `MydbMysqli/MydbMysqliEscapeStringInterface.php` | RealEscapeString contract | Clean (well-motivated) | - |
| `MydbMysqli/MydbMysqliResultInterface.php` | Result packet contract | `__construct(...)` declared on interface L31 (locks impls); `@access protected` L24 | drop ctor from interface; drop `@access` |
| `MydbMysqli/MydbMysqliResult.php` | Fetch-all result wrapper | `$fieldsCount` mutable L41; `getsError()` returns null when result exists L81-96 (by design, undocumented); `@access protected` L28 | `readonly` fieldsCount; add invariant comment |
| `pcntl-polyfill.php` | Windows no-op polyfill | Param `$restartSysCalls` vs real `restart_syscalls` L135 (named-arg incompatible); legacy `string` union member; `ValueError` gap; inverted docblock L130 | align param name; tighten; only define on Windows |

### Tests `test/`

| File | Purpose | Outdated / issues | Improve |
|---|---|---|---|
| `phpunit/DatabaseTestCase.php` | Base DB test case | - | - |
| `phpunit/*Test.php` (20 files) | Feature/unit tests | `QueryBuilderTest.php:214,228` **pins broken SQL**; duplicate data-provider key in `LoggerTest.php:106-113` (dropped case); reversed `assertSame` arg order; bogus `@throws \phpunit\*` docblocks; `@medium` docblocks (PHPUnit 10.5 soft-deprecated) | fix expected SQL; migrate to `#[Test]`/`#[DataProvider]`/`#[Medium]` attributes; fix assert order |
| `phpunit/MydbModernizationTest.php` | Acceptance of 8.3 modernization | L142-171 string-matches source text - **brittle, breaks on refactors** | rework to behaviour assertions |
| `phpunit.xml`, `.bootstrap.php`, `.badges.php` | Config/badges | creds root/root in fixture | - |
| `docker-compose.yaml` | DB test services | mysql 5.7 service commented out L87-97; maria10 commented out L111-121 (scripts reference it) | remove comments or restore services |
| `fixtures/mysql/*.sql` | Schema/data fixtures | - | - |

### Tooling, CI, docs (root + build/ + .github/)

| File | Purpose | Outdated / issues | Improve |
|---|---|---|---|
| `composer.json` | Manifest/scripts | `require: composer-plugin-api ^2` L154 (odd for library); `require-dev: ext-posix` L133 (blocks Windows install); `psalm/phar` pinned exactly `6.0.0` L141 (**6.16.x current**); dead `app-phpunit-maria10*` scripts L92-106; `app-quality` runs auto-rewriters (phpcbf, psalm-alter) | remove plugin-api and ext-posix; bump psalm `^6.16`; drop maria scripts (or restore maria10 service); keep rewriters behind explicit scripts |
| `composer.lock` | Lockfile | matches above | regenerate after bumps |
| `build/Dockerfile.php83` | Runtime image | dead openjdk comment L16; `phpDocumentor.phar` unpinned L70 | remove comment; pin phar checksum/version |
| `build/docker-compose.yaml` | Dev compose | commented legacy php74-82 services L86-121; no DB services (healthcheck L25 pings `mysql80` that only exists in `test/docker-compose.yaml`) | drop commented blocks |
| `build/.pre-commit-config.yaml` | Pre-commit | `pre-commit-hooks` rev `v4.0.1` L61 (v5/v6 current) | bump |
| `build/phpstan.neon` | PHPStan | **`level: 0` L11** (lowest); bootstraps tests but excludes them | raise level (5+), iterate findings |
| `build/psalm.xml` | Psalm | level 1, strict - good | - |
| `build/phan.php` | Phan | targets 8.3 L49 | - |
| `build/phpcs-ruleset.xml` | PHPCS | `TypeHintDeclaration.*` excludes L43-45 are **dead** (sniff removed in slevomat 8); duplicate exclude L43/L45; full-slevomat include uses deprecated `UnionTypeHintFormat` (removed in slevomat 9) | reference only needed sniffs; drop dead excludes; plan PHPCS 4/slevomat 9 upgrade |
| `build/pdepend.xml`, `php.ini`, `README.md`, `tmp/.gitignore` | Tool backend | php.ini has obsolete `always_populate_raw_post_data`/`enable_post_data_reading` | trim |
| `.github/workflows/*.yml` (11 files) | CI | `phpcs.yml:46` action/cache still v3 (**missed in v3->v5 bump**); `github-pages.yml:35` checkout v3; `github-pages.yml:25` cron `0 * * * 0` (hourly on Sundays); `opencode.yml:31` checkout v6 (rest v5); `opencode.yml:36` floating `@latest`; `setup-buildx@v2` (php-docker.yml:28, phpunit83.yml:28); `setup-python@v3` (phpcs/phpstan); **image-tag mismatch `app/php` vs `app/php83`** in `phpdoc.yml:38`/`phppdepend.yml:38` (defeats cache); `phppdepend.yml:64` step named "Docker run PHPUnit with MySQL5.7"; 9.5 docs comment phpdoc.yml:61; no PR trigger (push: master only) | bump actions; fix tag; rename step; add `pull_request`; add `~/.composer/cache` cache |
| `AGENTS.md` | Agent guidance | **MySQL 5.7.8+ L10 stale**; `app.php80/81/82` L25 stale; `app-phpunit` "5.7 and 8.0" L31 stale; `plans/` L20 (dir was deleted) | sync with reality; keep plans/ policy (this plan restores it) |
| `CONTRIBUTING` | Contributor doc | lists deprecated php74-82 containers L14-19; `docker-compose exec mysql ...` 5.7 command L38; legacy `docker-compose` vs `docker compose` everywhere | rewrite around `docker compose` + `app.php83` |
| `README.md` | Project readme | **`MySQL >=5.7` L45 stale** (5.7 deprecated); **"No 3rd party dependencies" L84 contradicted** (`psr/log`, `composer-plugin-api`); **"Repository pattern out of scope" L95 contradicted** (`MydbRepository` shipped); 5.7/5.6 doc links L50-64; roadmap `MydbFactory` L111 | fix claims; update links; link LICENSE/SECURITY |
| `.env` | Compose env | committed; no secrets; consistent | consider git-ignore (low risk) |
| `.gitattributes` | Export attributes | `LICENSE`, `CONTRIBUTING`, `CODE_OF_CONDUCT`, `DCO`, `CITATION`, `AUTHORS` excluded from dist | keep LICENSE/CONTRIBUTING in package |
| `.dockerignore` | Build context | only `.github`, `examples` | add `.git`, `vendor`, `build/tmp`, `test/tmp`, `.env` |
| `.editorconfig` | Editor rules | `insert_final_newline = false` conflicts with `end-of-file-fixer` | set `true` |
| `.github/CODEOWNERS` | owners | placeholder comment L13 | clean up |
| `.github/FUNDING.yml` | sponsorship | fine | - |
| `.github/SECURITY` | security | **placeholder (`This is security policy`), no email, wrong filename (should be `SECURITY.md`)**, not linked from README | rewrite + rename + link |
| `examples/*` | Usage examples | `example.php:79` `assert(1, ...)` wrong signature (no-op); `example.php:30` resolves deprecated `mysql` host | fix assert; use env var |
| `AUTHORS`, `CITATION`, `DCO`, `CODE_OF_CONDUCT`, `LICENSE` | Legal/meta | `CITATION` version 1.0.0 / date 2021 stale; year drift LICENSE 2022 vs AUTHORS 2021; CoC no reporting contact | update metadata |

## Recommended missing files to create

1. `.github/dependabot.yml` - keep the many pinned GitHub Actions and composer
   dev deps current (would have caught psalm/phar 6.0.0 and the cache v3 miss).
   Scope: `github-actions` (all workflows) + `composer` (dev deps), monthly.
2. `CHANGELOG.md` - public user-facing change log; link from README; keep
   `Keep a Changelog` format.
3. `.github/ISSUE_TEMPLATE/bug_report.yml` + `.github/PULL_REQUEST_TEMPLATE.md`
   - standard OSS contribution flow (currently none exist).
4. `.github/SECURITY.md` (rename/rewrite `SECURITY`) - supported versions
   table + real contact address; add "Security" link in README.
5. `plans/review-repository-improvements.md` - this plan (restores the
   `plans/` dir referenced by AGENTS.md).

Not recommended: `phpinsights` config (toolchain already complete), a
`Makefile` (composer scripts are the canonical entry), additional analyzers.

## Concrete steps

### 1. Correctness fixes (do first; each with a regression test)

1. Fix `MydbQueryBuilder::buildUpdateWhereMany()` (MydbQueryBuilder.php:117-161)
   to emit one `SET col = CASE ... END` per column, comma-separated; close each
   CASE before the next `SET`. Update the two pinned expectations in
   `QueryBuilderTest.php:214,228` and add a real-DB multi-column case to
   `UpdateTest.php` so the generated SQL is executed, not just string-matched.
2. Fix `MydbRegistry::key()` (MydbRegistry.php:83-91) `TypeError` on integer
   offsets - return `int|string|null` or constrain offsets to strings; add a
   registry test with an integer key.
3. Fix `MydbLogger`:
   - `formatter()` return - cast scalars to string (MydbLogger.php:345-352);
   - PSR-3 placeholder interpolation `{key}` from context;
   - level-aware routing (log(ERROR) to stderr, not stdout) and single
     `write($level, ...)` via `match()`;
   - default streams safe outside CLI (php-fpm), non-fatal fallback;
   - replace the dead 3-attempt retry loop (L261-306) with a simple write.
4. `MydbMysqli::realConnect()` (L439-448): pass `?int $port`/`?string $socket`
   through instead of `(int)`/`(string)` casts.
5. `MydbMysqli::getWarnings()` (L604-617): guard `get_warnings() === false`.
6. `Mydb.php:703-704`: align exception type with intent -
   `TransactionBeginReadonlyException`; update `ResourceTest.php:385` if it
   changes observable behaviour (verify first - see inventory).
7. `MydbQueryBuilder.php:406`: make `QueryBuilderEscapeException extends
   QueryBuilderException` and throw it directly (preserves
   `catch (QueryBuilderException)`).
8. Guard edge inputs: empty value array in `buildWhere()` (`IN ()`), INF/NAN
   floats in `escape()`, `$type` validation in `insertOne()`.
9. `examples/example.php:79`: `assert(1 === $mydb->updateWhere(...), ...)`.

### 2. Tooling / CI alignment

10. `phpdoc.yml:38` / `phppdepend.yml:38`: retag `app/php-image-cache` to
    `app/php83` (compose image at build/docker-compose.yaml:124).
11. Action bumps: `phpcs.yml:46` cache v3->v5; `github-pages.yml:35` checkout
    v3->v5 and cron `0 * * * 0` -> `0 0 * * 0`; normalize opencode.yml checkout
    to v5 and pin `anomalyco/opencode/github`; setup-buildx v2->v4;
    setup-python v3->v6; nick-fields/retry v2->v3.
12. Rename stale steps/comments: `phppdepend.yml:64`, `phpdoc.yml:61`.
13. Add `pull_request` trigger to the native quality workflows (phpcs, phpmd,
    phpphan, phppsalm, phpstan) so PRs run checks; cache `~/.composer/cache`.
14. `composer.json`: remove `composer-plugin-api` and `ext-posix`; bump
    `psalm/phar` to `^6.16`; remove dead `app-phpunit-maria10*` scripts (or
    restore the maria10 compose service); regenerate lock.
15. `build/phpstan.neon`: raise `level` from 0 (target 5-6) and fix findings,
    or set an explicit baseline file while iterating.
16. `build/phpcs-ruleset.xml`: drop dead `TypeHintDeclaration.*` excludes and
    the duplicate line 45; reference selected slevomat sniffs instead of the
    whole ruleset; plan the PHPCS 4 / slevomat 9 upgrade in a follow-up.
17. `build/Dockerfile.php83`: remove dead comment; pin phpDocumentor phar.
18. `build/docker-compose.yaml`: delete commented legacy services; keep only
    `app.php83`; document the DB services live in `test/docker-compose.yaml`.
19. `.pre-commit-config.yaml`: bump `pre-commit-hooks` rev.
20. `.editorconfig`: `insert_final_newline = true`.

### 3. Documentation and claims sync

21. `README.md`: MySQL 8.0 only; fix "No 3rd party dependencies" (say "single
    PSR-3 runtime dependency"); fix "Repository pattern out of scope"; update
    doc links; link LICENSE + SECURITY + CHANGELOG.
22. `CONTRIBUTING`: rewrite for `docker compose`, drop php74-82 container
    list, drop the 5.7 `mysql` exec command.
23. `AGENTS.md`: MySQL 8.0 only; `app.php83` only; correct `app-phpunit`
    description; list `psr/log` runtime dependency.
24. `.github/SECURITY` -> `.github/SECURITY.md` with real content and contact.
25. `CITATION` metadata; `AUTHORS`/`LICENSE`/`CODE_OF_CONDUCT` year/contact
    alignment.
26. `.gitattributes`: keep LICENSE/CONTRIBUTING in dist.
27. `.dockerignore`: add `.git`, `vendor`, `build/tmp`, `test/tmp`, `.env`.

### 4. Source hygiene and safe modernization

28. Remove stale `@phpcs:disable ...ReturnTypeHint` comments: `Mydb.php:114,399`,
    `MydbMysqliInterface.php:84-86`, `MydbInterface/QueryInterface.php:27`.
29. `readonly` on `MydbMysqliResult::$fieldsCount`; `final` where sensible
    (`MydbMysqliResult`, `InternalListener`, the four Internal* events).
30. `MydbEvent/InternalEvent.php`: shared `__construct(array $data)` to remove
    the four duplicated constructors.
31. `EncoderInterface::escape()`: native param type
    `float|int|string|MydbExpressionInterface|null` (lockstep
    `Mydb.php:232` / `MydbQueryBuilder.php:351`).
32. `MydbExpressionInterface` `extends \Stringable`; `Mydb.php:219`
    `str_replace`; `MydbQueryBuilder.php:364` `ctype_alnum`; convert the two
    arrow-fn-eligible closures (MydbQueryBuilder.php:312,319).
33. `MydbEnvironment`: typo `$originalHandler`, drop `E_STRICT`.
34. Remove legacy `serialize()`/`unserialize()`, `Traversable` from
    `MydbRegistry`.
35. `pcntl-polyfill.php`: rename param to `restart_syscalls`, tighten
    `string` union + `SIG_DFL`/`SIG_IGN` validation, wrap guards in
    `PHP_OS_FAMILY === 'Windows'`.
36. Strip obsolete phpdocs: `@category`, `@access`, `@author`/`@license`
    duplicates, "As of PHP 7.2" / shell-era comments.
37. `MydbEventMetadataInterface`: per-event `array{...}` shapes;
    `InternalListener` `is_null()` -> `null ===`.
38. Recast `MydbModernizationTest.php` string-match assertions to behaviour
    assertions.
39. PHPUnit: fix duplicate data-provider key (`LoggerTest.php:106`), `assertSame`
    argument order, bogus `@throws` docblocks; move `@medium` -> `#[Medium]`.

### 5. Missing files

40. Add the four recommended files from the section above (dependabot,
    CHANGELOG, templates, SECURITY.md).

## Verification

- Local (or Docker `app.php83`):
  - `composer validate`, `php -l` on all modified source files
  - `composer app-quality` - PHPCS/CBF, PHPMD, PHPCPD, PDepend, PHPStan,
    Psalm (incl. taint), Phan - no new findings
  - `composer app-phpunit-mysql80` - PHPUnit green, including the new
    multi-column `updateWhereMany` real-DB test
  - PHPStan at the raised level green (or explicit baseline)
- CI: all workflows run on push and pull_request; phpdoc/pdepend steps boot
  the cached `app/php83` image without a full rebuild.
- Confirm zero `E_DEPRECATED`/`E_WARNING` from library sources on PHP 8.3 with
  `E_ALL`.

## Suggested commit/merge sequence

1. `plans/` - this plan
2. Correctness fixes + regression tests (section 1), split by concern
3. Tooling/CI alignment (section 2)
4. Docs sync (section 3)
5. Source hygiene (section 4), small semantic commits
6. Missing files (section 5)
7. Final verification run

Each step must keep `composer app-quality` and `composer app-phpunit-mysql80`
green independently.