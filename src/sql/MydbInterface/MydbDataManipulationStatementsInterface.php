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
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface MydbDataManipulationStatementsInterface
{
    /**
     * @param array<array-key, float|int|string|MydbExpression> $update
     */
    public function updateWhere(array $update, array $whereFields, string $table, array $whereNotFields = []): bool;

    public function deleteWhere(array $whereFields, string $table, array $whereNotFields = []): void;

    public function updateWhereMany(array $columnSetWhere, array $where, string $table): void;

    public function insertMany(array $data, array $columns, string $table, bool $ignore = false, ?string $onDuplicate = null): void;

    public function insertOne(array $data, string $table): ?string;

    public function replaceOne(array $data, string $table): ?string;

    public function select(string $query): ?array;

    public function insert(string $query): ?string;

    public function update(string $query): ?int;

    public function delete(string $query): ?int;

    public function replace(string $query): ?string;

    public function table(string $query): ?array;

    public function values(string $query): ?array;

    public function call(string $query): void;

    public function do(string $query): void;

    public function handler(string $query): void;
}
