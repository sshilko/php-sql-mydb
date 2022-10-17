<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
-->
MyDb - Component
=================
Simple class to work with MySQL database.

<p align="center">
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/v/stable" alt="Latest Stable Version"></a>
	<a href="https://packagist.org/packages/sshilko/php-sql-mydb/stats"><img src="https://poser.pugx.org/sshilko/php-sql-mydb/downloads" alt="Total Downloads"></a>
	<a href="https://choosealicense.com/licenses/mit/"><img src="https://poser.pugx.org/phpstan/phpstan/license" alt="License"></a>
	<a href="https://phpstan.org/"><img src="https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat" alt="PHPStan Enabled"></a>
</p>


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
  - non-persistent connections
  - utf8mb4 charset
  - UTC timezone
  - 2hours non-interactive connection timeout
  - read-only transaction optimizations: isolation level READ COMMITTED, TRANSACTION READ ONLY
  - php error-reporting E_ALL & ~E_WARNING & ~E_NOTICE
  - Catch and report errors from mysqli function calls
    - MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT ^ MYSQLI_REPORT_INDEX
- Configurable connection retry
- Better memory usage - copy results from the mysqlnd buffer into the PHP variables MYSQLI_STORE_RESULT_COPY_DATA
- unit-tested, static analysed codebase source

#### What is the best use-case for this library

- MySQL server and CRUD php application
- No prepared statements
- No dependencies
- No abstractions
- No reflections
- Minimum code, maximum-performance

MySQL database is fast, reliable and scalable, php runtime is the same.

Measure your app performance with real-world datasets and organic user load.

Optimize for your use-case, focus on bottlenecks, there is no ~~NoSQL~~ silver bullet.

Do not optimize early, resources like CPU & memory are cheap, build an architecture instead.

##### Compatibility

- [+] PHP 7.4
- [+] MySQL <-> PHP via TCP
- [+] MySQL >=5.7.8
- [?] MySQL 8.0

##### Roadmap 2022-2023

- Psalm etc. free ci/cd & github badges
- PHP documentor (phpcs generator documentation)
- Test coverage report https://coveralls.io
- More tests
- Usage example
- phan/phan https://github.com/phan/phan
- exacat io 
- https://codeclimate.com
- https://app.codecov.io/
- https://shepherd.dev/
- https://github.com/EdgedesignCZ/phpqa
- https://scrutinizer-ci.com
- https://github.com/pdepend/pdepend
- https://github.com/sebastianbergmann/phpcpd
- phpmnd https://github.com/povils/phpmnd
- Performance benchmarks
- Run tests against MySQL8
- PHP8 compatiblity
- Packagist export
- Analytics & metrics collector
- Retry options
- Compression flag ON/OFF

#### Installation

```
composer install --no-dev
```

#### Development setup

- Install [PHP](https://www.php.net/) & [Composer](https://getcomposer.org/) & [Docker Compose](https://docs.docker.com/compose/install/)
- Install [PHPStorm IDE](https://www.jetbrains.com/phpstorm/) with [PHP Inspections](https://github.com/kalessil/phpinspectionsea) or [VSCode IDE](https://code.visualstudio.com/)

```
docker-compose build
docker-compose up -d
docker-compose exec app composer install --dev
docker-compose exec app composer dump-autoload
...
docker-compose stop
```

#### Apply coding standards to modified files

`docker-compose exec app composer pre-commit`

#### Run [PHPUnit](https://phpunit.de) test suite

```
docker-compose exec app composer phpunit
```

#### Run PHP Code Beautifier & PHP [CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) (all files)

```
docker-compose exec app composer phpcbf
docker-compose exec app composer phpcs
```

#### Run [Psalm](https://psalm.dev) - a static analysis tool for PHP (all files)

```
docker-compose exec app composer psalm
docker-compose exec app composer psalm-alter
docker-compose exec app composer psalm-taint
```

#### Run [PHPStan](https://phpstan.org) - PHP Static Analysis Tool (all files)

`docker-compose exec app composer phpstan`

#### Run [PHPMD](https://phpmd.org) - PHP Mess Detector

`docker-compose exec app composer phpmd`

