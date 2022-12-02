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

use sql\MydbException\UpdateException;
use sql\MydbExpression;
use sql\MydbMysqli;
use sql\MydbQueryBuilderInterface;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.buildDeleteWherecom/sshilko/php-sql-mydb
 */
final class UpdateTest extends includes\DatabaseTestCase
{
    public function testUpdateWhere(): void
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

        $db->updateWhere(['name' => 'hello1'], ['id' => 1, 'name' => 'user1'], 'myusers', ['name' => 'what']);
        $db->updateWhere(['name' => 'hello2'], ['id' => 2, 'name' => 'user2'], 'myusers');
        $db->updateWhere(['name' => 'hello4', 'id' => 4], ['id' => 3, 'name' => 'user3'], 'myusers');

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '1', 'name' => 'hello1'],
            ['id' => '2', 'name' => 'hello2'],
            ['id' => '4', 'name' => 'hello4'],
        ];
        self::assertSame($reality, $actual);

        $affectedRows = $db->updateWhere(['name' => 'hello'], ['id' => [1, 2, 3, 4, 5, 6, 77, 88]], 'myusers');
        self::assertSame(3, $affectedRows);

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '1', 'name' => 'hello'],
            ['id' => '2', 'name' => 'hello'],
            ['id' => '4', 'name' => 'hello'],
        ];
        self::assertSame($reality, $actual);

        $db->rollbackTransaction();
        $db->close();
    }

    public function testUpdate(): void
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

        self::assertSame(1, $db->update("UPDATE myusers SET name = 'userabc' WHERE id = 1"));

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '1', 'name' => 'userabc'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
        ];
        self::assertSame($reality, $actual);
        $db->rollbackTransaction();
    }

    public function testUpdateMany(): void
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
        $db->updateWhereMany(
            [
                'name' => [ ['user1', 'user10'], ['user2', new MydbExpression('"user22"')], ['user3', 333] ],
            ],
            [
                'id' => [1, 2, 3],
            ],
            'myusers'
        );
        $actual = $db->select("SELECT id, name FROM myusers");
        $defaults = [
            ['id' => '1', 'name' => 'user10'],
            ['id' => '2', 'name' => 'user22'],
            ['id' => '3', 'name' => '333'],
        ];
        self::assertSame($defaults, $actual);

        $db->rollbackTransaction();
    }

    public function testUpdateInternalError(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $sql = "UPDATE myusers SET id = 10000 WHERE id IN (991, 992)";

        $r = new MydbMysqli\MydbMysqliResult(null, [], 0);

        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('realQuery')->with($sql)->willReturn(true);
        $mysqli->expects(self::once())->method('readServerResponse')->willReturn($r);
        $mysqli->expects(self::once())->method('getAffectedRows')->willReturn(null);
        self::expectException(UpdateException::class);

        $result = $db->update($sql);

        self::assertNull($result);
    }

    public function testUpdateReturnsNull(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $sql = "UPDATE myusers SET id = 10000 WHERE id IN (991, 992)";
        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('realQuery')->with($sql)->willReturn(false);
        $mysqli->expects(self::never())->method('readServerResponse');
        $result = $db->update($sql);
        self::assertNull($result);
    }

    public function testDeleteWhereReturnsNull(): void
    {
        $builder = $this->createMock(MydbQueryBuilderInterface::class);

        $db = $this->getDefaultDb(null, null, null, $builder);

        $builder->expects(self::once())->method('buildUpdateWhere')->willReturn(null);
        self::assertNull($db->updateWhere([], [], ''));
    }
}
