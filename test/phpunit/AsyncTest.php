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

use sql\MydbMysqli;
use sql\MydbOptions;
use function time;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class AsyncTest extends includes\BaseTestCase
{
    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function testAsync(): void
    {
        $mysqli = new MydbMysqli();
        $options = new MydbOptions();
        $options->setReadonly(false);
        $options->setAutocommit(true);
        $options->setPersistent(false);

        $db = $this->getDefaultDb($mysqli, $options);

        $db->open();
        $db->async('SELECT 1');
        $db->close();

        self::expectNotToPerformAssertions();
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function testAsyncMock(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = new MydbOptions();
        $options->setReadonly(false);
        $options->setAutocommit(true);
        $options->setPersistent(false);

        $db = $this->getDefaultDb($mysqli, $options);

        $sql = 'SELECT 2' . time();

        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::atLeastOnce())->method('close')->willReturn(true);
        $mysqli->expects(self::once())->method('isTransactionOpen')->willReturn(false);
        $mysqli->expects(self::once())->method('mysqliQueryAsync')->with($sql)->willReturn(true);

        $db->open();
        $db->async($sql);
        $db->close();
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function testAsyncReal(): void
    {
        $mysqli = new MydbMysqli();
        $options = new MydbOptions();
        $options->setReadonly(false);
        $options->setAutocommit(true);
        $options->setPersistent(false);

        $db = $this->getDefaultDb($mysqli, $options);

        $sql = 'SELECT 234';

        $db->open();
        $result = $mysqli->mysqliQueryAsync($sql);
        $db->close();

        self::assertTrue($result);
    }
}
