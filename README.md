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

### This client wrappers helps you talk SQL to MySQL server

- Raw DB access with minimal abstraction for developer convenience
- No prepared statements
- No dependencies
- Not ActiveRecord
- Not ORM/Doctrine
- Minimum code, maximum-performance

##### Compatibility

- [+] PHP 7.4
- [+] MySQL <-> PHP via TCP
- [+] MySQL >=5.7.8
- [?] MySQL 8.0

##### Roadmap 2022-2023

- Test coverage report
- More tests
- Usage example
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
...
docker-compose stop
```

#### PHPUNIT PHPCBF PHPCS PHPSTAN PSALM PHPMD
```
docker-compose exec app composer phpunit
docker-compose exec app composer pre-commit
docker-compose exec app composer psalm
docker-compose exec app composer psalm-alter
docker-compose exec app composer psalm-taint
docker-compose exec app composer phpmd
```
