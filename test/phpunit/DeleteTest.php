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

use sql\MydbException\DeleteException;
use sql\MydbMysqli;
use sql\MydbQueryBuilderInterface;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class DeleteTest extends includes\DatabaseTestCase
{
    public function testDelete(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        $actual = $db->select("SELECT id, name FROM myusers");
        $defaults = [
            ['id' => '1', 'name' => 'user1'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
        ];

        self::assertSame($defaults, $actual);

        $affected = $db->delete("DELETE FROM myusers WHERE id IN (2,3)");
        self::assertSame(2, $affected);

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '1', 'name' => 'user1'],
        ];
        self::assertSame($reality, $actual);

        $affected = $db->delete("DELETE FROM myusers WHERE id IN (991, 992)");
        self::assertSame(0, $affected);

        $db->rollbackTransaction();
        $db->close();
    }

    public function testDeleteError(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $sql = "DELETE FROM myusers WHERE id IN (991, 992)";

        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('realQuery')->with($sql)->willReturn(false);
        $mysqli->expects(self::never())->method('readServerResponse');
        $result = $db->delete($sql);

        self::assertNull($result);
    }

    public function testDeleteInternalError(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $sql = "DELETE FROM myusers WHERE id IN (991, 992)";

        $r = new MydbMysqli\MydbMysqliResult(null, [], 0);
        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('realQuery')->with($sql)->willReturn(true);
        $mysqli->expects(self::once())->method('readServerResponse')->willReturn($r);
        $mysqli->expects(self::once())->method('getAffectedRows')->willReturn(null);
        self::expectException(DeleteException::class);

        $result = $db->delete($sql);

        self::assertNull($result);
    }

    public function testDeleteWhere(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        $actual = $db->select("SELECT id, name FROM myusers");
        $defaults = [
            ['id' => '1', 'name' => 'user1'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
        ];

        self::assertSame($defaults, $actual);

        $affected = $db->deleteWhere(['id' => [1, 3]], 'myusers', ['id' => [2]]);
        self::assertSame(2, $affected);

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '2', 'name' => 'user2'],
        ];
        self::assertSame($reality, $actual);

        $affected = $db->deleteWhere(['id' => [11, 33]], 'myusers', ['id' => [22]]);
        self::assertSame(0, $affected);

        $db->rollbackTransaction();
        $db->close();
    }

    public function testDeleteWhereReturnsNull(): void
    {
        $builder = $this->createMock(MydbQueryBuilderInterface::class);

        $db = $this->getDefaultDb(null, null, null, $builder);

        $builder->expects(self::once())->method('buildDeleteWhere')->willReturn(null);
        self::assertNull($db->deleteWhere([], ''));
    }
}
