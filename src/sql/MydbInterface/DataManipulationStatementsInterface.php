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

namespace sql\MydbInterface;

/**
 * These statements do not implicitly commit the current transaction.
 *
 * Data Manipulation Language (DML) statements are used for managing data within
 * schema objects DML deals with data manipulation, and therefore includes most common
 * SQL statements such as SELECT, INSERT, etc. DML allows adding / modifying / deleting data itself.
 *
 * @see https://dev.mysql.com/doc/refman/8.0/en/sql-data-manipulation-statements.html
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @category interfaces
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface DataManipulationStatementsInterface
{
    /**
     * @param array<string, (float|int|string|\sql\MydbExpressionInterface|null)> $update
     */
    public function updateWhere(array $update, array $whereFields, string $table, array $whereNotFields = []): ?int;

    public function deleteWhere(array $whereFields, string $table, array $whereNotFields = []): ?int;

    public function updateWhereMany(array $columnSetWhere, array $where, string $table): void;

    /**
     * @psalm-param array<array-key, array<(float|int|string|\sql\MydbExpressionInterface|null)>> $data
     * @param array<string> $cols
     */
    public function insertMany(
        array $data,
        array $cols,
        string $table,
        bool $ignore = false,
        string $onDuplicateSql = '',
    ): void;

    /**
     * @param array<string, (float|int|\sql\MydbExpressionInterface|string|null)> $data
     */
    public function insertOne(array $data, string $table): ?string;

    /**
     * @param array<string, (float|int|\sql\MydbExpressionInterface|string|null)> $data
     */
    public function replaceOne(array $data, string $table): ?string;

    public function select(string $query): ?array;

    public function insert(string $query): ?string;

    public function update(string $query): ?int;

    public function delete(string $query): ?int;

    public function replace(string $query): ?string;
}
