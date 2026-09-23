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

use Override;
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
    private const string HOST = PHPUNIT_MYSQL_MYDB1_HOST;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const string PORT = PHPUNIT_MYSQL_MYDB1_PORT;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const string USER = PHPUNIT_MYSQL_MYDB1_USER;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const string PASS = PHPUNIT_MYSQL_MYDB1_PASS;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const string NAME = PHPUNIT_MYSQL_MYDB1_NAME;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const string ROOT_U = PHPUNIT_MYSQL_ROOT_USER;

    /**
     * @psalm-suppress UndefinedConstant
     */
    private const string ROOT_P = PHPUNIT_MYSQL_ROOT_PASS;

    /**
     * @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    protected LoggerInterface $logger;

    private static ?MydbRegistry $registry = null;

    #[Override]
    protected function setUp(): void
    {
        static::$registry = new MydbRegistry();
        $this->logger     = $this->createMock(LoggerInterface::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        $registry = static::$registry;
        if (null !== $registry && 0 !== count($registry)) {
            /**
             * @phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
             * @psalm-suppress PossiblyNullArgument
             * @psalm-suppress UnusedForeachValue
             */
            foreach ($registry as $index => $value) {
                $registry->offsetUnset($index);
            }
        }
        static::$registry = null;
    }

    protected function getDefaultDb(
        ?MydbMysqliInterface $mysqli = null,
        ?MydbOptionsInterface $options = null,
        ?MydbEnvironmentInterface $environment = null,
        ?MydbQueryBuilderInterface $builder = null,
        bool $refresh = false,
    ): MydbInterface {
        if (!isset(static::$registry['db0']) || true === $refresh) {
            /**
             * Bootstrap-defined constants carry string credentials.
             * @psalm-suppress MixedArgument
             */
            $credentials = new MydbCredentials(self::HOST, self::USER, self::PASS, self::NAME, (int) self::PORT);
            if (isset(static::$registry['db0'])) {
                unset(static::$registry['db0']);
            }
            static::$registry['db0'] = new Mydb($credentials, $this->logger, $options, $mysqli, $environment, $builder);
        }

        return static::$registry['db0'];
    }

    protected function getNoConnectDb(): MydbInterface
    {
        if (!isset(static::$registry['db1'])) {
            $options = new MydbOptions();
            /**
             * Bootstrap-defined constants carry string credentials.
             * @psalm-suppress MixedArgument
             */
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

            /**
             * Bootstrap-defined constants carry string credentials.
             * @psalm-suppress MixedArgument
             */
            $credentials             = new MydbCredentials(
                self::HOST,
                self::ROOT_U,
                self::ROOT_P,
                self::NAME,
                (int) self::PORT
            );
            static::$registry['db2'] = new Mydb($credentials, $this->logger, $options);
        }

        return static::$registry['db2'];
    }

    protected static function getDbName(): string
    {
        /**
         * Bootstrap-defined constant carries a string value.
         * @psalm-suppress MixedReturnStatement
         */
        return self::NAME;
    }
}
