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

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use function count;
use function fwrite;
use function is_scalar;
use function var_export;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Implementation of PSR-3 Logger that will output to STDERR & STDOUT
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 * @see https://www.php-fig.org/psr/psr-3/
 */
class MydbLogger implements LoggerInterface
{
    public function error($message, array $context = []): void
    {
        fwrite(STDERR, static::formatter($message) . PHP_EOL);
        if (!count($context)) {
            return;
        }

        fwrite(STDERR, static::formatter($context) . PHP_EOL);
    }

    public function log($level, $message, array $context = []): void
    {
        fwrite(STDOUT, (string) $level . ' ' . static::formatter($message) . PHP_EOL);
        if (!count($context)) {
            return;
        }

        fwrite(STDOUT, static::formatter($context) . PHP_EOL);
    }

    public function warning($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    public function emergency($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param string|array $var
     */
    protected static function formatter($var): string
    {
        if (is_scalar($var)) {
            return $var;
        }

        return var_export($var, true);
    }
}
