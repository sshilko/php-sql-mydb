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

use sql\MydbException\InternalException;
use sql\MydbMysqliInterface;
use function count;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class PreparedStatementTest extends includes\DatabaseTestCase
{
    public function testPreparedSelectWithAllValueTypes(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        $statement = $db->prepare('SELECT ? AS intv, ? AS strv, ? AS nullv, ? AS boolv');
        $statement->bind([1, 'x', null, true]);
        self::assertTrue($statement->execute());

        $actual = $statement->fetchAll();
        $statement->close();

        self::assertSame(
            [
                ['intv' => 1, 'strv' => 'x', 'nullv' => null, 'boolv' => 1],
            ],
            $actual
        );

        $db->rollbackTransaction();
        $db->close();
    }

    public function testPreparedInsertInsertIdAndAffectedRows(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        $statement = $db->prepare('INSERT INTO myusers (id, name) VALUES (?, ?)');
        $statement->bind([600, 'needle']);
        self::assertTrue($statement->execute());
        self::assertSame(600, (int) $statement->getInsertId());
        self::assertSame(1, $statement->getAffectedRows());
        $statement->close();

        $actual = $db->select('SELECT id, name FROM myusers WHERE id = 600');
        self::assertSame([['id' => '600', 'name' => 'needle']], $actual);

        $db->rollbackTransaction();
        $db->close();
    }

    public function testPreparedUpdateAffectedRows(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        $statement = $db->prepare('UPDATE myusers SET name = ? WHERE id = ?');
        $statement->bind(['needle2', 1]);
        self::assertTrue($statement->execute());
        self::assertSame(1, $statement->getAffectedRows());
        $statement->close();

        $db->rollbackTransaction();
        $db->close();
    }

    public function testInjectionAttemptIsBoundAsSingleParameter(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        $statement = $db->prepare('SELECT id, name FROM myusers WHERE name = ?');
        $statement->bind(['\'; DROP TABLE myusers; --']);
        self::assertTrue($statement->execute());
        self::assertSame([], $statement->fetchAll());
        $statement->close();

        /**
         * The table survived: the injected string was passed as a parameter
         * value and never executed as SQL.
         */
        self::assertCount(3, $db->select('SELECT id FROM myusers'));

        $db->rollbackTransaction();
        $db->close();
    }

    public function testExecuteConvenienceSingleShot(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        self::assertSame(
            [['id' => 1, 'name' => 'user1']],
            $db->execute('SELECT id, name FROM myusers WHERE id = ?', [1])
        );

        /**
         * DML through execute() produces no result set: null is returned.
         */
        self::assertNull($db->execute('INSERT INTO myusers (id, name) VALUES (?, ?)', [601, 'one-shot']));
        self::assertSame(
            [['id' => '601', 'name' => 'one-shot']],
            $db->select('SELECT id, name FROM myusers WHERE id = 601')
        );

        $db->rollbackTransaction();
        $db->close();
    }

    public function testPrepareFailureRaisesInternalException(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);
        $db     = $this->getDefaultDb($mysqli);

        $mysqli->expects(self::once())->method('isConnected')->willReturn(true);
        $mysqli->expects(self::once())->method('prepare')->willReturn(null);
        $mysqli->expects(self::once())->method('getError')->willReturn(null);
        $this->logger->expects(self::once())->method('error');

        $this->expectException(InternalException::class);
        $this->expectExceptionMessage('Failed to prepare the SQL statement');
        $db->prepare('SELECT ?');
    }

    public function testExecuteWithDmlLeavesNoPendingResultSet(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        self::assertNull($db->execute('DELETE FROM myusers WHERE id = ?', [1]));

        /**
         * The result set of the executed statement is fully consumed, so the
         * next query is not affected by a pending result.
         */
        self::assertSame(2, count($db->select('SELECT id FROM myusers')));

        $db->rollbackTransaction();
        $db->close();
    }
}