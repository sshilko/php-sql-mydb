# MyDb — a simple, fast, dependency-free PHP client for MySQL

<p align="left">
	<a href="https://github.com/sshilko/php-sql-mydb/actions"><img src="https://img.shields.io/badge/status-active-success" alt="Project status - active"></a>
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/v/stable" alt="Latest Stable Version"></a>
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb/stats"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/downloads" alt="Total Downloads"></a>
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/require/php" alt="PHP Required Version"></a>
	<a href="https://choosealicense.com/licenses/mit/"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/license" alt="MIT License"></a>
	<a href="https://psalm.dev/docs/running_psalm/command_line_usage/#shepherd"><img src="https://shepherd.dev/github/sshilko/php-sql-mydb/coverage.svg" alt="Psalm Coverage"></a>
	<img src="https://img.shields.io/github/languages/code-size/sshilko/php-sql-mydb" alt="Code size">
	<br />
	<img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/pages/php/phpunit83/phpunit-coverage-badge.svg" alt="PHPUnit coverage" />
	<img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/pages/php/phpunit83/phpunit-coverage-badge-lines.svg" alt="PHPUnit lines coverage" />
	<img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/pages/php/phpunit83/phpunit-coverage-badge-classes.svg" alt="PHPUnit classes coverage" />
	<img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/pages/php/phpunit83/phpunit-coverage-badge-methods.svg" alt="PHPUnit methods coverage" />
	<br />
	<a href="https://sshilko.com/php-sql-mydb/php/phpunit/html/"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit83.yml/badge.svg" alt="8.3 PHPUnit build"></a>
	<a href="https://sshilko.com/php-sql-mydb/php/phpstan/"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpstan.yml/badge.svg" alt="8.3 PHPStan build"></a>
	<a href="https://sshilko.com/php-sql-mydb/php/psalm/"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phppsalm.yml/badge.svg" alt="8.3 Psalm build"></a>
	<a href="https://sshilko.com/php-sql-mydb/php/phan/"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpphan.yml/badge.svg" alt="8.3 Phan build"></a>
	<a href="https://sshilko.com/php-sql-mydb/php/phpmd/"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpmd.yml/badge.svg" alt="8.3 PHPMd build"></a>
	<a href="https://sshilko.com/php-sql-mydb/php/phpcs/"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpcs.yml/badge.svg" alt="8.3 PHPCodeSniffer build"></a>
	<a href="https://sshilko.com/php-sql-mydb/php/phpdoc/"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpdoc.yml/badge.svg" alt="8.3 PHPDocumentor build"></a>
	<a href="https://sshilko.com/php-sql-mydb/php/pdepend/"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phppdepend.yml/badge.svg" alt="8.3 Pdepend build"></a>
</p>

**MyDb is a small, production-hardened PHP client for MySQL.** It speaks raw SQL at
native `mysqli`/`mysqlnd` speed — no ORM, no query-language abstraction, no framework
lock-in. You get a clean, fully typed API wrapped around opinionated defaults that make
MySQL behave like a *traditional* SQL database: strict by default, explicit transactions,
sanely bounded timeouts, and `utf8mb4`/UTC everywhere.

Think of it as a thin, well-tested layer between your PHP code and MySQL — nothing more,
nothing less.

> **Simple** to learn, **fast** to run, **safe** by default — the SQL client you stop
> thinking about. 🐬

## Table of contents

