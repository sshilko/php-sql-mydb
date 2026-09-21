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
use sql\MydbCredentials;
use sql\MydbCredentialsInterface;
use const MYSQLI_CLIENT_SSL;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MydbCredentialsTest extends TestCase
{

    public function testDefaultParameterValues(): void
    {
        $credentials = new MydbCredentials('127.0.0.1', 'root', 'root', 'mydb');

        self::assertNull($credentials->getPort());
        self::assertNull($credentials->getSocket());
        self::assertSame(0, $credentials->getFlags());
    }

    public function testConstructWithAllParameters(): void
    {
        $credentials = new MydbCredentials(
            'mysql.example.com',
            'testuser',
            'secret',
            'example_db',
            3307,
            '/var/run/mysqld/mysqld.sock',
            MYSQLI_CLIENT_SSL
        );

        self::assertSame('mysql.example.com', $credentials->getHost());
        self::assertSame('testuser', $credentials->getUsername());
        self::assertSame('secret', $credentials->getPasswd());
        self::assertSame('example_db', $credentials->getDbname());
        self::assertSame(3307, $credentials->getPort());
        self::assertSame('/var/run/mysqld/mysqld.sock', $credentials->getSocket());
        self::assertSame(MYSQLI_CLIENT_SSL, $credentials->getFlags());
    }

    public function testImplementsCredentialsInterface(): void
    {
        $credentials = new MydbCredentials('127.0.0.1', 'root', 'root', 'mydb');
        self::assertInstanceOf(MydbCredentialsInterface::class, $credentials);
    }

    public function testTypedGettersReturnSameValues(): void
    {
        $credentials = new MydbCredentials('db', 'u', 'p', 'd', 3306, '/tmp/sock', 16);

        self::assertSame('db', $credentials->getHost());
        self::assertSame('u', $credentials->getUsername());
        self::assertSame('p', $credentials->getPasswd());
        self::assertSame('d', $credentials->getDbname());
        self::assertIsInt($credentials->getPort());
        self::assertIsString($credentials->getSocket());
        self::assertIsInt($credentials->getFlags());
    }
}
