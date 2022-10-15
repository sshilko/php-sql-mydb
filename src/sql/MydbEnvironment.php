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

use function error_reporting;
use function gc_collect_cycles;
use function gc_enabled;
use function ignore_user_abort;
use function ini_get;
use function ini_set;
use function set_error_handler;
use const E_ALL;
use const E_STRICT;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName
 */
class MydbEnvironment
{
    public function gc_collect_cycles(): void
    {
        if (!gc_enabled()) {
            return;
        }

        gc_collect_cycles();
    }

    public function set_error_handler(?callable $callback, int $error_levels = E_ALL|E_STRICT): ?callable
    {
        return set_error_handler($callback, $error_levels);
    }

    public function setMysqlndNetReadTimeout(string $timeoutSeconds): bool
    {
        return $this->ini_set('mysqlnd.net_read_timeout', $timeoutSeconds);
    }

    public function error_reporting(int $level): int
    {
        return error_reporting($level);
    }

    public function ignore_user_abort(): int
    {
        return ignore_user_abort();
    }

    public function ini_set(string $key, string $value): bool
    {
        return false !== ini_set($key, $value);
    }

    public function ini_get(string $key): ?string
    {
        $ini = ini_get($key);
        if (false === ini_get($key)) {
            return null;
        }

        return $ini;
    }
}
