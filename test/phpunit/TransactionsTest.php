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
use sql\MydbException\TransactionIsolationException;
use sql\MydbException\TransactionRollbackException;
use sql\MydbMysqli;
use sql\MydbOptions;
use function str_replace;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class TransactionsTest extends includes\DatabaseTestCase
{
    /**
     * @throws \phpunit\MydbException
     * @throws \phpunit\ConnectException
     */
    public function testBeginTransactionReadonlyReal(): void
    {
        $mysqli = new MydbMysqli();
        $options = new MydbOptions();
        $options->setReadonly(true);

        $db = $this->getDefaultDb($mysqli, $options);
        $db->open();

        $db->beginTransaction();
        $data = $db->select('SELECT 1');
        self::assertSame([['1' => '1']], $data);

        $db->rollbackTransaction();
        $db->close();
    }

    /**
     * @throws \phpunit\MydbException
     * @throws \phpunit\ConnectException
     */
    public function testBeginTransactionReadWriteReal(): void
    {
        $db = $this->getDefaultDb();
        $db->open();

        $db->beginTransaction();
        $data = $db->select('SELECT 2 as n');
        self::assertSame([['n' => '2']], $data);

        $db->commitTransaction();
        $db->close();
    }

    /**
     * @throws \sql\MydbException
     */
    public function testTransactionIsolationLevelException(): void
    {
        $mysqli = $this->createMock(MydbMysqli::class);
        $db = $this->getDefaultDb($mysqli);
        $mysqli->expects(self::once())->method('setTransactionIsolationLevel')->willReturn(false);
        self::expectException(TransactionIsolationException::class);
        $db->setTransactionIsolationLevel('hello');
    }

    public function testTransactionIsolationLevel(): void
    {
        $db = $this->getDefaultDb();
        $db->open();

        $levels = [
            'REPEATABLE READ',
            'REPEATABLE READ',
            'READ COMMITTED',
            'READ UNCOMMITTED',
            'SERIALIZABLE',
        ];

        foreach ($levels as $l) {
            $db->setTransactionIsolationLevel($l);
            $row = $db->select('SELECT @@transaction_isolation as n');
            self::assertSame($row[0]['n'], str_replace(' ', '-', $l));
        }

        $db->close();
    }

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