- [🎯 Why MyDb? Pros and cons](#why-mydb-pros-and-cons)
- [✨ Highlights](#highlights)
- [📦 Installation](#installation)
- [🏷️ Versioning / Releases](#versioning-releases)
- [🚀 Quick start](#quick-start)
- [🔌 Everything is injectable](#everything-is-injectable)
- [🛡️ Safe defaults](#safe-defaults)
- [⚡ Performance](#performance)
- [🔧 Reliability](#reliability)
- [✅ Code quality](#code-quality)
- [🧩 Compatibility](#compatibility)
- [💡 Best use cases](#best-use-cases)
- [🚫 Out of scope](#out-of-scope)
- [💭 Why this library exists](#why-this-library-exists)
- [🤝 Contributing](#contributing)
- [👤 Authors and license](#authors-and-license)

## 🎯 Why MyDb? Pros and cons

| ✅ Pros | ⚠️ Cons |
| --- | --- |
| **Zero runtime dependencies beyond `psr/log`** — deterministic, auditable installs with a tiny `vendor/` footprint | A small project with a single maintainer; you are closer to the code than with a big ecosystem framework |
| **Native `mysqlnd` speed** — no query parsing, no abstraction layers between you and the driver | Raw SQL is on you: you write and maintain the queries, and you own escaping (the `escape()` helper keeps this easy) |
| **Fully typed and deeply analyzed** — 100% Psalm type inference, six static analyzers green in CI | Requires PHP >= 8.3; older LTS runtimes are not supported |
| **Unit-tested against a real MySQL 8.0 server** — 284 tests, 877 assertions, high coverage | MySQL 8.0 only today; MariaDB is not yet compatible |
| **Opinionated safe defaults** — `TRADITIONAL` SQL mode, `autocommit = 0`, explicit timeouts, `utf8mb4`, UTC | Defaults are deliberate; atypical setups may want to tune `MydbOptions` first |
| **Production-ready reliability** — signal trapping, connection retry, commit on graceful shutdown | No prepared-statement-first API; server-side binding is traded for raw speed and simplicity |
| **Interface-driven design** — every collaborator (logging, options, mysqli, environment, query builder, listener) is swappable and mockable | DML helpers cover the common cases; complex statements are written as raw SQL by design |
| **Raw SQL, no DSL** — zero learning curve if you already know SQL, and portable across MySQL deployments | Manual result mapping; there is no ORM to do it for you |

If you want an ORM, a query-language abstraction, or a full framework, look at Doctrine or
Eloquent. If you want a minimal, fast, predictable SQL client that gets out of your way,
MyDb is for you.

## ✨ Highlights

- **Raw SQL, first-class** — `select()`, `query()`, `command()`, `insert()`, `update()`,
  `delete()`, `replace()` plus helper methods for common bulk work
- **Helper DML** — array-based `insertOne()`, `insertMany()`, `updateWhere()`,
  `deleteWhere()`, `replaceOne()` with `MydbExpression` support for raw SQL fragments
- **Friendly transactions** — `autocommit = 0`, explicit `commit()` / `rollback()`,
  and an automatic commit on graceful shutdown so you never lose half a transaction
- **Readonly connections** — a first-class mode with `READ COMMITTED` isolation and
  read-only InnoDB optimizations
- **Strict by default** — `TRADITIONAL` SQL mode ("give an error instead of a warning"),
  strict MySQL error reporting, and PSR-3 logging of everything unusual
- **Explicit timeouts** — connect, read, server-side SELECT, and idle timeouts so one
  slow query cannot hang your process forever
- **Signal aware** — traps `SIGTERM`/`SIGINT`/`SIGHUP`, stops in-flight queries, and
  respects client disconnects under php-fpm
- **Zero-friction integration** — PSR-4 `sql\` namespace, PSR-3 logging, constructor
  injection, no run-time or compile-time steps

## 📦 Installation

```
composer require sshilko/php-sql-mydb
```

Requires `ext-mysqli` and `ext-mysqlnd` (bundled with the standard PHP distribution).
Composer installs the latest **stable** release, currently the `2.x` line.

## 🏷️ Versioning / Releases

| Version | Status | Released | PHP |
| --- | --- | --- | --- |
| **1.0.0** | Legacy release | 2022-12-04 | 7.4 – 8.2 |
| **2.0.0** | ✅ Latest stable release | 2024-01-03 | 8.0 – 8.2 |
| **3.x** (current) | 🔜 In development, not released yet | — | >= 8.3 |

What's new and improved in **3.x**:

- Upgraded to **PHP 8.3** — strict types, typed properties, constructor promotion, and union
  types throughout the codebase
- `ext-pcntl` is now **optional**: a bundled no-op polyfill keeps Windows and non-pcntl
  environments working (a hard requirement in `2.x`)
- New `MydbFactory` for assembling connections with library defaults, including dedicated
  read-only options (`createReadonlyOptions()`)
- Focused on **MySQL 8.0** (MySQL 5.7 support deprecated)
- **Psalm infers 100% of types with zero errors**, and a taint-analysis pass was added

## 🚀 Quick start

```php
use sql\MydbCredentials;
use sql\MydbFactory;
use sql\MydbLogger;

$credentials = new MydbCredentials('127.0.0.1', 'root', 'secret', 'app', 3306);
$mydb        = (new MydbFactory())->create($credentials, new MydbLogger());

$mydb->beginTransaction();

$id = $mydb->insertOne(['name' => 'Ada Lovelace'], 'users');            // '9' — auto-increment id
$mydb->insertMany([['Grace Hopper'], ['Linus Torvalds']], ['name'], 'users');

$rows = $mydb->select('SELECT id, name FROM users ORDER BY id ASC');

$mydb->commitTransaction();
```

Read-only replicas get a dedicated factory helper:

```php
$options = (new MydbFactory())->createReadonlyOptions();   // READ COMMITTED, autocommit on
$replica = (new MydbFactory())->create($credentials, new MydbLogger(), $options);
```

A complete runnable example lives in [`examples/example.php`](examples/example.php).

## 🔌 Everything is injectable

MyDb is assembled from small, interface-driven parts — and every single one can be
swapped, extended, or mocked through `MydbFactory::create()`. Omit any dependency and
the factory fills in production defaults, so flexibility costs nothing when you do not
need it:

```php
$mydb = (new MydbFactory())->create(
    credentials:  $credentials,       // MydbCredentialsInterface
    logger:       new MydbLogger(),   // Psr\Log\LoggerInterface
    options:      $myOptions,         // MydbOptionsInterface
    mysqli:       $myMysqli,          // MydbMysqliInterface
    environment:  $myEnvironment,     // MydbEnvironmentInterface
    queryBuilder: $myQueryBuilder,    // MydbQueryBuilderInterface
    listener:     $myListener,        // MydbListenerInterface
);
```

- **Tracing and debugging with a listener** — implement `MydbListenerInterface` (or extend
  `MydbListener`) and subscribe to lifecycle events: query begin/end and connection
  begin/end. Every event carries metadata — the SQL text, success flag, host, and
  database — and returning `false` stops dispatch. The built-in `InternalListener` logs
  connection events via your PSR-3 logger; query events are left for your own listener.
  A minimal SQL tracer:

```php
use sql\MydbEvent\InternalQueryBegin;
use sql\MydbEventMetadataInterface;
use sql\MydbListener;

final class SqlTracer extends MydbListener
{
    protected function onEvent(MydbEventMetadataInterface $event): ?bool
    {
        if (InternalQueryBegin::class === $event->getEventName()) {
            var_dump($event->getEventMetadata()['sql'] ?? null);
        }

        return null; // keep dispatching
    }
}

$mydb = (new MydbFactory())->create($credentials, listener: new SqlTracer());
```

- **Your own query builder** — `MydbQueryBuilderInterface` is the single place where SQL
  for the helper methods is constructed (`buildWhere`, `buildInsertMany`,
  `buildUpdateWhere`, `buildDeleteWhere`, escaping). Bring your own implementation to
  customize quoting, add dialect behavior, or go further with `MydbExpression`.
- **Environment dependencies are abstracted** — `MydbEnvironmentInterface` wraps the
  global PHP surface the library touches: `set_error_handler`, `error_reporting`,
  `ini_set`, `ignore_user_abort`, `mysqlnd` network timeouts, and signal trapping. The
  library never reaches into globals directly, which makes it fully testable and
  predictable on any platform.
- **Even `mysqli` itself is interfaced** — `MydbMysqliInterface` is a complete facade
  over `mysqli`/`mysqli_result`: connecting, driver options, result reading, transactions,
  and warnings. Use your own implementation to run against an alternative transport or to
  test without a live MySQL server.

## 🛡️ Safe defaults

Every connection is set up to be strict, explicit, and deterministic:

- `TRADITIONAL` and strict SQL modes — MySQL returns an **error** instead of a warning
- `autocommit = 0` with explicit `commit()` / `rollback()` on `Mydb`
- Commit is attempted on graceful shutdown (`SIGTERM`, `SIGINT`, `SIGHUP`), so in-flight
  transactions are not silently dropped
- `utf8mb4` character set and `UTC` timezone on the session
- `mysqli` error reporting configured so silent data loss is not possible
- Connection retry for resilient reconnect

## ⚡ Performance

MyDb is tuned for high-throughput, low-latency workloads:

- `MYSQLI_OPT_NET_READ_BUFFER_SIZE` and `MYSQLI_OPT_NET_CMD_BUFFER_SIZE` increased
- Resultsets moved to PHP userspace memory with `MYSQLI_STORE_RESULT_COPY_DATA`
- `fetch_all()` from `mysqlnd` for fast, allocation-friendly result materialization
- Async command execution for parallel pipelines
- `READ COMMITTED` scan/read-only InnoDB optimizations on readonly connections
- No overhead from ORM mapping or query abstraction

## 🔧 Reliability

- Connect timeout of 5 seconds, client-side read timeout of 90 seconds for any query,
  server-side SELECT timeout of 89 seconds, and a 7200-second idle timeout
- `mysqlnd.net_read_timeout` honoured on every query
- `pcntl` signal dispatch (`SIGTERM`, `SIGINT`, `SIGHUP`) cancels in-flight queries and
  shuts down cleanly; a no-op polyfill is bundled for Windows
- php-fpm client disconnect (`ignore_user_abort`) is respected
- Every failure path raises typed exceptions under `sql\MydbException` — no silent `false`

## ✅ Code quality

Code quality is not a checklist item here; it is the build. Every commit is verified by:

- **Unit tests** against a real MySQL 8.0 server — >=280 tests, >=870 assertions, with
  coverage badges generated on CI
- **Psalm** — **100% of types inferred, zero errors**, plus a taint-analysis pass and an
  auto-fix (alter) pass for type annotations
- **PHPStan** at the highest level
- **Phan** with `ext-ast`, **PHPMD**, **PHPCS (PSR-12)**, **PHPCPD** for duplication, and
  **PDepend** for architecture metrics
- **PSR-12 formatting** enforced by `phpcs`/`phpcbf` in pre-commit hooks and CI

The codebase is deliberately small: the complete library is ~26 source files, each with a
single responsibility, typed properties, constructor promotion, and PSR-12 formatting. It
is easy to read end-to-end.

## 🧩 Compatibility

- [PHP >= 8.3](https://sshilko.com/php-sql-mydb/php/)
- MySQL 8.0 (`mysqli` + `mysqlnd`) and 5.7

## 💡 Best use cases

- High-performance, low-latency, data-intensive applications
- Services that favor hand-written SQL over ORM magic
- Codebases that want a thin, auditable database layer without framework lock-in
- Projects with no prepared-statement requirement and no desire for extra dependencies
- Easy drop-in [integration](https://refactoring.guru/design-patterns/php) into existing
  applications

## 🚫 Out of scope

MyDb deliberately stays a component, not a framework. To keep focus and the codebase
minimal, it does not provide:

- Input [validation](https://symfony.com/doc/current/validation.html) or an
  [API facade](https://refactoring.guru/design-patterns/facade)
- [Object-relational mapping](https://en.wikipedia.org/wiki/Object%E2%80%93relational_mapping) (ORM)
- [Active record](https://en.wikipedia.org/wiki/Active_record_pattern)
- [Repository](https://symfony.com/doc/current/doctrine.html#querying-for-objects-the-repository) pattern
- Data import/export

Please reuse existing solutions that best fit your requirements.

## 💭 Why this library exists

- MySQL is fast, reliable, and scalable — and so is the PHP runtime
- Value developers' time; do not add complexity where it is not needed
- Measure application performance with real-world datasets and organic load
- Optimize for real bottlenecks and remember that there is no
  ~~NoSQL~~ silver bullet
- Do not optimize prematurely — CPU and memory are cheap
- Focus on architecture, learn from others, and improve over time

## 🤝 Contributing

Please read the [contributing](CONTRIBUTING) document first. All quality gates run in
Docker; see `CONTRIBUTING` for the supported workflow.

## 👤 Authors and license

- Sergei Shilko <contact@sshilko.com>

Licensed under the [MIT License](LICENSE). See also [AUTHORS](AUTHORS).