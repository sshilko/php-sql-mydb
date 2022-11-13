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

use sql\MydbException\ConnectException;
use function sprintf;
use function time;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class ExceptionTest extends includes\BaseTestCase
{
    public function testTableDoesNotExist(): void
    {
        $db = $this->getDefaultDb();
        $tableName = 'table' . time();
        $sql = "SELECT * from " . $tableName;
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                sprintf(
                    "mysqli::store_result(): (42S02/1146): Table '%s.%s' doesn't exist",
                    $this->getDbName(),
                    $tableName
                )
            );

        $this->expectExceptionMessage("1146 Table '" . self::getDbName() . "." . $tableName . "' doesn't exist");
        $db->select($sql);
    }

    /**
     * @medium
     */
    public function testFailedToConnect(): void
    {
        $db = $this->getNoConnectDb();
        $this->logger->expects(self::once())->method('warning')->with('2002 Connection timed out');
        $result = $db->open();
        self::assertSame(false, $result);
    }

    /**
     * @medium
     */
    public function testFailedToConnectAfterRetry(): void
    {
        $retry = 1;
        $db = $this->getNoConnectDb();
        $this->logger->expects(self::exactly($retry + 1))->method('warning')->with('2002 Connection timed out');
        $result = $db->open($retry);
        self::assertSame(false, $result);
    }

    /**
     * @medium
     */
    public function testFailedToConnectLazy(): void
    {
        $db = $this->getNoConnectDb();
        $this->expectException(ConnectException::class);
        $this->logger->expects(self::once())->method('warning')->with('2002 Connection timed out');
        $db->select("SELECT 1");
    }

    /**
     * @medium
     */
    public function testFailedToCommandLazy(): void
    {
        $db = $this->getNoConnectDb();
        $this->expectException(ConnectException::class);
        $this->logger->expects(self::once())->method('warning')->with('2002 Connection timed out');
        $db->command("SELECT 1");
    }

    /**
     * @medium
     */
    public function testFailedToBeginTransaction(): void
    {
        $db = $this->getNoConnectDb();
        $this->expectException(ConnectException::class);
        $db->beginTransaction();
    }

    /**
     * @medium
     */
    public function testFailedToRollbackTransaction(): void
    {
        $db = $this->getNoConnectDb();
        $this->expectException(ConnectException::class);
        $db->rollbackTransaction();
    }

    /**
     * @medium
     */
    public function testFailedToCommitTransaction(): void
    {
        $db = $this->getNoConnectDb();
        $this->expectException(ConnectException::class);
        $db->commitTransaction();
    }

    /**
     * @medium
     */
    public function testFailedToEscapeLazy(): void
    {
        $db = $this->getNoConnectDb();
        $this->expectException(ConnectException::class);
        $this->logger->expects(self::once())->method('warning')->with('2002 Connection timed out');
        $db->escape("hello");
    }

    public function testMySqlWarning(): void
    {
        $db = $this->getDefaultDb();
        $this->logger->expects(self::once())->method('warning')->with('Division by 0');
        $x = $db->select("select 1/0 as x");
        self::assertSame($x[0]['x'], null);
    }

    public function testMySqlError(): void
    {
        $db = $this->getDefaultDb();
        $random = 'a' . time();
        $this->expectExceptionMessage("Unknown system variable '" . $random . "'");
        $db->select("SELECT @@" . $random);
    }
}
