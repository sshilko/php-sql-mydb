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

use sql\MydbException\TransactionBeginReadonlyException;
use sql\MydbException\TransactionBeginReadwriteException;
use sql\MydbException\TransactionCommitException;
use sql\MydbException\TransactionRollbackException;
use sql\MydbMysqli;
use sql\MydbOptions;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class TransactionsTest extends includes\BaseTestCase
{
    public function testBeginTransactionReadonlySuccess(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = $this->createMock(MydbOptions::class);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isReadonly')->willReturn(true);
        $mysqli->expects(self::once())->method('beginTransactionReadonly')->willReturn(true);

        $db->beginTransaction();
    }

    public function testBeginTransactionReadonlyFailure(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = $this->createMock(MydbOptions::class);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isReadonly')->willReturn(true);
        $mysqli->expects(self::once())->method('beginTransactionReadonly')->willReturn(false);
        $this->expectException(TransactionBeginReadonlyException::class);

        $db->beginTransaction();
    }

    public function testBeginTransactionReadwriteSuccess(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = $this->createMock(MydbOptions::class);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isReadonly')->willReturn(false);
        $mysqli->expects(self::once())->method('beginTransactionReadwrite')->willReturn(true);

        $db->beginTransaction();
    }

    public function testBeginTransactionReadwriteFailure(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = $this->createMock(MydbOptions::class);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->method('isConnected')->willReturn(true);
        $options->method('isReadonly')->willReturn(false);
        $mysqli->expects(self::once())->method('beginTransactionReadwrite')->willReturn(false);
        $this->expectException(TransactionBeginReadwriteException::class);

        $db->beginTransaction();
    }

    public function testRollbackTransactionSuccess(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = $this->createMock(MydbOptions::class);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('rollback')->willReturn(true);

        $db->rollbackTransaction();
    }

    public function testRollbackTransactionFailure(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = $this->createMock(MydbOptions::class);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('rollback')->willReturn(false);
        $this->expectException(TransactionRollbackException::class);

        $db->rollbackTransaction();
    }

    public function testCommitTransactionSuccess(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = $this->createMock(MydbOptions::class);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('commit')->willReturn(true);

        $db->commitTransaction();
    }

    public function testCommitTransactionFailure(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $options = $this->createMock(MydbOptions::class);
        $db = $this->getDefaultDb($mysqli, $options);

        $mysqli->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('commit')->willReturn(false);
        $this->expectException(TransactionCommitException::class);

        $db->commitTransaction();
    }
}
