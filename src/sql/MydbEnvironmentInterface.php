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
interface MydbEnvironmentInterface
{

    /**
     * @SuppressWarnings("camelCase")
     */
    public function gc_collect_cycles(): void;

    /**
     * @SuppressWarnings("camelCase")
     */
    public function restore_error_handler(): void;

    /**
     * @SuppressWarnings("camelCase")
     */
    public function set_error_handler(?callable $callback = null, int $error_levels = E_ALL|E_STRICT): ?callable;

    public function setMysqlndNetReadTimeout(string $timeoutSeconds): bool;

    /**
     * @SuppressWarnings("camelCase")
     */
    public function error_reporting(int $level): int;

    /**
     * @SuppressWarnings("camelCase")
     */
    public function ignore_user_abort(): int;

    /**
     * @SuppressWarnings("camelCase")
     */
    public function ini_set(string $key, string $value): string;

    public function endSignalsTrap(): ?array;

    public function startSignalsTrap(): void;
}
