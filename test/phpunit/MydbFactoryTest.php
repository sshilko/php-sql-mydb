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

namespace phpunit;

use sql\Mydb;
use sql\MydbCredentials;
use sql\MydbFactory;
use sql\MydbOptions;
use sql\MydbOptionsInterface;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MydbFactoryTest extends includes\DatabaseTestCase
{
    public function testCreateOptionsInitializesDefaults(): void
    {
        $options = (new MydbFactory())->createOptions();

        self::assertInstanceOf(MydbOptionsInterface::class, $options);
        self::assertSame(MydbOptions::DEFAULT_CONNECT_TIMEOUT, $options->getConnectTimeout());
        self::assertSame(MydbOptions::DEFAULT_SERVER_SELECT_TIMEOUT, $options->getServerSideSelectTimeout());
        self::assertSame(MydbOptions::DEFAULT_READ_TIMEOUT, $options->getReadTimeout());
        self::assertSame(MydbOptions::DEFAULT_NON_INTERACTIVE_TIMEOUT, $options->getNonInteractiveTimeout());
        self::assertSame(MydbOptions::DEFAULT_NETWORK_BUFFER_SIZE, $options->getNetworkBufferSize());
        self::assertSame(MydbOptions::DEFAULT_NETWORK_READ_BUFFER, $options->getNetworkReadBuffer());
        self::assertSame(MydbOptions::DEFAULT_ERROR_REPORTING, $options->getErrorReporting());
        self::assertSame(MydbOptions::DEFAULT_CLIENT_ERROR_LEVEL, $options->getClientErrorLevel());
        self::assertSame(MydbOptions::DEFAULT_TIMEZONE, $options->getTimeZone());
        self::assertSame(MydbOptions::DEFAULT_CHARSET, $options->getCharset());
        self::assertSame(MydbOptions::DEFAULT_AUTOCOMMIT, $options->isAutocommit());
        self::assertSame(MydbOptions::DEFAULT_PERSISTENT, $options->isPersistent());
        self::assertSame(MydbOptions::DEFAULT_READONLY, $options->isReadonly());
    }

    public function testCreateOptionsMatchesNewMydbOptions(): void
    {
        $options  = (new MydbFactory())->createOptions();
        $defaults = new MydbOptions();

        self::assertSame($defaults->getConnectTimeout(), $options->getConnectTimeout());
        self::assertSame($defaults->getServerSideSelectTimeout(), $options->getServerSideSelectTimeout());
        self::assertSame($defaults->getReadTimeout(), $options->getReadTimeout());
        self::assertSame($defaults->getNonInteractiveTimeout(), $options->getNonInteractiveTimeout());
        self::assertSame($defaults->getNetworkBufferSize(), $options->getNetworkBufferSize());
        self::assertSame($defaults->getNetworkReadBuffer(), $options->getNetworkReadBuffer());
        self::assertSame($defaults->getErrorReporting(), $options->getErrorReporting());
        self::assertSame($defaults->getClientErrorLevel(), $options->getClientErrorLevel());
        self::assertSame($defaults->getTimeZone(), $options->getTimeZone());
        self::assertSame($defaults->getCharset(), $options->getCharset());
        self::assertSame($defaults->isAutocommit(), $options->isAutocommit());
        self::assertSame($defaults->isPersistent(), $options->isPersistent());
        self::assertSame($defaults->isReadonly(), $options->isReadonly());
    }

    public function testCreateReadonlyOptions(): void
    {
        $options = (new MydbFactory())->createReadonlyOptions();

        self::assertTrue($options->isReadonly());
        self::assertTrue($options->isAutocommit());
        self::assertSame(
            MydbOptions::TRANSACTION_ISOLATION_LEVEL_READ_COMMITTED,
            $options->getTransactionIsolationLevel()
        );
    }

    public function testCreateDefaults(): void
    {
        $credentials = new MydbCredentials('127.0.0.1', 'root', 'root', 'mydb');

        $mydb = (new MydbFactory())->create($credentials);

        self::assertInstanceOf(Mydb::class, $mydb);
    }

    public function testCreateWithOptions(): void
    {
        $credentials = new MydbCredentials('127.0.0.1', 'root', 'root', 'mydb');
        $options     = (new MydbFactory())->createReadonlyOptions();

        $mydb = (new MydbFactory())->create($credentials, $this->logger, $options);

        self::assertInstanceOf(Mydb::class, $mydb);
    }
}
