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
 *
 * Polyfill for the pcntl extension when it is not available (Windows).
 */

declare(strict_types = 1);

// @codeCoverageIgnoreStart
if (!defined('SIG_DFL')) {
    define('SIG_DFL', 0);
}
if (!defined('SIG_IGN')) {
    define('SIG_IGN', 1);
}
if (!defined('SIG_ERR')) {
    define('SIG_ERR', -1);
}
if (!defined('SIGHUP')) {
    define('SIGHUP', 1);
}
if (!defined('SIGINT')) {
    define('SIGINT', 2);
}
if (!defined('SIGQUIT')) {
    define('SIGQUIT', 3);
}
if (!defined('SIGILL')) {
    define('SIGILL', 4);
}
if (!defined('SIGTRAP')) {
    define('SIGTRAP', 5);
}
if (!defined('SIGABRT')) {
    define('SIGABRT', 6);
}
if (!defined('SIGBUS')) {
    define('SIGBUS', 7);
}
if (!defined('SIGFPE')) {
    define('SIGFPE', 8);
}
if (!defined('SIGKILL')) {
    define('SIGKILL', 9);
}
if (!defined('SIGUSR1')) {
    define('SIGUSR1', 10);
}
if (!defined('SIGSEGV')) {
    define('SIGSEGV', 11);
}
if (!defined('SIGUSR2')) {
    define('SIGUSR2', 12);
}
if (!defined('SIGPIPE')) {
    define('SIGPIPE', 13);
}
if (!defined('SIGALRM')) {
    define('SIGALRM', 14);
}
if (!defined('SIGTERM')) {
    define('SIGTERM', 15);
}
if (!defined('SIGSTKFLT')) {
    define('SIGSTKFLT', 16);
}
if (!defined('SIGCHLD')) {
    define('SIGCHLD', 17);
}
if (!defined('SIGCONT')) {
    define('SIGCONT', 18);
}
if (!defined('SIGSTOP')) {
    define('SIGSTOP', 19);
}
if (!defined('SIGTSTP')) {
    define('SIGTSTP', 20);
}
if (!defined('SIGTTIN')) {
    define('SIGTTIN', 21);
}
if (!defined('SIGTTOU')) {
    define('SIGTTOU', 22);
}
if (!defined('SIGURG')) {
    define('SIGURG', 23);
}
if (!defined('SIGXCPU')) {
    define('SIGXCPU', 24);
}
if (!defined('SIGXFSZ')) {
    define('SIGXFSZ', 25);
}
if (!defined('SIGVTALRM')) {
    define('SIGVTALRM', 26);
}
if (!defined('SIGPROF')) {
    define('SIGPROF', 27);
}
if (!defined('SIGWINCH')) {
    define('SIGWINCH', 28);
}
if (!defined('SIGIO')) {
    define('SIGIO', 29);
}
if (!defined('SIGPWR')) {
    define('SIGPWR', 30);
}
if (!defined('SIGSYS')) {
    define('SIGSYS', 31);
}
if (!defined('SIGRTMIN')) {
    define('SIGRTMIN', 34);
}
if (!defined('SIGRTMAX')) {
    define('SIGRTMAX', 64);
}

// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
if (!function_exists('pcntl_signal')) {
    /**
     * Shared registry of simulated signal handlers shared by the pcntl_signal()
     * and posix_kill() polyfills, keyed by signal number.
     *
     * @var array<int, int|string|callable> $pcntlPolyfillSignalHandlers
     */
    $pcntlPolyfillSignalHandlers = [];
    // phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
    $GLOBALS['pcntl_polyfill_signal_handlers'] = $pcntlPolyfillSignalHandlers;
    // phpcs:enable PSR1.Files.SideEffects.FoundWithSymbols

    /**
     * Register or reset a simulated signal handler that posix_kill() will invoke.
     *
     * @param int|string|callable $handler
     * @see https://www.php.net/manual/en/function.pcntl-signal.php
     */
    function pcntl_signal(int $signal, int|string|callable $handler, bool $restartSysCalls = true): bool
    {
        unset($restartSysCalls);
        /** @var array<int, int|string|callable> $handlers */
        $handlers = $GLOBALS['pcntl_polyfill_signal_handlers'];
        if (SIG_DFL === $handler || SIG_IGN === $handler) {
            unset($handlers[$signal]);
        } else {
            $handlers[$signal] = $handler;
        }

        $GLOBALS['pcntl_polyfill_signal_handlers'] = $handlers;

        return true;
    }
}

if (!function_exists('posix_kill')) {
    /**
     * Deliver a signal to the current process by invoking the simulated handler
     * registered through the pcntl_signal() polyfill.
     *
     * @see https://www.php.net/manual/en/function.posix-kill.php
     */
    function posix_kill(int $processId, int $signal): bool
    {
        unset($processId);
        /** @var array<int, int|string|callable> $handlers */
        $handlers = $GLOBALS['pcntl_polyfill_signal_handlers'];
        if (isset($handlers[$signal]) && is_callable($handlers[$signal])) {
            call_user_func($handlers[$signal], $signal);
        }

        return true;
    }
}
// phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable

if (!function_exists('pcntl_signal_dispatch')) {
    /**
     * Report that pending signals were dispatched, there are none on Windows.
     *
     * @see https://www.php.net/manual/en/function.pcntl-signal-dispatch.php
     */
    function pcntl_signal_dispatch(): bool
    {
        return true;
    }
}

if (!function_exists('pcntl_signal_get_handler')) {
    /**
     * Report the default signal handler, no handlers are ever installed.
     *
     * @psalm-suppress LessSpecificReturnType the polyfill always returns the
     *     default handler while the real function may return a string handler
     * @see https://www.php.net/manual/en/function.pcntl-signal-get-handler.php
     */
    function pcntl_signal_get_handler(int $signal): int|string|false
    {
        unset($signal);

        return SIG_DFL;
    }
}
// @codeCoverageIgnoreEnd
