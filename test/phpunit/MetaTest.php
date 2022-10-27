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
            '1.1' => '1.1',
            1.10231 => '1.10231',
            1 => '1',
            122 => '122',
            123456789 => '123456789',
            '\a' => '\\\a',
            "drop \" table" => "drop \\\" table",
            "drox \' table" => "drox \\\\\' table",
            'droc " table' => 'droc \" table',
            "droe ' table" => "droe \' table",
            " Jown's Woo's " => " Jown\'s Woo\'s ",
            "x0011" => "x0011",
            "\x0011" => "\\011",
            "\x1a" => "\\Z",
            "\r" => "\\r",
            "a\nb" => "a\\nb",
            'NULL' => 'NULL',
            'null' => 'NULL',
        ];

        $db = $this->getDefaultDb();
        foreach ($input as $in => $expect) {
            $actual = $db->escape($in, '');
            self::assertSame($expect, $actual);
        }

        $actual = $db->escape(null);
        self::assertSame('', $actual);

        $actual = $db->escape(new MydbExpression('hello world " unescaped null'));
        self::assertSame('hello world " unescaped null', $actual);
    }

    public function testSingleQuotedEscape(): void
    {
        $input = [
            'aaa' => '"aaa"',
            'a b c' => '"a b c"',
            'esca\'pe' => '"esca\\\'pe"',
        ];

        $db = $this->getDefaultDb();
        foreach ($input as $in => $expect) {
            $actual = $db->escape($in, '"');
            self::assertSame($expect, $actual);
        }
    }

    public function testDoubleQuotedEscape(): void
    {
        $input = [
            'aaa' => "'aaa'",
            'a b c' => "'a b c'",
            'esca\'pe' => "'esca\\'pe'",
        ];

        $db = $this->getDefaultDb();
        foreach ($input as $in => $expect) {
            $actual = $db->escape($in, "'");
            self::assertSame($expect, $actual);
        }
    }
}
