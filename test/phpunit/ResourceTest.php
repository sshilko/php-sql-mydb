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

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class ResourceTest extends includes\BaseTestCase
{
    public function testOpen(): void
    {
        $db = $this->getDefaultDb();
        self::assertTrue($db->open());
    }

    public function testSimpleClose(): void
    {
        $db = $this->getDefaultDb();
        self::assertNull($db->close());
    }

    public function testCloseNotConnected(): void
    {
        $mysqli = self::createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);
        $mysqli->expects(self::once())->method('isConnected')->willReturn(false);
        $db->close();
    }

    public function testWillCommitNotPersistentTransactionWhenNoAutocommitAndNoTransactionOnClose(): void
    {
        $mysqli = self::createMock(MydbMysqli::class);
        $options = self::createMock(MydbOptions::class);
        $envs = self::createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isAutocommit')->willReturn(false);
        $mysqli->method('isTransactionOpen')->willReturn(false);
        $options->method('isPersistent')->willReturn(false);
        $mysqli->expects(self::once())->method('commit')->with(4)->willReturn(true);
        $mysqli->method('close')->willReturn(true);
        $envs->expects(self::once())->method('gc_collect_cycles');
        $db->close();
    }

    public function testWillCommitIsPersistentTransactionWhenNoAutocommitAndNoTransactionOnClose(): void
    {
        $mysqli = self::createMock(MydbMysqli::class);
        $options = self::createMock(MydbOptions::class);
        $envs = self::createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isAutocommit')->willReturn(false);
        $mysqli->method('isTransactionOpen')->willReturn(false);
        $options->method('isPersistent')->willReturn(true);
        $mysqli->expects(self::once())->method('commit')->with(8)->willReturn(true);
        $mysqli->method('close')->willReturn(true);
        $envs->expects(self::once())->method('gc_collect_cycles');
        $db->close();
    }

    public function testNoGcWhenNotConnected(): void
    {
        $mysqli = self::createMock(MydbMysqli::class);
        $envs = self::createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, null, $envs);
        $mysqli->method('isConnected')->willReturn(false);
        $envs->expects(self::never())->method('gc_collect_cycles');
        $db->close();
    }

    public function testGcWhenConnected(): void
    {
        $mysqli = self::createMock(MydbMysqli::class);
        $envs = self::createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, null, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $mysqli->method('commit')->willReturn(true);
        $mysqli->method('close')->willReturn(true);

        $mysqli->method('isConnected')->willReturn(false);
        $envs->expects(self::once())->method('gc_collect_cycles');
        $db->close();
    }

    public function testDoNoCommitTransactionWhenAutocommitEnabledOnClose(): void
    {
        $mysqli = self::createMock(MydbMysqli::class);
        $options = self::createMock(MydbOptions::class);
        $envs = self::createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isAutocommit')->willReturn(true);
        $mysqli->method('isTransactionOpen')->willReturn(false);
        $options->method('isPersistent')->willReturn(true);
        $mysqli->expects(self::never())->method('commit');
        $mysqli->method('close')->willReturn(true);
        $db->close();
    }

    public function testDoNoCommitTransactionWhenTransactionExplicitlyStartedOnClose(): void
    {
        $mysqli = self::createMock(MydbMysqli::class);
        $options = self::createMock(MydbOptions::class);
        $envs = self::createMock(MydbEnvironment::class);
        $db = $this->getDefaultDb($mysqli, $options, $envs);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isAutocommit')->willReturn(false);
        $mysqli->method('isTransactionOpen')->willReturn(true);
        $mysqli->expects(self::never())->method('commit');
        $mysqli->method('close')->willReturn(true);
        $db->close();
    }
}
