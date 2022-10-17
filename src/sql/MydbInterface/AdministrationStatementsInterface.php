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
 * @see https://dev.mysql.com/doc/refman/8.0/en/sql-server-administration-statements.html
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface AdministrationStatementsInterface
{
    /**
     * Get table primary key
     */
    public function getPrimaryKey(string $table): ?string;

    /**
     * @return array<string>
     */
    public function getEnumValues(string $table, string $column): array;

    /**
     * @return array<string>
     */
    public function getSetValues(string $table, string $column): array;
}
