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
     * @throws MydbException
     */
    public function open(): void;

    /**
     * @throws MydbException
     */
    public function close(): void;

    /**
     * @throws MydbException
     */
    public function getPrimaryKey(string $table): ?string;

    /** @return array<string> */
    public function getEnumValues(string $table, string $column): array;

    /**
     * @throws MydbException
     */
    public function command(string $query, ?int $retry = null): bool;

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint
     * @throws MydbException
     */
    public function query(string $query): array;

    /**
     * @throws MydbException
     */
    public function deleteWhere(array $whereFields, string $table, array $whereNotFields = []): void;

    /**
     * @param array<array-key, float|int|string|MydbExpression> $update
     * @throws MydbException
     */
    public function updateWhere(array $update, array $whereFields, string $table, array $whereNotFields = []): bool;

    /**
     * @throws MydbException
     */
    public function updateWhereMany(array $columnSetWhere, array $where, string $table): void;

    /**
     * @throws MydbException
     */
    public function insertMany(
        array $data,
        array $columns,
        string $table,
        bool $ignore = false,
        ?string $onDuplicateKeyUpdate = null
    ): void;

    /**
     * Add data to database table
     *
     * Returns the value of the AUTO_INCREMENT field that was updated by the previous query.
     * Returns zero if there was no previous query on the connection
     * or if the query did not update an AUTO_INCREMENT value.
     *
     * @throws MydbException
     */
    public function insertOne(array $data, string $table): ?string;

    /**
     * Replace data in database table
     *
     * Returns the value of the AUTO_INCREMENT field that was updated by the previous query.
     * Returns zero if there was no previous query on the connection
     * or if the query did not update an AUTO_INCREMENT value.
     *
     * @throws MydbException
     */
    public function replaceOne(array $data, string $table): ?string;

    /**
     * @throws MydbException
     */
    public function async(string $command): void;

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint
     * @throws MydbException
     */
    public function select(string $query): array;

    /**
     * @throws MydbException
     */
    public function delete(string $query): ?int;

    /**
     * @throws MydbException
     */
    public function update(string $query): ?int;

    /**
     * Add data to database table
     *
     * Returns the value of the AUTO_INCREMENT field that was updated by the previous query.
     * Returns zero if there was no previous query on the connection
     * or if the query did not update an AUTO_INCREMENT value.
     *
     * @throws MydbException
     */
    public function insert(string $query): ?string;

    /**
     * Replace data in database table
     *
     * Returns the value of the AUTO_INCREMENT field that was updated by the previous query.
     * Returns zero if there was no previous query on the connection
     * or if the query did not update an AUTO_INCREMENT value.
     *
     * @throws MydbException
     */
    public function replace(string $query): ?string;

    /**
     * @throws MydbException
     */
    public function escape(string $unescaped): string;

    /**
     * @throws MydbException
     */
    public function beginTransaction(): void;

    /**
     * @throws MydbException
     */
    public function commitTransaction(): void;

    /**
     * @throws MydbException
     */
    public function rollbackTransaction(): void;

    /**
     * @throws MydbException
     */
    public function setAutoCommit(bool $autocommit): bool;
}
