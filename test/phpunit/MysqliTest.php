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

use sql\MydbEnvironment;
use sql\MydbMysqli;
use sql\MydbOptions;
use function mysqli_init;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MysqliTest extends includes\DatabaseTestCase
{
    public function testReuseResource(): void
    {
        $resource = mysqli_init();
        $mysqli = new MydbMysqli($resource);
        $result = $mysqli->init();
        self::assertFalse($result);
    }

    /**
     * @throws \phpunit\EnvironmentException
     */
    public function testOptionsNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->setTransportOptions(new MydbOptions(), new MydbEnvironment());
        self::assertFalse($result);
    }

    public function testQueryNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->realQuery('SELECT 1');
        self::assertFalse($result);
    }

    public function testReadResponseNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->readServerResponse(new MydbEnvironment());
        self::assertNull($result);
    }

    public function testExtractResponseNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $events = [];
        $result = $mysqli->extractServerResponse(new MydbEnvironment(), $events);
        self::assertNull($result);
        self::assertSame([], $events);
    }

    public function testEscapeNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->realEscapeString('hello');
        self::assertNull($result);
    }

    public function testTransactionsNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->beginTransactionReadonly();
        self::assertFalse($result);
        $result = $mysqli->beginTransactionReadwrite();
        self::assertFalse($result);
        $result = $mysqli->rollback();
        self::assertFalse($result);
        $result = $mysqli->commitAndRelease();
        self::assertFalse($result);
        $result = $mysqli->commit();
        self::assertFalse($result);
        $result = $mysqli->autocommit(true);
        self::assertFalse($result);
        $result = $mysqli->autocommit(false);
        self::assertFalse($result);
    }

    public function testAsyncNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->mysqliQueryAsync('SELECT 1');
        self::assertFalse($result);
    }

    public function testCloseNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->close();
        self::assertFalse($result);
    }

    public function testAffectedRowsNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->getAffectedRows();
        self::assertNull($result);
    }

    public function testWarningsNoInit(): void
    {
        $mysqli = new MydbMysqli();
        $result = $mysqli->getWarnings();
        self::assertSame([], $result);
    }
}
