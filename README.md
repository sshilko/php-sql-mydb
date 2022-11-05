<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

 @license https://opensource.org/licenses/mit-license.php MIT
-->
MyDb - Component
=================
<p align="left">
	<img src="https://img.shields.io/badge/status-active-success" alt="Project status - active">
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/v/stable" alt="Latest Stable Version"></a>
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb/stats"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/downloads" alt="Total Downloads"></a>
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/require/php" alt="PHP Required Version"></a>
	<a href="https://choosealicense.com/licenses/mit/"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/license" alt="MIT License"></a>
    <a href="https://psalm.dev/docs/running_psalm/command_line_usage/#shepherd">
    <img src="https://shepherd.dev/github/sshilko/php-sql-mydb/coverage.svg" alt="Psalm Coverage"></a>
    <img src="https://hits.seeyoufarm.com/api/count/incr/badge.svg?url=https%3A%2F%2Fgithub.com%2Fsshilko%2Fphp-sql-mydb&count_bg=%2379C83D&title_bg=%23555555&icon=&icon_color=%23E7E7E7&title=hits&edge_flat=false"/>
    <img src="https://img.shields.io/github/languages/code-size/sshilko/php-sql-mydb" alt="Code size">
    <br />
    <img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/badges/phpunit-coverage-badge.svg" alt="PHPUnit coverage" />
    <img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/badges/phpunit-coverage-badge-classes.svg" alt="PHPUnit classes coverage" />
    <img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/badges/phpunit-coverage-badge-lines.svg" alt="PHPUnit lines coverage" />
    <img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/badges/phpunit-coverage-badge-methods.svg" alt="PHPUnit methods coverage" />
    <br/>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/psalm.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/psalm.yml/badge.svg" alt="7.4 Psalm build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phan.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phan.yml/badge.svg" alt="7.4 Phan build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpmd.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpmd.yml/badge.svg" alt="7.4 PHPMd build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpstan.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpstan.yml/badge.svg" alt="7.4 PHPStan build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpcs.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpcs.yml/badge.svg" alt="7.4 PHPCodeSniffer build"></a>
    <br/>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit.yml/badge.svg" alt="7.4 PHPUnit build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit80.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit80.yml/badge.svg" alt="8.0 PHPUnit build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit81.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit81.yml/badge.svg" alt="8.1 PHPUnit build"></a>
    <br/>
    </p>
    

</p>

Simple library to work with MySQL database

#### Installation

```
composer require sshilko/php-sql-mydb:@dev
```

##### Compatibility

- PHP 7.4, 8.0, 8.1
- MySQL 5.7, 8.0

### How this client helps you talk SQL to MySQL server

- Make MySQL behave like a “traditional” SQL database system
  - `TRADITIONAL` mode, a simple description of this mode is [“give an error instead of a warning”](https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sqlmode_traditional)
