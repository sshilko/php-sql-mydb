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

namespace sql;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface MydbInterface
{
    /**
     * Open connection
     */
    public function open(int $retry): bool;

    /**
     * Close connection
     */
    public function close(): void;

    /**
     * Get table primary key
     */
    public function getPrimaryKey(string $table): ?string;

    /**
     * @return array<string>
     */
    public function getEnumValues(string $table, string $column): array;

    public function deleteWhere(array $whereFields, string $table, array $whereNotFields = []): void;

    /**
     * @param array<array-key, float|int|string|MydbExpression> $update
     */
    public function updateWhere(array $update, array $whereFields, string $table, array $whereNotFields = []): bool;

    public function updateWhereMany(array $columnSetWhere, array $where, string $table): void;

    public function insertMany(
        array $data,
        array $columns,
        string $table,
        bool $ignore = false,
        ?string $onDuplicate = null
    ): void;

    /**
     * Add data to database table
     *
     * Returns the value of the AUTO_INCREMENT field that was updated by the previous query.
     * Returns zero if there was no previous query on the connection
     * or if the query did not update an AUTO_INCREMENT value.
     */
    public function insertOne(array $data, string $table): ?string;

    /**
     * Replace data in database table
     *
     * Returns the value of the AUTO_INCREMENT field that was updated by the previous query.
     * Returns zero if there was no previous query on the connection
     * or if the query did not update an AUTO_INCREMENT value.
     */
    public function replaceOne(array $data, string $table): ?string;

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint
     */
    public function select(string $query): array;

    public function delete(string $query): ?int;

    public function update(string $query): ?int;

    /**
     * Add data to database table
     *
     * Returns the value of the AUTO_INCREMENT field that was updated by the previous query.
     * Returns zero if there was no previous query on the connection
     * or if the query did not update an AUTO_INCREMENT value.
     */
    public function insert(string $query): ?string;

    /**
     * Replace data in database table
     *
     * Returns the value of the AUTO_INCREMENT field that was updated by the previous query.
     * Returns zero if there was no previous query on the connection
     * or if the query did not update an AUTO_INCREMENT value.
     */
    public function replace(string $query): ?string;

    public function escape(string $unescaped): string;
}
