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

use sql\MydbExpression;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class UpdateTest extends includes\BaseTestCase
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

        $db->updateWhere(['name' => 'hello'], ['id' => [1, 2, 3, 4]], 'myusers');
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

        $db->update("UPDATE myusers SET name = 'userabc' WHERE id = 1");

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
}
