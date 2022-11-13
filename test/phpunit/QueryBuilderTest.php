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
 *
 * @license https://opensource.org/licenses/mit-license.php MIT
 */

declare(strict_types = 1);

namespace phpunit;

use PHPUnit\Framework\TestCase;
use sql\MydbException\QueryBuilderException;
use sql\MydbExpression;
use sql\MydbMysqli\MydbMysqliEscapeStringInterface;
use sql\MydbQueryBuilder;
use sql\MydbQueryBuilderInterface;
use function array_merge;
use function str_replace;

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
            static function ($arg1) {
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

        self::expectException(QueryBuilderException::class);
        $this->builder->showColumnsLike('', 'table2.column3');

        self::expectException(QueryBuilderException::class);
        $this->builder->showColumnsLike('db1.table1', '');
    }

    public function testShowKeys(): void
    {
        $sql = $this->builder->showKeys('table1');
        self::assertSame('SHOW KEYS FROM table1', $sql);

        $sql = $this->builder->showKeys('db1.table2');
        self::assertSame('SHOW KEYS FROM db1.table2', $sql);

        self::expectException(QueryBuilderException::class);
        $this->builder->showKeys('');
    }

    /**
     * @dataProvider dataProviderTestInsertOne
     */
    public function testInsertOne(string $sql, string $table, $data): void
    {
        $real = $this->builder->insertOne($data, $table, 'INSERT');
        self::assertSame($sql, $real);

        self::expectException(QueryBuilderException::class);
        $this->builder->insertOne($data, '', 'INSERT');
    }

    public function testInsertReplaceOneException(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->insertOne(['a'], '', 'INSERT');

        self::expectException(QueryBuilderException::class);
        $this->builder->insertOne([], 'db1.table1', 'INSERT');

        self::expectException(QueryBuilderException::class);
        $this->builder->insertOne(['a'], '', 'REPLACE');

        self::expectException(QueryBuilderException::class);
        $this->builder->insertOne([], 'db1.table1', 'REPLACE');
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

    public function testEscape(): void
    {
        $esc = $this->createMock(MydbMysqliEscapeStringInterface::class);
        $builder = new MydbQueryBuilder($esc);

        $esc->expects(self::once())->method('realEscapeString')->willReturn(null);
        self::expectException(QueryBuilderException::class);
        self::expectExceptionMessage('Failed to escape value: a $ b');
        $builder->escape('a $ b');
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
     * @dataProvider dataProviderTestBuildWhere
     */
    public function testBuildWhere($sql, array $fields, array $negativeFields, array $likeFields): void
    {
        $real = $this->builder->buildWhere($fields, $negativeFields, $likeFields);
        self::assertSame($sql, $real);
    }

    public function testBuildWhereException(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildWhere([], []);
    }

    public function testBuildInsertManyException(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildInsertMany(['a' => 'b'], ['c'], '', false, '');

        self::expectException(QueryBuilderException::class);
        $this->builder->buildInsertMany(['a' => 'b'], [], 'table', false, '');

        self::expectException(QueryBuilderException::class);
        $this->builder->buildInsertMany([], ['c'], 'table', false, '');

        self::expectException(QueryBuilderException::class);
        $this->builder->buildInsertMany([], [1 => '2'], 'table', false, '');
    }

    public function testBuildUpdateWhereManyException1(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhereMany([], [], '');
    }

    public function testBuildUpdateWhereManyException2(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhereMany(['a' => 'b'], [], 'table1');
    }

    public function testBuildUpdateWhereManyException3(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhereMany(['a' => []], [], 'table1');
    }

    public function testBuildUpdateWhereManyException4(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhereMany(['a' => ['a' => ['c']]], [], 'table1');
    }

    public function testBuildUpdateWhereManyException5(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhereMany(['a' => ['a' => ['c', 'd', 'e']]], [], 'table1');
    }

    public function testBuildUpdateWhereManyException6(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhereMany(['a' => ['a' => [null, 'd', 'e']]], [], 'table1');
    }

    public function testBuildUpdateWhereManyException7(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhereMany(['a' => ['a' => ['c', null,]]], [], 'table1');
    }

    /**
     * @return array<array<string, string>>
     * @phpcs:disable Generic.Files.LineLength.TooLong
     */
    public function dataProviderTestBuildUpdateWhereMany(): array
    {
        return [
            'simple' => [
                'sql' => "UPDATE table1 SET id = CASE WHEN (id = '1') THEN 2 WHEN (id = 3.3) THEN '4' ELSE id SET name = CASE WHEN (name = 'oldname') THEN NOW() ELSE name END WHERE xxx='yyy'",
                'columnSetWhere' => [
                    'id' => [
                        ['1', 2],
                        [3.30, '4'],
                    ],
                    'name' => [
                        ['oldname', new MydbExpression('NOW()')],
                    ],
                ],
                'where' => ['xxx' => 'yyy'],
                'table' => 'table1',
            ],
            'prefixed simple' => [
                'sql' => "UPDATE db1.table1 SET table1.id = CASE WHEN (table1.id = '1') THEN 2 WHEN (table1.id = 3) THEN '4' ELSE table1.id SET table1.name = CASE WHEN (table1.name = 'oldname') THEN NOW() ELSE table1.name END WHERE table1.xxx='yyy'",
                'columnSetWhere' => [
                    'table1.id' => [
                        ['1', 2],
                        [3, '4'],
                    ],
                    'table1.name' => [
                        ['oldname', new MydbExpression('NOW()')],
                    ],
                ],
                'where' => ['table1.xxx' => 'yyy'],
                'table' => 'db1.table1',
            ],
        ];
    }

    /**
     * @return array<array<string, string>>
     * @phpcs:disable Generic.Files.LineLength.TooLong
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    public function dataProviderTestBuildWhere(): array
    {
        $simples = [
            'simple' => [
                'sql' => "WHERE id=1",
                'fields' => ['id' => 1],
                'negativeFields' => [],
                'likeFields' => [],
            ],
        ];

        $nullTests = [
            'simple array count >1' => [
                'sql' => "WHERE id IN ('a','b','c')",
                'fields' => ['id' => ['a', 'b', 'c']],
                'negativeFields' => [],
                'likeFields' => [],
            ],
            'negative array count >1' => [
                'sql' => "WHERE id NOT IN ('a','b','c')",
                'fields' => ['id' => ['a', 'b', 'c']],
                'negativeFields' => ['id'],
                'likeFields' => [],
            ],
            'simple null array count >1' => [
                'sql' => "WHERE (id IN ('a','b') OR id IS NULL)",
                'fields' => ['id' => ['a', 'b', null]],
                'negativeFields' => [],
                'likeFields' => [],
            ],
            'negative null array count >1' => [
                'sql' => "WHERE (id NOT IN ('a','b') AND id IS NOT NULL)",
                'fields' => ['id' => ['a', 'b', null]],
                'negativeFields' => ['id'],
                'likeFields' => [],
            ],
            'simple array count 1' => [
                'sql' => "WHERE id='abcd'",
                'fields' => ['id' => ['abcd']],
                'negativeFields' => [],
                'likeFields' => [],
            ],
            'negative array count 1' => [
                'sql' => "WHERE id!='abcd'",
                'fields' => ['id' => ['abcd']],
                'negativeFields' => ['id'],
                'likeFields' => [],
            ],
            'simple null' => [
                'sql' => "WHERE id IS NULL",
                'fields' => ['id' => null],
                'negativeFields' => [],
                'likeFields' => [],
            ],
            'negative null' => [
                'sql' => "WHERE id IS NOT NULL",
                'fields' => ['id' => null],
                'negativeFields' => ['id'],
                'likeFields' => [],
            ],
            'like null' => [
                'sql' => "WHERE id IS NULL",
                'fields' => ['id' => null],
                'negativeFields' => [],
                'likeFields' => ['id'],
            ],
        ];

        $result = array_merge($simples, $nullTests);
        foreach (["'1a'" => '1a', 2 => 2, '2.5' => 2.5] as $where => $simpleTypes) {
            foreach ($simples as $stest) {
                $stest['fields']['id'] = $simpleTypes;
                foreach ([true, false] as $negative) {
                    $stest['negativeFields'] = $negative ? ['id'] : [];
                    foreach ([true, false] as $like) {
                        if (false === $like) {
                            $stest['sql'] = "WHERE id" . ($negative ? '!=' : '=') . $where;
                        } else {
                            $stest['sql'] = "WHERE id" . ($negative ? ' NOT LIKE ' : ' LIKE ') . $where;
                        }
                        $stest['likeFields'] = $like ? ['id'] : [];
                        $result[] = $stest;
                    }
                }
            }
        }

        return $result;
    }

    public function testbuildDeleteWhereExceptions(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildDeleteWhere('', ['a' => 'b']);

        self::expectException(QueryBuilderException::class);
        $this->builder->buildDeleteWhere('table1', ['a']);

        self::expectException(QueryBuilderException::class);
        $this->builder->buildDeleteWhere('table1', []);
    }

    public function testBuildUpdateWhereExceptions1(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhere(['a'], [], 'table');
    }

    public function testBuildUpdateWhereExceptions2(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhere([1 => 'a'], [], 'table');
    }

    public function testBuildUpdateWhereExceptions3(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhere(['a' => 'b'], [], 'table');
    }

    public function testBuildUpdateWhereExceptions4(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhere(['a' => 'b'], [], '');
    }

    public function testBuildUpdateWhereExceptions5(): void
    {
        self::expectException(QueryBuilderException::class);
        $this->builder->buildUpdateWhere([], [], 'table');
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
