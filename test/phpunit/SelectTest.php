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

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class SelectTest extends includes\DatabaseTestCase
{
    /**
     * @return array<array<string, string>>
     */
    public function dataProviderTestSimpleSelect(): array
    {
        return [
            'simple select all' => [
                'sql' => 'SELECT * FROM myusers',
                'expects' => [
                    ['id' => '1', 'name' => 'user1'],
                    ['id' => '2', 'name' => 'user2'],
                    ['id' => '3', 'name' => 'user3'],
                ],
            ],
            'complex select all' => [
                'sql' => 'SELECT * FROM (SELECT * FROM (SELECT * FROM myusers) y ) x',
                'expects' => [
                    ['id' => '1', 'name' => 'user1'],
                    ['id' => '2', 'name' => 'user2'],
                    ['id' => '3', 'name' => 'user3'],
                ],
            ],
            'simple select JOIN' => [
                'sql' =>
                    'SELECT myusers.id, HEX(id_binary) as id_binary
                       FROM myusers
                       JOIN myusers_devices ON (myusers_devices.id_user = myusers.id)
                   ORDER BY id_binary ASC',
                'expects' => [
                    ['id' => '1', 'id_binary' => '657800000000000000000000'],
                    ['id' => '1', 'id_binary' => '657861000000000000000000'],
                    ['id' => '1', 'id_binary' => '6578616D0000000000000000'],
                    ['id' => '2', 'id_binary' => '6578616D7000000000000000'],
                ],
            ],
            'simple select UNION' => [
                'sql' =>
                    'SELECT myusers.id FROM myusers WHERE id < 2
                      UNION ALL
                     SELECT myusers.id FROM myusers WHERE id > 2',
                'expects' => [
                    ['id' => '1'],
                    ['id' => '3'],
                ],
            ],
            'simple select ORDER BY' => [
                'sql' => 'SELECT * FROM myusers ORDER BY id DESC',
                'expects' => [
                    ['id' => '3', 'name' => 'user3'],
                    ['id' => '2', 'name' => 'user2'],
                    ['id' => '1', 'name' => 'user1'],
                ],
            ],
            'simple select WHERE' => [
                'sql' => 'SELECT * FROM myusers WHERE id > 1 AND name like "%user%" LIMIT 2',
                'expects' => [
                    ['id' => '2', 'name' => 'user2'],
                    ['id' => '3', 'name' => 'user3'],
                ],
            ],
            'simple select COUNT' => [
                'sql' => 'SELECT COUNT(*) as n FROM myusers WHERE id IN (1,2,3)',
                'expects' => [
                    ['n' => '3'],
                ],
            ],
            'simple select GROUP BY' => [
                'sql' => 'SELECT COUNT(*) as n, name FROM myusers WHERE id IN (1,2,3) GROUP BY name',
                'expects' => [
                    ['n' => '1', 'name' => 'user1'],
                    ['n' => '1', 'name' => 'user2'],
                    ['n' => '1', 'name' => 'user3'],
                ],
            ],
            'simple select HAVING' => [
                'sql' => 'SELECT COUNT(*) as n, name FROM myusers WHERE id IN (1,2,3) GROUP BY name HAVING n > 0',
                'expects' => [
                    ['n' => '1', 'name' => 'user1'],
                    ['n' => '1', 'name' => 'user2'],
                    ['n' => '1', 'name' => 'user3'],
                ],
            ],
            'simple select LIMIT' => [
                'sql' => 'SELECT * FROM myusers LIMIT 1',
                'expects' => [
                    ['id' => '1', 'name' => 'user1'],
                ],
            ],
            'simple select LIMIT OFFSET' => [
                'sql' => 'SELECT * FROM myusers LIMIT 1, 1',
                'expects' => [
                    ['id' => '2', 'name' => 'user2'],
                ],
            ],
            'simple select ALL' => [
                'sql' => 'SELECT ALL * FROM myusers LIMIT 1',
                'expects' => [
                    ['id' => '1', 'name' => 'user1'],
                ],
            ],
            'simple select SQL_SMALL_RESULT' => [
                'sql' => 'SELECT ALL SQL_SMALL_RESULT * FROM myusers LIMIT 1',
                'expects' => [
                    ['id' => '1', 'name' => 'user1'],
                ],
            ],


        ];
    }

    /**
     * @dataProvider dataProviderTestSimpleSelect
     */
    public function testSimpleSelect(string $sql, $expects): void
    {
        $db = $this->getDefaultDb();
        $actual = $db->select($sql);
        self::assertSame($expects, $actual);
    }
}
