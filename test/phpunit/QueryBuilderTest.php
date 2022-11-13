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

use PHPUnit\Framework\TestCase;
use sql\MydbException;
use sql\MydbException\DisconnectException;
use sql\MydbException\QueryBuilderEscapeException;
use sql\MydbExpression;
use sql\MydbMysqli;

use sql\MydbMysqli\MydbMysqliEscapeStringInterface;

use sql\MydbQueryBuilder;
use sql\MydbQueryBuilderInterface;

use function time;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class QueryBuilderTest extends TestCase
{
    private MydbMysqliEscapeStringInterface $esc;
    private MydbQueryBuilderInterface $builder;

    public function setUp(): void
    {
        $this->esc = $this->createMock(MydbMysqliEscapeStringInterface::class);
        $this->esc->method('realEscapeString')->willReturnCallback(
                function ($arg1) {
                    /**
                     * Not testing escape logic here
                     */
                    return $arg1;
                }
        );
        $this->builder = new MydbQueryBuilder($this->esc);
    }

    public function testShowColumnsLike(): void
    {
        $sql = $this->builder->showColumnsLike('table1', 'column1');
        self::assertSame("SHOW COLUMNS FROM table1 LIKE 'column1'", $sql);

        $sql = $this->builder->showColumnsLike('db1.table1', 'table2.column3');
        self::assertSame("SHOW COLUMNS FROM db1.table1 LIKE 'table2.column3'", $sql);
    }

    public function testShowKeys(): void
    {
        $sql = $this->builder->showKeys('table1');
        self::assertSame('SHOW KEYS FROM table1', $sql);

        $sql = $this->builder->showKeys('db1.table2');
        self::assertSame('SHOW KEYS FROM db1.table2', $sql);
    }

    /**
     * @dataProvider dataProviderTestInsertOne
     */
    public function testInsertOne(string $sql, string $table, $data): void
    {
        $real = $this->builder->insertOne($data, $table, 'INSERT');
        self::assertSame($sql, $real);
    }

    /**
     * @dataProvider dataProviderTestInsertOne
     */
    public function testReplaceOne(string $sql, string $table, $data): void
    {
        $real = $this->builder->insertOne($data, $table, 'REPLACE');
        $sql = str_replace('INSERT ', 'REPLACE ', $sql);
        self::assertSame($sql, $real);
    }

    /**
     * @dataProvider dataProviderTestBuildUpdateWhereMany
     */
    public function testBuildUpdateWhereMany(string $sql, array $columnSetWhere, array $where, string $table): void
    {
        $real = $this->builder->buildUpdateWhereMany($columnSetWhere, $where, $table);
        self::assertSame($sql, $real);
    }

    /**
     * @return array<array<string, string>>
     */
    public function dataProviderTestBuildUpdateWhereMany(): array
    {
        return [
                'simple' => [
                        'sql' => "UPDATE table1 SET id = CASE WHEN (id = '1') THEN 2 WHEN (id = 3.3) THEN '4' ELSE id SET name = CASE WHEN (name = 'oldname') THEN NOW() ELSE name END WHERE xxx='yyy'",
                        'columnSetWhere' => [
                                'id' => [
                                        ['1',  2],
                                        [3.30,   '4']
                                ],
                                'name' => [
                                        ['oldname', new MydbExpression('NOW()')],
                                ],
                        ],
                        'where' => ['xxx' => 'yyy'],
                        'table' => 'table1'
                ],
                'prefixed simple' => [
                        'sql' => "UPDATE db1.table1 SET table1.id = CASE WHEN (table1.id = '1') THEN 2 WHEN (table1.id = 3) THEN '4' ELSE table1.id SET table1.name = CASE WHEN (table1.name = 'oldname') THEN NOW() ELSE table1.name END WHERE table1.xxx='yyy'",
                        'columnSetWhere' => [
                                'table1.id' => [
                                        ['1',  2],
                                        [3,   '4']
                                ],
                                'table1.name' => [
                                        ['oldname', new MydbExpression('NOW()')],
                                ],
                        ],
                        'where' => ['table1.xxx' => 'yyy'],
                        'table' => 'db1.table1'
                ]
        ];
    }

    /**
     * @return array<array<string, string>>
     */
    public function dataProviderTestInsertOne(): array
    {
        return [
                'simple int' => [
                        'sql' => "INSERT INTO hello1 (id,name) VALUES (1,'user1')",
                        'table' => 'hello1',
                        'data' => ['id' => 1, 'name' => 'user1'],
                ],
                'simple float' => [
                        'sql' => "INSERT INTO hello2 (id,name) VALUES (1.5,'user1')",
                        'table' => 'hello2',
                        'data' => ['id' => 1.5, 'name' => 'user1'],
                ],
                'expression' => [
                        'sql' => "INSERT INTO hello3 (id,name) VALUES (2,NOW())",
                        'table' => 'hello3',
                        'data' => ['id' => 2, 'name' => new MydbExpression('NOW()')],
                ],
                'raw null' => [
                        'sql' => "INSERT INTO hello4 (id,name) VALUES (3,'')",
                        'table' => 'hello4',
                        'data' => ['id' => 3, 'name' => null],
                ],
                'string caps null' => [
                        'sql' => "INSERT INTO hello5 (id,name) VALUES (5,'NULL')",
                        'table' => 'hello5',
                        'data' => ['id' => 5, 'name' => 'NULL'],
                ],
                'string lowercase null' => [
                        'sql' => "INSERT INTO hello6 (id,name) VALUES (6,'null')",
                        'table' => 'hello6',
                        'data' => ['id' => 6, 'name' => 'null'],
                ],
                'string 0x' => [
                        'sql' => "INSERT INTO hello7 (id,name) VALUES (6,0xAABBCC)",
                        'table' => 'hello7',
                        'data' => ['id' => 6, 'name' => '0xAAbbCc'],
                ],
                'string 0x even' => [
                        'sql' => "INSERT INTO hello7 (id,name) VALUES (6,'0xAAbbC')",
                        'table' => 'hello7',
                        'data' => ['id' => 6, 'name' => '0xAAbbC'],
                ],
                'string 0x mismatch' => [
                        'sql' => "INSERT INTO hello7 (id,name) VALUES (6,'0xAA-.%bbC')",
                        'table' => 'hello7',
                        'data' => ['id' => 6, 'name' => '0xAA-.%bbC'],
                ],
        ];
    }
}