- Friendly transactions
  - `autocommit = 0`
  - explicit `commit` [on gracefull shutdown](https://dev.mysql.com/doc/refman/5.7/en/innodb-autocommit-commit-rollback.html)
- Explicit timeouts  
  - 05 seconds `client-side` connect-timeout
  - 89 seconds `server-side` SELECT query timeout
  - 90 seconds `client-side` read-timeout for any query
  - 7200 seconds non-interactive connection `idle timeout`
  - `mysqlnd.net_read_timeout`
  - respect client disconnect in php-fpm `function.ignore-user-abort.php`
- Performance boost
  - increased `MYSQLI_OPT_NET_READ_BUFFER_SIZE`
  - increased `MYSQLI_OPT_NET_CMD_BUFFER_SIZE`
  - read-only InnoDB [optimizations](https://dev.mysql.com/doc/refman/5.6/en/innodb-performance-ro-txn.html)
  - async command execution
  - move mysql resultset to PHP userspace memory `MYSQLI_STORE_RESULT_COPY_DATA`
  - use of `fetch_all` from [PHP Mysql native driver](https://www.php.net/manual/en/intro.mysqlnd.php)
- UTF-8
  - `utf8mb4` character set
  - `UTC` timezone
- Quality error handling
  - PHP default error-reporting `E_ALL & ~E_WARNING & ~E_NOTICE`
  - MySQL default error-reporting `MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT ^ MYSQLI_REPORT_INDEX`
  - `SIGTERM, SIGINT, SIGHUP` signals trap
  - connection retry
- PHPUnit & Static code analysis
  - unit-tested, static analysed codebase

#### What is the best use-case for this library

- MySQL server
- High performance, low-latency data-intensive applications
- API first applications with existing user-input marshalling
- No prepared statements requirement
- No 3rd party dependencies
- Minimum abstractions, no ORM no ActiveRecord patterns
- No run-time/compile-time steps
- Minimum code, maximum-performance

#### Why this library exists

* MySQL database is fast, reliable and scalable, php runtime is the same
* Value developers time and do not add complexity where possible
* Measure app's performance with real-world datasets and organic load
* Optimize for my use-case, focus on bottlenecks, remember that there is no ~~NoSQL~~ silver bullet
* Do not optimize early - resources like CPU, memory are cheap
* Focus on building architecture, learn from others and improve over time

##### Roadmap 2022-2023

- refactor sql builder into separate concern/object
- add repository helper (not active record or ORM)
- usage example
- execute command/query via events, unit-tests against raw SQL generator and events objects
- Pluggable M.E.L.T (metrics, events, logs, traces)
- Packagist export & release

#### Development setup

- Install [PHP](https://www.php.net/) & [Composer](https://getcomposer.org/) & [Docker Compose](https://docs.docker.com/compose/install/)
- Install [PHPStorm IDE](https://www.jetbrains.com/phpstorm/) with [PHP Inspections](https://github.com/kalessil/phpinspectionsea) or [VSCode IDE](https://code.visualstudio.com/)

```
docker-compose build
docker-compose up -d
docker-compose exec -T app.php composer install
...
docker-compose stop
```

PHP runs in containers, commands can be executed against any of
- PHP7.4 - *app.php*
- PHP8.0 - *app.php80*
- PHP8.1 - *app.php81*

example
```
docker-compose exec %php-container% composer %composer-script%
```

#### How to check code quality before commit

```
# only modified files
git add -A
docker-compose exec app.php composer app-pre-commit
git commit -m "message-placeholder"
```
```
# all codebase
docker-compose exec app.php composer app-code-quality 
```

##### Run [PHPUnit](https://phpunit.de) test suite

```
docker-compose exec app.php composer app-phpunit
docker-compose exec app.php composer app-phpunit -- --filter SelectTest
```

##### Run PHP Code Beautifier & PHP [CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) (all files)

```
docker-compose exec app.php composer app-phpcbf
docker-compose exec app.php composer app-phpcs
```

##### Run [Psalm](https://psalm.dev) - a static analysis tool for PHP (all files)

```
docker-compose exec app.php composer app-psalm
docker-compose exec app.php composer app-psalm-alter
docker-compose exec app.php composer app-psalm-taint
docker-compose exec app.php composer app-psalm-shepherd
```

##### Run [PHPStan](https://phpstan.org) - PHP Static Analysis Tool (all files)

```
docker-compose exec app.php composer app-phpstan`
```

##### Run [PHPMD](https://phpmd.org) - PHP Mess Detector

```
docker-compose exec app.php composer app-phpmd
```

##### Run [Phan](https://github.com/phan/phan) - PHP Phan static analyzer

```
docker-compose exec app.php composer app-phan
```

##### Run [phpDocumentor](https://www.phpdoc.org) - [phpDocumentor](https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/)

```
docker-compose exec app.php composer app-phpdoc
```

##### Run [PHPCPD](https://github.com/sebastianbergmann/phpcpd) - PHPCPD Copy/Paste Detector (CPD) for PHP code

```
docker-compose exec app.php composer app-phpcpd
```

##### Run [Pdepend](https://pdepend.org) - PHP quality of design - extensibility, reusability and maintainability

```
docker-compose exec app.php composer app-pdepend
```

#### [Maintainers](MAINTAINERS)

* Sergei Shilko <contact@sshilko.com>