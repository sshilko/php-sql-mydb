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

use sql\MydbException\EnvironmentException;
use function count;
use function error_reporting;
use function gc_collect_cycles;
use function gc_enabled;
use function ignore_user_abort;
use function ini_set;
use function pcntl_signal;
use function pcntl_signal_dispatch;
use function pcntl_signal_get_handler;
use function set_error_handler;
use const E_ALL;
use const E_STRICT;
use const SIG_DFL;
use const SIGHUP;
use const SIGINT;
use const SIGTERM;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbEnvironment
{
    protected array $knownSignals = [SIGTERM, SIGINT, SIGHUP];

    /**
     * @var array<int>
     */
    protected array $trappedSignals = [];

    /**
     * Backup of signal handlers
     *
     * @var array
     */
    protected array $trappedHandlers = [];

    /**
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function gc_collect_cycles(): void
    {
        if (!gc_enabled()) {
            return;
        }

        gc_collect_cycles();
    }

    /**
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function set_error_handler(?callable $callback = null, int $error_levels = E_ALL|E_STRICT): ?callable
    {
        return set_error_handler($callback ?? $this->getNullErrorHandler(), $error_levels);
    }

    /**
     * @throws EnvironmentException
     */
    public function setMysqlndNetReadTimeout(string $timeoutSeconds): bool
    {
        return (bool) $this->ini_set('mysqlnd.net_read_timeout', $timeoutSeconds);
    }

    /**
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function error_reporting(int $level): int
    {
        return error_reporting($level);
    }

    /**
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function ignore_user_abort(): int
    {
        return ignore_user_abort();
    }

    /**
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     * @throws EnvironmentException
     */
    public function ini_set(string $key, string $value): string
    {
        $result = ini_set($key, $value);
        if (false === $result) {
            throw new EnvironmentException();
        }

        return $result;
    }

    /**
     * Disable custom signal handler
     *
     * @see https://wiki.php.net/rfc/async_signals
     * @see https://blog.pascal-martin.fr/post/php71-en-other-new-things/
     * @return array|null array of trapped signals
     * @throws EnvironmentException
     */
    public function endSignalsTrap(): ?array
    {
        /**
         * Process signals
         */
        if (!pcntl_signal_dispatch()) {
            throw new EnvironmentException();
        }

        $trappedSignals = $this->trappedSignals;
        foreach ($this->knownSignals as $signalNumber) {
            /**
             * Reset signals to previous/default handler
             */
            if (!pcntl_signal($signalNumber, $this->trappedHandlers[$signalNumber] ?? SIG_DFL)) {
                throw new EnvironmentException();
            }
            unset($this->trappedHandlers[$signalNumber]);
        }
        $this->trappedSignals = [];

        return count($trappedSignals) > 0
            ? $trappedSignals
            : null;
    }

    /**
     * Enable custom signal handler
     *
     * @see https://wiki.php.net/rfc/async_signals
     * @see https://blog.pascal-martin.fr/post/php71-en-other-new-things/
     * @throws EnvironmentException
     */
    public function startSignalsTrap(): void
    {
        $this->trappedSignals = [];

        $signalHandler = function (int $signalNumber): void {
            $this->trappedSignals[] = $signalNumber;
        };

        foreach ($this->knownSignals as $signalNumber) {
            $originalNandler = pcntl_signal_get_handler($signalNumber);
            $this->trappedHandlers[$signalNumber] = $originalNandler;

            if (!pcntl_signal($signalNumber, $signalHandler)) {
                throw new EnvironmentException();
            }
        }
    }

    protected function getNullErrorHandler(): callable
    {
        return static function () {
            return true;
        };
    }
}
