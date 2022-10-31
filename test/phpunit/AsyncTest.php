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

use sql\MydbException\AsyncException;
use sql\MydbException\ConnectException;
use sql\MydbMysqli;
use sql\MydbOptions;
use function time;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class AsyncTest extends includes\BaseTestCase
{
    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function testAsync(): void
    {
        $mysqli = new MydbMysqli();
        $options = new MydbOptions();
        $options->setReadonly(false);
        $options->setAutocommit(true);
        $options->setPersistent(false);

        $db = $this->getDefaultDb($mysqli, $options);

        $db->open();
        $db->async('SELECT 1');
        $db->close();

        self::expectNotToPerformAssertions();
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function testAsyncMock(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = new MydbOptions();
        $options->setReadonly(false);
        $options->setAutocommit(true);
        $options->setPersistent(false);

        $db = $this->getDefaultDb($mysqli, $options);

        $sql = 'SELECT 2' . time();

        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::atLeastOnce())->method('close')->willReturn(true);
        $mysqli->expects(self::once())->method('isTransactionOpen')->willReturn(false);
        $mysqli->expects(self::once())->method('mysqliQueryAsync')->with($sql)->willReturn(true);

        $db->open();
        $db->async($sql);
        $db->close();
    }

    public function testAsyncNotConnected(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(false);
        $mysqli->expects(self::once())->method('close')->willReturn(true);

        self::expectException(ConnectException::class);
        $db->async('select 1');
    }

    public function testAsyncReadonly(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = new MydbOptions();
        $options->setReadonly(true);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);

        self::expectException(AsyncException::class);
        $db->async('select 1');
    }

    public function testAsyncNotAutocommit(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = new MydbOptions();
        $options->setAutocommit(false);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);

        self::expectException(AsyncException::class);
        $db->async('select 1');
    }

    public function testAsyncTransactionOpen(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = new MydbOptions();
        $options->setAutocommit(true);
        $options->setReadonly(false);
        $options->setPersistent(false);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('isTransactionOpen')->willReturn(true);

        self::expectException(AsyncException::class);
        $db->async('select 1');
    }

    public function testAsyncFailedCommand(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = new MydbOptions();
        $options->setAutocommit(true);
        $options->setReadonly(false);
        $options->setPersistent(false);
        $db = $this->getDefaultDb($mysqli, $options);

        $sql = 'select 1' . time();

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('isTransactionOpen')->willReturn(false);
        $mysqli->expects(self::once())->method('mysqliQueryAsync')->with($sql)->willReturn(false);

        self::expectException(AsyncException::class);
        $db->async($sql);
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function testAsyncReal(): void
    {
        $mysqli = new MydbMysqli();
        $options = new MydbOptions();
        $options->setReadonly(false);
        $options->setAutocommit(true);
        $options->setPersistent(false);

        $db = $this->getDefaultDb($mysqli, $options);

        $sql = 'SELECT 234';

        $db->open();
        $result = $mysqli->mysqliQueryAsync($sql);
        $db->close();

        self::assertTrue($result);
    }
}
