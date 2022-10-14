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
final class MetaTest extends includes\BaseTestCase
{
    public function testOpen(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $actual = $db->select("SELECT 2 as n");
        self::assertSame([['n' => '2']], $actual);
        $db->close();
    }

    public function testClose(): void
    {
        $db = $this->getDefaultDb();
        $actual = $db->select("SELECT 1 as n");
        self::assertSame([['n' => '1']], $actual);
        $db->close();
    }

    public function testPrimaryKey(): void
    {
        $db = $this->getDefaultDb();
        $actual = $db->getPrimaryKey('myusers');
        self::assertSame('id', $actual);
    }

    public function testEnum(): void
    {
        $db = $this->getDefaultDb();
        $actual = $db->getEnumValues('myusers_devices', 'handler');
        self::assertSame(['1', '2', '3'], $actual);
    }

    public function testSet(): void
    {
        $db = $this->getDefaultDb();
        $actual = $db->getEnumValues('myusers_devices', 'provider');
        self::assertSame(['Sansunk', 'Hookle', 'Sany'], $actual);
    }

    public function testEscape(): void
    {
        $input = [
            'a' => 'a',
            '1' => '1',
            '\a' => '\\\a',
            "drop \" table" => "drop \\\" table",
            "drox \' table" => "drox \\\\\' table",
            'droc " table' => 'droc " table',
            "droe ' table" => "droe \' table",
            " Jown's Woo's " => " Jown\'s Woo\'s ",
            "x0011" => "x0011",
            "\x0011" => "\\011",
            "\x1a" => "\\Z",
            "\r" => "\\r",
            "a\nb" => "a\\nb",
            'NULL' => 'NULL',
        ];

        $db = $this->getDefaultDb();
        foreach ($input as $in => $expect) {
            $actual = $db->escape((string) $in);
        }
        self::assertSame($expect, $actual);
    }
}
