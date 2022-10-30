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
<p align="center" cellpadding="0" cellspacing="0">
	<img src="https://img.shields.io/badge/status-active-success" alt="Project status - active">
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/v/stable" alt="Latest Stable Version"></a>
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb/stats"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/downloads" alt="Total Downloads"></a>
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/require/php" alt="PHP Required Version"></a>
	<a href="https://choosealicense.com/licenses/mit/"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/license" alt="MIT License"></a>
    <a href="https://psalm.dev/docs/running_psalm/command_line_usage/#shepherd"><img src="https://shepherd.dev/github/sshilko/php-sql-mydb/coverage.svg" alt="Psalm Coverage"></a>
    <img src="https://hits.seeyoufarm.com/api/count/incr/badge.svg?url=https%3A%2F%2Fgithub.com%2Fsshilko%2Fphp-sql-mydb&count_bg=%2379C83D&title_bg=%23555555&icon=&icon_color=%23E7E7E7&title=hits&edge_flat=false"/>
    <br/>
    <img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/badges/phpunit-coverage-badge.svg" alt="PHPUnit coverage">
    <img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/badges/phpunit-coverage-badge-classes.svg" alt="PHPUnit classes coverage">
    <img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/badges/phpunit-coverage-badge-lines.svg" alt="PHPUnit lines coverage">
    <img src="https://raw.githubusercontent.com/sshilko/php-sql-mydb/badges/phpunit-coverage-badge-methods.svg" alt="PHPUnit methods coverage">
    <br/>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/psalm.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/psalm.yml/badge.svg" alt="Psalm build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phan.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phan.yml/badge.svg" alt="Phan build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpunit.yml/badge.svg" alt="PHPUnit build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpmd.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpmd.yml/badge.svg" alt="PHPMd build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpstan.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpstan.yml/badge.svg" alt="PHPStan build"></a>
    <a href="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpcs.yml"><img src="https://github.com/sshilko/php-sql-mydb/actions/workflows/phpcs.yml/badge.svg" alt="PHPCodeSniffer build"></a>
</p>

Simple library to work with MySQL database.

### How this client helps you talk SQL to MySQL server

- Quality production defaults
  - TRADITIONAL [sql mode](https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sqlmode_traditional)
  - autocommit 0, require commit to accept a transaction
  - automatically commit when gracefully shutting down
  - 90s client-side read-timeout for any query
  - 89s server-side SELECT query timeout
  - 5s client-side connect-timeout
  - respect php-fpm client disconnect and script execution shutdown
  - increased mysqlnd MYSQLI_OPT_NET_READ_BUFFER_SIZE and MYSQLI_OPT_NET_CMD_BUFFER_SIZE
  - automatic mysqlnd.net_read_timeout value
  - `utf8mb4` character set
  - `UTC` timezone
  - 2 hours non-interactive connection timeout
  - read-only transaction optimizations: isolation level READ COMMITTED, TRANSACTION READ ONLY
  - php error-reporting E_ALL & ~E_WARNING & ~E_NOTICE
  - Catch and report errors from mysqli function calls
    - MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT ^ MYSQLI_REPORT_INDEX
  - Catch SIGTERM, SIGINT, SIGHUP termination signals
- Configurable connection retry
- Better memory usage - copy results from the mysqlnd buffer into the PHP variables MYSQLI_STORE_RESULT_COPY_DATA
- unit-tested, static analysed codebase source

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

##### Is it compatible?

- [+] PHP 7.4
- [+] MySQL <-> PHP via TCP
- [+] MySQL >=5.7.8
- [?] MySQL 8.0

##### Roadmap 2022-2023

- execute command/query via events, unit-tests against raw SQL generator and events objects
- 90% tests coverage
- usage example
- run phpunit tests against MySQL8
- run phpunit tests against PHP8
- Pluggable M.E.L.T (metrics, events, logs, traces)
- Packagist export & release

#### Installation

```
composer install --no-dev
```

#### Development setup

- Install [PHP](https://www.php.net/) & [Composer](https://getcomposer.org/) & [Docker Compose](https://docs.docker.com/compose/install/)
- Install [PHPStorm IDE](https://www.jetbrains.com/phpstorm/) with [PHP Inspections](https://github.com/kalessil/phpinspectionsea) or [VSCode IDE](https://code.visualstudio.com/)

```
docker-compose build
docker-compose up -d --no-build --wait
docker-compose exec -T app composer install --dev
...
docker-compose stop
```

#### Apply coding standards to modified files

`docker-compose exec app composer app-pre-commit`

```
docker-compose exec app composer app-phpcbf  &&
docker-compose exec app composer app-phpcs   &&
docker-compose exec app composer app-phpmd   &&
docker-compose exec app composer app-phpstan &&
docker-compose exec app composer app-psalm   && 
docker-compose exec app composer app-phan    &&
echo 'Looks good' 
```

#### Run [PHPUnit](https://phpunit.de) test suite

```
docker-compose exec app composer app-phpunit
```

#### Run PHP Code Beautifier & PHP [CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) (all files)

```
docker-compose exec app composer app-phpcbf
docker-compose exec app composer app-phpcs
```

#### Run [Psalm](https://psalm.dev) - a static analysis tool for PHP (all files)

```
docker-compose exec app composer app-psalm
docker-compose exec app composer app-psalm-alter
docker-compose exec app composer app-psalm-taint
docker-compose exec app composer app-psalm-shepherd
```

#### Run [PHPStan](https://phpstan.org) - PHP Static Analysis Tool (all files)

`docker-compose exec app composer app-phpstan`

#### Run [PHPMD](https://phpmd.org) - PHP Mess Detector

```
docker-compose exec app composer app-phpmd
```

#### Run [Phan](https://github.com/phan/phan) - PHP Phan static analyzer

```
docker-compose exec app composer app-phan
```

#### Run [phpDocumentor](https://www.phpdoc.org) - [phpDocumentor](https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/)

```
docker-compose exec app composer app-phpdoc
```

#### Run [PHPCPD](https://github.com/sebastianbergmann/phpcpd) - PHPCPD Copy/Paste Detector (CPD) for PHP code

```
docker-compose exec app composer app-phpcpd
```

#### Run [Pdepend](https://pdepend.org) - PHP quality of design - extensibility, reusability and maintainability

```
docker-compose exec app composer app-pdepend
```
