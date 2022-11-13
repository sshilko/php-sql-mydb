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
use sql\MydbMysqli;
use function array_merge;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class InsertTest extends includes\BaseTestCase
{
    public function testReplace(): void
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

        $db->replace("REPLACE INTO myusers (id, name) VALUES (1, 'user11')");

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '1', 'name' => 'user11'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
        ];
        self::assertSame($reality, $actual);

        $db->rollbackTransaction();
        $db->close();
    }

    public function testReplaceOne(): void
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

        $db->replaceOne(['name' => 'user111', 'id' => 1], 'myusers');

        $actual = $db->select("SELECT id, name FROM myusers");
        $reality = [
            ['id' => '1', 'name' => 'user111'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
        ];
        self::assertSame($reality, $actual);

        $db->rollbackTransaction();
        $db->close();
    }

    public function testInsertError(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);

        $sql = "INSERT INTO myusers (id, name) VALUES (9, 'user9')";

        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('realQuery')->with($sql)->willReturn(false);
        $mysqli->expects(self::never())->method('readServerResponse');
        $result = $db->replace($sql);
        self::assertNull($result);
    }

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

        $db->insertMany(
            [
                ['1', 'user1'],
            ],
            [
                'id',
                'name',
            ],
            'myusers',
            false,
            'id=id+100'
        );

        $actual = $db->select("SELECT id, name FROM myusers");
        $defaults = [
                /**
                 * duplicate row id=1 is updated to id=101
                 */
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
            ['id' => '5', 'name' => 'name5'],
            ['id' => '6', 'name' => 'name6'],
            ['id' => '7', 'name' => 'name7'],
            ['id' => '8', 'name' => '888'],
            ['id' => '101', 'name' => 'user1'],
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
        $expected = [
            ['id' => '1', 'name' => 'user1'],
            ['id' => '2', 'name' => 'user2'],
            ['id' => '3', 'name' => 'user3'],
            ['id' => '7', 'name' => '666'],
            ['id' => '8', 'name' => 'user8'],
        ];
        self::assertSame($expected, $actual);

        $actual = $db->select("SELECT id, cost FROM mydecimals");
        $expected = [
            ['id' => '1', 'cost' => '1.10'],
            ['id' => '2', 'cost' => '1.20'],
            ['id' => '3', 'cost' => '0.30'],
        ];
        self::assertSame($expected, $actual);
        $db->insertOne(['id' => 95, 'cost' => 3.21], 'mydecimals');
        $db->insertOne(['id' => 96, 'cost' => 3.2], 'mydecimals');
        $db->insertOne(['id' => 97, 'cost' => '3'], 'mydecimals');
        $db->insertOne(['id' => 98, 'cost' => '3.01'], 'mydecimals');
        $db->insertOne(['id' => 99, 'cost' => '0'], 'mydecimals');

        $expected = array_merge($expected, [
            ['id' => '95', 'cost' => '3.21'],
            ['id' => '96', 'cost' => '3.20'],
            ['id' => '97', 'cost' => '3.00'],
            ['id' => '98', 'cost' => '3.01'],
            ['id' => '99', 'cost' => '0.00'],
        ]);
        $actual = $db->select("SELECT id, cost FROM mydecimals");
        self::assertSame($expected, $actual);

        $db->rollbackTransaction();
        $db->close();
    }
}
