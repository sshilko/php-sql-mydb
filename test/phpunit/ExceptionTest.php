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

namespace phpunit;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class ExceptionTest extends includes\BaseTestCase
{
    public function testTableDoesNotExist(): void
    {
        $db = $this->getDefaultDb();
        $tableName = 'a' . time();
        $this->expectExceptionMessage("Table '" . self::getDbName(). ".$tableName' doesn't exist");
        $db->select("SELECT * from $tableName");
    }

    public function testFailedToConnect(): void
    {
        $db = $this->getNoConnectDb();
        $tableName = 'a' . time();
        $this->expectExceptionMessage("Connection timed out");
        $db->select("SELECT * from $tableName");
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

    public function testPacketSizeTooLarge(): void
    {
        $db = $this->getRootDb();

        $netBuffer = $db->select("SELECT @@net_buffer_length as len");
        $minPacket = $netBuffer[0]['len'];
        $bigPacket = $minPacket + 1;

        /**
         * @var MockObject $logger
         */
        $this->logger->expects(self::once())->method('error');

        $this->expectExceptionMessage("Got a packet bigger than 'max_allowed_packet' bytes");
        $this->expectException(\sql\MydbException::class);
        $db->select("SELECT '" . str_repeat('.', $bigPacket) . "' as x");
    }
}
