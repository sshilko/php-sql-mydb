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
        $logger = $db->getOptions()->getLogger();
        /**
         * @var MockObject $logger
         */
        $logger->expects(self::once())->method('warning')->with('Division by 0');
        $x = $db->select("SELECT 1/0 as x");
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

        $oldPacket = $db->select("SELECT @@max_allowed_packet as mpac");
        $maxPacket = $oldPacket[0]['mpac'];


        $netBuffer = $db->select("SELECT @@net_buffer_length as len");
        $minPacket = $netBuffer[0]['len'];
        $bigPacket = $minPacket + 1;

        $db->command("SET GLOBAL max_allowed_packet=" . $minPacket);

        $logger = $db->getOptions()->getLogger();
        /**
         * @var MockObject $logger
         */
        $logger->expects(self::once())->method('warning')->with(
            'Result of repeat() was larger than max_allowed_packet (' . $minPacket . ') - truncated'
        );
        $result = $db->select("SELECT REPEAT('x', " . $bigPacket . ") as x");

        self::assertSame($result[0]['x'], null);
        $db->command("SET GLOBAL max_allowed_packet=" . $maxPacket);

        $randomSmall = random_int(2, 256);
        $result = $db->select("SELECT REPEAT('y', " . $randomSmall . ") as y");
        self::assertSame($result[0]['y'], str_repeat('y', $randomSmall));
    }
}
