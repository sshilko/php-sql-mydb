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
use sql\MydbException\ConnectException;
use sql\MydbMysqliInterface;
use sql\MydbOptions;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MydbUnitTest extends includes\DatabaseTestCase
{
    public function testQueryThrowsConnectExceptionWhenLazyConnectFails(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $db = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(false);
        $mysqli->expects(self::once())->method('close')->willReturn(true);

        $this->expectException(ConnectException::class);
        $db->query('SELECT 1');
    }

    public function testCommandThrowsConnectExceptionWhenLazyConnectFails(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $db = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(false);
        $mysqli->expects(self::once())->method('close')->willReturn(true);

        $this->expectException(ConnectException::class);
        $db->command('SELECT 1');
    }

    public function testEscapeThrowsConnectExceptionWhenLazyConnectFails(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $db = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $mysqli->expects(self::once())->method('init')->willReturn(false);
        $mysqli->expects(self::once())->method('close')->willReturn(true);

        $this->expectException(ConnectException::class);
        $db->escape('text');
    }

    public function testOpenFailsAfterAllRetriesExhausted(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $db = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::exactly(3))->method('isConnected')->willReturn(false);
        $mysqli->expects(self::exactly(3))->method('init')->willReturn(false);
        $mysqli->expects(self::exactly(3))->method('close')->willReturn(true);
        $mysqli->expects(self::never())->method('setTransportOptions');

        self::assertFalse($db->open(2));
    }

    public function testOpenRecoversOnRetry(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $options = $this->createMock(MydbOptions::class);
        $envs = $this->createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->expects(self::exactly(2))->method('isConnected')->willReturn(false);
        $mysqli->expects(self::exactly(2))->method('init')->willReturnOnConsecutiveCalls(false, true);
        $mysqli->expects(self::once())->method('setTransportOptions')->willReturn(true);
        $mysqli->expects(self::once())->method('realConnect')->willReturn(true);
        $mysqli->expects(self::once())->method('close')->willReturn(true);
        $mysqli->expects(self::once())->method('autocommit')->willReturn(true);
        $mysqli->expects(self::once())->method('realQuery')->willReturn(true);

        $options->method('isPersistent')->willReturn(false);
        $options->method('getErrorReporting')->willReturn(0);
        $options->method('getClientErrorLevel')->willReturn(0);
        $options->method('isAutocommit')->willReturn(true);
        $options->method('getTimeZone')->willReturn('UTC');
        $options->method('getNonInteractiveTimeout')->willReturn(1);
        $options->method('getCharset')->willReturn('utf8mb4');
        $options->method('getTransactionIsolationLevel')->willReturn(null);
        $options->method('isReadonly')->willReturn(false);

        $envs->method('error_reporting')->willReturn(0);

        self::assertTrue($db->open(2));
    }
}
