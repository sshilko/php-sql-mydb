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

use RuntimeException;
use sql\MydbEnvironment;
use sql\MydbException;
use sql\MydbException\ConnectDefaultsException;
use sql\MydbException\DisconnectException;
use sql\MydbMysqli;
use sql\MydbMysqliInterface;
use sql\MydbOptions;
use function random_int;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class ResourceTest extends includes\DatabaseTestCase
{
    public function testOpen(): void
    {
        $db = $this->getDefaultDb();
        self::assertTrue($db->open());
    }

    public function testOpenCloseError(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(false);
        $mysqli->expects(self::once())->method('close')->willReturn(false);

        self::expectException(DisconnectException::class);
        $db->open();
    }

    public function testOpenAutocommitFailed(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(true);
        $mysqli->expects(self::once())->method('setTransportOptions')->willReturn(true);
        $mysqli->expects(self::once())->method('realConnect')->willReturn(true);
        $mysqli->expects(self::once())->method('mysqliReport');
        $mysqli->expects(self::once())->method('autocommit')->willReturn(false);

        self::expectException(MydbException\TransactionAutocommitException::class);
        $db->open();
    }

    public function testSimpleClose(): void
    {
        $db = $this->getDefaultDb();
        self::assertNull($db->close());
    }

    public function testCloseNotConnected(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);
        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $db->close();
    }

    public function testWillCommitNotPersistentTransactionWhenNoAutocommitAndNoTransactionOnClose(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);
        $envs = $this->createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->method('isConnected')->willReturn(true);

        $options->method('isAutocommit')->willReturn(false);
        $mysqli->method('isTransactionOpen')->willReturn(false);

        $options->method('isPersistent')->willReturn(false);

        $mysqli->expects(self::once())->method('commitAndRelease')->willReturn(true);
        $mysqli->method('close')->willReturn(true);
        $envs->expects(self::once())->method('gc_collect_cycles');
        $db->close();
    }

    public function testWillCommitIsPersistentTransactionWhenNoAutocommitAndNoTransactionOnClose(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);
        $envs = $this->createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isAutocommit')->willReturn(false);
        $mysqli->method('isTransactionOpen')->willReturn(false);
        $options->method('isPersistent')->willReturn(true);
        $mysqli->expects(self::once())->method('commit')->willReturn(true);
        $mysqli->method('close')->willReturn(true);
        $envs->expects(self::once())->method('gc_collect_cycles');
        $db->close();
    }

    public function testTransactionExceptionOnPersistentClose(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);

        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);

        $options->expects(self::once())->method('isAutocommit')->willReturn(false);
        $mysqli->expects(self::once())->method('isTransactionOpen')->willReturn(false);
        $options->expects(self::once())->method('isPersistent')->willReturn(true);
        $mysqli->expects(self::once())->method('commit')->willReturn(false);
        $mysqli->expects(self::never())->method('commitAndRelease');

        $this->expectException(MydbException\TransactionCommitException::class);
        $db->close();
    }

    public function testTransactionExceptionOnNonPersistentClose(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);

        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);

        $options->expects(self::once())->method('isAutocommit')->willReturn(false);
        $mysqli->expects(self::once())->method('isTransactionOpen')->willReturn(false);
        $options->expects(self::once())->method('isPersistent')->willReturn(false);
        $mysqli->expects(self::never())->method('commit');
        $mysqli->expects(self::once())->method('commitAndRelease')->willReturn(false);

        $this->expectException(MydbException\TransactionCommitException::class);
        $db->close();
    }

    public function testDisconnectExceptionOnClose(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);

        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);

        $options->expects(self::once())->method('isAutocommit')->willReturn(true);

        $mysqli->expects(self::once())->method('close')->willReturn(false);
        $this->expectException(MydbException\DisconnectException::class);
        $db->close();
    }

    public function testUncaughtExceptionBecomesInternalExceptionOnClose(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);

        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);

        $options->expects(self::once())->method('isAutocommit')->willReturn(true);

        $mysqli->expects(self::once())->method('close')->willThrowException(new RuntimeException('ho-ho'));
        $this->expectException(MydbException\InternalException::class);
        $db->close();
    }

    public function testNoGcWhenNotConnected(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $envs = $this->createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, null, $envs);
        $mysqli->method('isConnected')->willReturn(false);
        $envs->expects(self::never())->method('gc_collect_cycles');
        $db->close();
    }

    public function testGcWhenConnected(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $envs = $this->createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, null, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $mysqli->method('commitAndRelease')->willReturn(true);

        $mysqli->method('close')->willReturn(true);

        $mysqli->method('isConnected')->willReturn(false);
        $envs->expects(self::once())->method('gc_collect_cycles');
        $db->close();
    }

    public function testDoNoCommitTransactionWhenAutocommitEnabledOnClose(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);
        $envs = $this->createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isAutocommit')->willReturn(true);
        $mysqli->method('isTransactionOpen')->willReturn(false);
        $options->method('isPersistent')->willReturn(true);
        $mysqli->expects(self::never())->method('commit');
        $mysqli->method('close')->willReturn(true);
        $db->close();
    }

    public function testDoNoCommitTransactionWhenTransactionExplicitlyStartedOnClose(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);
        $envs = $this->createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isAutocommit')->willReturn(false);
        $mysqli->method('isTransactionOpen')->willReturn(true);
        $mysqli->expects(self::never())->method('commit');
        $mysqli->method('close')->willReturn(true);
        $db->close();
    }

    public function testQueryBadClientRequest(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);

        $mysqli->expects(self::once())->method('realQuery')->willReturn(false);

        $db = $this->getDefaultDb($mysqli);
        self::assertTrue($db->open());

        self::assertNull($db->query('SELECT 1'));
    }

    public function testQueryBadServerResponse(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);

        $mysqli->expects(self::once())->method('realQuery')->willReturn(true);
        $mysqli->expects(self::once())->method('readServerResponse')->willReturn(null);

        $db = $this->getDefaultDb($mysqli);
        self::assertTrue($db->open());

        self::assertNull($db->query('SELECT 1'));
    }

    public function testQueryBadServerResponsePacketFieldCountIsZero(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $packet = $this->createMock(MydbMysqli\MydbMysqliResultInterface::class);

        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);

        $mysqli->expects(self::once())->method('realQuery')->willReturn(true);
        $mysqli->expects(self::once())->method('readServerResponse')->willReturn($packet);

        $packet->expects(self::once())->method('getFieldCount')->willReturn(0);
        $packet->expects(self::never())->method('getResult');

        $db = $this->getDefaultDb($mysqli);
        self::assertTrue($db->open());

        self::assertNull($db->query('SELECT 1'));
    }

    public function testQueryBadServerResponsePacketFieldCountIsNotZeroButBadResult(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $packet = $this->createMock(MydbMysqli\MydbMysqliResultInterface::class);

        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);

        $mysqli->expects(self::once())->method('realQuery')->willReturn(true);
        $mysqli->expects(self::once())->method('readServerResponse')->willReturn($packet);

        $packet->expects(self::once())->method('getFieldCount')->willReturn(random_int(1, 99));
        $packet->expects(self::once())->method('getResult')->willReturn(null);

        $db = $this->getDefaultDb($mysqli);
        self::assertTrue($db->open());

        $this->expectException(MydbException\InternalException::class);
        self::assertNull($db->query('SELECT 1'));
    }

    public function testConnectingDefaultsFailed(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);
        $envs = $this->createMock(MydbEnvironment::class);

        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(true);
        $mysqli->expects(self::once())->method('setTransportOptions')->willReturn(true);
        $mysqli->expects(self::once())->method('realConnect')->willReturn(true);
        $mysqli->expects(self::once())->method('autocommit')->willReturn(true);

        $options->expects(self::once())->method('getTimeZone')->willReturn('UTC123');
        $options->expects(self::once())->method('getNonInteractiveTimeout')->willReturn(123);
        $options->expects(self::once())->method('getCharset')->willReturn('ascii56');

        $mysqli->expects(self::once())->method('realQuery')
            ->with("SET time_zone = 'UTC123', wait_timeout = 123, names 'ascii56'")->willReturn(false);

        $this->expectException(ConnectDefaultsException::class);

        $db->open();
    }

    public function testConnectingTransactionIsolationFailed(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);
        $envs = $this->createMock(MydbEnvironment::class);

        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(true);
        $mysqli->expects(self::once())->method('setTransportOptions')->willReturn(true);
        $mysqli->expects(self::once())->method('realConnect')->willReturn(true);
        $mysqli->expects(self::once())->method('autocommit')->willReturn(true);
        $mysqli->expects(self::once())->method('realQuery')->willReturn(true);

        $options->expects(self::once())->method('getTransactionIsolationLevel')->willReturn('yes123');
        $mysqli->expects(self::once())->method('setTransactionIsolationLevel')->with('yes123')->willReturn(false);

        $this->expectException(MydbException\TransactionIsolationException::class);

        $db->open();
    }

    public function testConnectingTransactionAutocommitFailed(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);
        $envs = $this->createMock(MydbEnvironment::class);

        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(true);
        $mysqli->expects(self::once())->method('setTransportOptions')->willReturn(true);
        $mysqli->expects(self::once())->method('realConnect')->willReturn(true);
        $mysqli->expects(self::once())->method('autocommit')->willReturn(true);
        $mysqli->expects(self::once())->method('realQuery')->willReturn(true);

        $options->expects(self::once())->method('getTransactionIsolationLevel');
        $mysqli->expects(self::never())->method('setTransactionIsolationLevel');

        $options->expects(self::once())->method('isReadonly')->willReturn(true);
        $mysqli->expects(self::once())->method('beginTransactionReadonly')->willReturn(false);

        $this->expectException(MydbException\TransactionAutocommitException::class);

        $db->open();
    }
}
