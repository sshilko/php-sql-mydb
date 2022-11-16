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
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @category interfaces
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface TransactionInterface
{
    public function beginTransaction(): void;

    public function commitTransaction(): void;

    public function rollbackTransaction(): void;

    public function setTransactionIsolationLevel(string $isolationLevel): void;
}
