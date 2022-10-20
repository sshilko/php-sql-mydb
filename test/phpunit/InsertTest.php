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
final class InsertTest extends includes\BaseTestCase
{
    public function testInsert(): void
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

        $db->insert("INSERT INTO myusers (id, name) VALUES (9, 'user9')");

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '1', 'name' => 'user1'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
            ['id' => '9', 'name' => 'user9'],
        ];
        self::assertSame($reality, $actual);

        $db->rollbackTransaction();
        $db->close();
    }

    public function testInsertMany(): void
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

        $db->insertMany(
            [
                ['5', 'name5'],
                [6, 'name6'],
                [new MydbExpression('7'), 'name7'],
                ['8', new MydbExpression('888')],
            ],
            [
                'id',
                'name',
            ],
            'myusers'
        );

        $actual = $db->select("SELECT id, name FROM myusers");
        $defaults = [
            ['id' => '1', 'name' => 'user1'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
            ['id' => '5', 'name' => 'name5'],
            ['id' => '6', 'name' => 'name6'],
            ['id' => '7', 'name' => 'name7'],
            ['id' => '8', 'name' => '888'],
        ];
        self::assertSame($defaults, $actual);
        $db->rollbackTransaction();
        $db->close();

        /**
         * Test re-open connection after close, w/o explicit open()
         */

        $actual = $db->select("SELECT id, name FROM myusers");
        $defaults = [
            ['id' => '1', 'name' => 'user1'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
        ];
        self::assertSame($defaults, $actual);
        $db->close();
    }

    public function testInsertOne(): void
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

        $db->insertOne(['id' => 7, 'name' => new MydbExpression('666')], 'myusers');
        $db->insertOne(['id' => 8, 'name' => 'user8'], 'myusers');

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '1', 'name' => 'user1'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
            ['id' => '7', 'name' => '666'],
            ['id' => '8', 'name' => 'user8'],
        ];
        self::assertSame($reality, $actual);

        $db->rollbackTransaction();
        $db->close();
    }
}
