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
use function restore_error_handler;
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

    /**
     * @var array<int>
     */
    protected array $knownSignals = [SIGTERM, SIGINT, SIGHUP];

    /**
     * Any signals that were trapped during custom signal handler
     *
     * @var array<int>
     */
    protected array $trappedSignals = [];

    /**
     * Backup of signal handlers
     * Original signal handler, which replaced by custon trap
     *
     * @var array
     */
    protected array $trappedHandlers = [];

    /**
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     * @SuppressWarnings("camelCase")
     * @see https://www.php.net/manual/en/function.gc-collect-cycles
     */
    public function gc_collect_cycles(): void
    {
        if (!gc_enabled()) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        gc_collect_cycles();
    }

    /**
     * Restore previous PHP error handler
     *
     * @see https://www.php.net/manual/en/function.restore-error-handler.php
     * @SuppressWarnings("camelCase")
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function restore_error_handler(): void
    {
        restore_error_handler();
    }

    /**
     * Set custom PHP error handler
     *
     * @param callable|null $callback
     * @see https://www.php.net/manual/en/function.set-error-handler
     * @SuppressWarnings("camelCase")
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function set_error_handler(?callable $callback = null, int $error_levels = E_ALL|E_STRICT): ?callable
    {
        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        return set_error_handler($callback ?? $this->getNullErrorHandler(), $error_levels);
    }

    /**
     * Set mysqlnd.net_read_timeout php ini value
     *
     * mysqlnd and the MySQL Client Library, libmysqlclient use different networking APIs.
     * mysqlnd uses PHP streams, whereas libmysqlclient uses its own wrapper around the operating level network calls.
     * PHP, by default, sets a read timeout of 60s for streams.
     * This is set via php.ini, default_socket_timeout.
     * This default applies to all streams that set no other timeout value.
     * mysqlnd does not set any other value and therefore connections of long running queries can be disconnected
     * after default_socket_timeout seconds resulting in an error message 2006 - MySQL Server has gone away.
     * The MySQL Client Library sets a default timeout of 24 * 3600 seconds (1 day)
     * and waits for other timeouts to occur, such as TCP/IP timeouts. mysqlnd now uses the same very long timeout.
     * The value is configurable through a new php.ini setting: mysqlnd.net_read_timeout.
     *
     * mysqlnd.net_read_timeout gets used by any extension (ext/mysql, ext/mysqli, PDO_MySQL) that uses mysqlnd.
     * mysqlnd tells PHP Streams to use mysqlnd.net_read_timeout.
     * Please note that there may be subtle differences between MYSQL_OPT_READ_TIMEOUT from the MySQL Client Library
     * and PHP Streams, for example MYSQL_OPT_READ_TIMEOUT is documented to work only for TCP/IP connections and,
     * prior to MySQL 5.1.2, only for Windows. PHP streams may not have this limitation.
     *
     * @throws EnvironmentException
     * @see https://www.php.net/manual/en/mysqlnd.config.php
     */
    public function setMysqlndNetReadTimeout(string $timeoutSeconds): bool
    {
        return (bool) $this->ini_set('mysqlnd.net_read_timeout', $timeoutSeconds);
    }

    /**
     * Sets which PHP errors are reported
     *
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     * @SuppressWarnings("camelCase")
     * @see https://www.php.net/manual/en/function.error-reporting
     */
    public function error_reporting(int $level): int
    {
        return error_reporting($level);
    }

    /**
     * Set whether a client disconnect should abort script execution (does not affect CLI)
     *
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     * @SuppressWarnings("camelCase")
     * @see https://www.php.net/manual/en/function.ignore-user-abort
     */
    public function ignore_user_abort(): int
    {
        return ignore_user_abort();
    }

    /**
     * Sets the value of a configuration option
     *
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     * @SuppressWarnings("camelCase")
     * @throws EnvironmentException
     * @see https://www.php.net/manual/en/function.ini-set
     */
    public function ini_set(string $key, string $value): string
    {
        $result = ini_set($key, $value);
        if (false === $result) {
            // @codeCoverageIgnoreStart
            throw new EnvironmentException();
            // @codeCoverageIgnoreEnd
        }

        return $result;
    }

    /**
     * Disable custom signal handler
     *
     * @see https://wiki.php.net/rfc/async_signals
     * @see https://blog.pascal-martin.fr/post/php71-en-other-new-things/
     * @see https://www.php.net/manual/en/function.pcntl-signal
     *
     * @return array|null array of trapped signals
     * @throws EnvironmentException
     */
    public function endSignalsTrap(): ?array
    {
        /**
         * Process signals
         */
        if (!pcntl_signal_dispatch()) {
            // @codeCoverageIgnoreStart
            throw new EnvironmentException();
            // @codeCoverageIgnoreEnd
        }

        $trappedSignals = $this->trappedSignals;
        foreach ($this->knownSignals as $signalNumber) {
            /**
             * Reset signals to previous/default handler
             * @psalm-suppress MixedArgument
             */
            if (!pcntl_signal($signalNumber, $this->trappedHandlers[$signalNumber] ?? SIG_DFL)) {
                // @codeCoverageIgnoreStart
                throw new EnvironmentException();
                // @codeCoverageIgnoreEnd
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
     * @see https://www.php.net/manual/en/function.pcntl-signal
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
                // @codeCoverageIgnoreStart
                throw new EnvironmentException();
                // @codeCoverageIgnoreEnd
            }
        }
    }

    /**
     * Error handler that does nothing and does not chain
     * @see https://www.php.net/manual/en/function.set-error-handler
     */
    protected function getNullErrorHandler(): callable
    {
        return static function (): bool {
            return true;
        };
    }
}
