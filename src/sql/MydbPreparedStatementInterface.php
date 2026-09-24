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

namespace sql;

/**
 * Wraps a mysqli prepared statement: bind, execute, fetch all, affected rows,
 * insert id and close.
 *
 * The prepared path is strictly opt-in. Raw query()/command() remain the fast
 * path; prepared statements trade a little raw speed for parameterized
 * execution and remove the manual escape() responsibility.
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface MydbPreparedStatementInterface
{
    /**
     * Bind parameters using mysqli bind_param() type inference:
     * int/bool as integer, float as double, everything else as string (null is
     * sent as SQL NULL).
     *
     * @param list<float|int|string|null|bool> $params
     */
    public function bind(array $params): bool;

    public function execute(): bool;

    /**
     * Returns the full result set as a list of associative rows, or null when
     * the statement produced no result set (for example DML statements).
     *
     * @psalm-return list<array<array-key, float|int|string|null>>|null
     */
    public function fetchAll(): ?array;

    public function getAffectedRows(): ?int;

    public function getInsertId(): int|string|null;

    public function close(): void;
}