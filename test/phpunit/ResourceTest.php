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

use sql\MydbEnvironment;
use sql\MydbException;
use sql\MydbException\DisconnectException;
use sql\MydbMysqli;
use sql\MydbOptions;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class ResourceTest extends includes\BaseTestCase
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

    public function testOpenLowVersion(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(true);
        $mysqli->expects(self::once())->method('setTransportOptions')->willReturn(true);
        $mysqli->expects(self::once())->method('realConnect')->willReturn(true);
        $mysqli->expects(self::once())->method('getServerVersion')->willReturn(50707);

        self::expectException(MydbException::class);
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
        $mysqli->expects(self::once())->method('getServerVersion')->willReturn(50708);
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
        $mysqli = $this->createMock(MydbMysqli::class);
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
        $mysqli = $this->createMock(MydbMysqli::class);
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

    public function testNoGcWhenNotConnected(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $envs = $this->createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, null, $envs);
        $mysqli->method('isConnected')->willReturn(false);
        $envs->expects(self::never())->method('gc_collect_cycles');
        $db->close();
    }

    public function testGcWhenConnected(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
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
        $mysqli = $this->createMock(MydbMysqli::class);
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
        $mysqli = $this->createMock(MydbMysqli::class);
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
}
