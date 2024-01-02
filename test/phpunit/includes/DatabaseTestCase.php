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
 * @license https://opensource.org/licenses/mit-license.php MIT
 */

declare(strict_types = 1);

namespace phpunit\includes;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use sql\Mydb;
use sql\MydbCredentials;
use sql\MydbEnvironmentInterface;
use sql\MydbInterface;
use sql\MydbMysqliInterface;
use sql\MydbOptions;
use sql\MydbOptionsInterface;
use sql\MydbQueryBuilderInterface;
use sql\MydbRegistry;
use function count;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
class DatabaseTestCase extends TestCase
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
     * @var \phpunit\includes\MockObject|\Psr\Log\LoggerInterface
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
        if (count(static::$registry)) {
            /**
             * @phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
             */
            foreach (static::$registry as $index => $value) {
                static::$registry->offsetUnset($index);
            }
        }
        static::$registry = null;
    }

    /**
     * @return \sql\Mydb
     */
    protected function getDefaultDb(
        ?MydbMysqliInterface $mysqli = null,
        ?MydbOptionsInterface $options = null,
        ?MydbEnvironmentInterface $environment = null,
        ?MydbQueryBuilderInterface $builder = null,
        bool $refresh = false,
    ): MydbInterface {
        if (!isset(static::$registry['db0']) || true === $refresh) {
            $credentials = new MydbCredentials(self::HOST, self::USER, self::PASS, self::NAME, (int) self::PORT);
            if (isset(static::$registry['db0'])) {
                unset(static::$registry['db0']);
            }
            static::$registry['db0'] = new Mydb($credentials, $this->logger, $options, $mysqli, $environment, $builder);
        }

        return static::$registry['db0'];
    }

    protected function getNoConnectDb(): Mydb
    {
        if (!isset(static::$registry['db1'])) {
            $options = new MydbOptions();
            $credentials = new MydbCredentials('1.2.3.4', self::USER, self::PASS, self::NAME, (int) self::PORT);

            $options->setConnectTimeout(1);
            static::$registry['db1'] = new Mydb($credentials, $this->logger, $options);
        }

        return static::$registry['db1'];
    }

    protected function getRootDb(): MydbInterface
    {
        if (!isset(static::$registry['db2'])) {
            $options = new MydbOptions();
            $options->setAutocommit(true);

            $credentials = new MydbCredentials(self::HOST, self::ROOT_U, self::ROOT_P, self::NAME, (int) self::PORT);
            static::$registry['db2'] = new Mydb($credentials, $this->logger, $options);
        }

        return static::$registry['db2'];
    }

    protected static function getDbName(): string
    {
        return self::NAME;
    }
}
