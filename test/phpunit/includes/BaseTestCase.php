<?php
/**
 * This file is part of the sshilko/php-sql-mydb package.
 *
 * (c) Sergei Shilko <contact@sshilko.com>
 *
 * MIT License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace phpunit\includes;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use sql\Mydb;
use sql\MydbCredentials;
use sql\MydbInterface;
use sql\MydbOptions;
use sql\MydbRegistry;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class BaseTestCase extends TestCase
{
    /**
     * @psalm-suppress UndefinedConstant
     */
    private const HOST = PHPUNIT_MYSQL_MYDB1_HOST;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const PORT = PHPUNIT_MYSQL_MYDB1_PORT;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const USER = PHPUNIT_MYSQL_MYDB1_USER;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const PASS = PHPUNIT_MYSQL_MYDB1_PASS;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const NAME = PHPUNIT_MYSQL_MYDB1_NAME;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const ROOT_U = PHPUNIT_MYSQL_ROOT_USER;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const ROOT_P = PHPUNIT_MYSQL_ROOT_PASS;

    /**
     * @var MockObject|LoggerInterface
     */
    protected LoggerInterface $logger;

    private static ?MydbRegistry $registry = null;

    protected function setUp(): void
    {
        static::$registry = new MydbRegistry();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        foreach (static::$registry::listInstances() as $dbId) {
            if (!static::$registry::hasInstance($dbId)) {
                continue;
            }

            static::$registry::getInstance($dbId)->close();
            static::$registry::setInstance($dbId, null);
        }
        static::$registry = null;
    }

    protected function getDefaultDb(): MydbInterface
    {
        if (!static::$registry::hasInstance('db0')) {
            $options = new MydbOptions();
            $credentials = new MydbCredentials(self::HOST, self::USER, self::PASS, self::NAME, (int) self::PORT);
            static::$registry::setInstance(
                'db0',
                new Mydb($credentials, $options, $this->logger)
            );
        }

        return static::$registry::getInstance('db0');
    }

    protected function getNoConnectDb(): MydbInterface
    {
        if (!static::$registry::hasInstance('db1')) {
            $options = new MydbOptions();
            $credentials = new MydbCredentials('1.2.3.4', self::USER, self::PASS, self::NAME, (int) self::PORT);
            $options->setConnectTimeout(1);
            static::$registry::setInstance(
                'db1',
                new Mydb($credentials, $options, $this->logger)
            );
        }

        return static::$registry::getInstance('db1');
    }

    protected function getRootDb(): MydbInterface
    {
        if (!static::$registry::hasInstance('db2')) {
            $options = new MydbOptions();
            $options->setAutocommit(true);
            $credentials = new MydbCredentials(self::HOST, self::ROOT_U, self::ROOT_P, self::NAME, (int) self::PORT);
            static::$registry::setInstance(
                'db2',
                new Mydb($credentials, $options, $this->logger)
            );
        }

        return static::$registry::getInstance('db2');
    }

    protected static function getDbName(): string
    {
        return self::NAME;
    }
}
