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
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface MydbQueryBuilderInterface
{
    public const SQL_INSERT  = 'INSERT';
    public const SQL_REPLACE = 'REPLACE';

    public function showColumnsLike(string $table, string $column): string;

    public function showKeys(string $table): string;

    /**
     * @param array<string, (float|int|\sql\MydbExpressionInterface|string|null)> $data
     * @psalm-return string
     */
    public function insertOne(array $data, string $table, string $type): string;

    /**
     * @param array  $columnSetWhere ['col1' => [ ['current1', 'new1'], ['current2', 'new2']]
     * @param array  $where          ['col2' => 'value2', 'col3' => ['v3', 'v4']]
     * @param string $table          'mytable'
     */
    public function buildUpdateWhereMany(array $columnSetWhere, array $where, string $table): string;

    /**
     * @throws \sql\MydbException\QueryBuilderException
     * @param array<string, (float|int|string|\sql\MydbExpressionInterface|null)> $update
     */
    public function buildUpdateWhere(
        array $update,
        array $whereFields,
        string $table,
        array $whereNotFields = [],
    ): ?string;

    public function buildDeleteWhere(string $table, array $fields = [], array $negativeFields = []): ?string;

    /**
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function buildWhere(array $fields, array $negativeFields = [], array $likeFields = []): string;

    /**
     * @param array<string> $cols
     */
    public function buildInsertMany(array $data, array $cols, string $table, bool $ignore, string $onDuplicate): string;

    /**
     * @param float|int|string|\sql\MydbExpressionInterface|null $unescaped
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function escape($unescaped, string $quote = "'"): string;
}
