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
 * @see https://dev.mysql.com/doc/refman/8.0/en/sql-server-administration-statements.html
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @category interfaces
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface AdministrationStatementsInterface
{
    /**
     * Get table primary keys
     * @return ?array<string>
     */
    public function getPrimaryKeys(string $table): ?array;

    /**
     * @return array<string>
     */
    public function getEnumValues(string $table, string $column): array;
}
