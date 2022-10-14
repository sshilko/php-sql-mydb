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

use Psr\Log\LoggerInterface;
use function ini_set;
use const E_ALL;
use const E_WARNING;
use const MYSQLI_REPORT_ALL;
use const MYSQLI_REPORT_INDEX;
use const MYSQLI_REPORT_STRICT;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbOptions
{
    protected const PHP_INI_MYSQL_TIMEOUT = 'mysqlnd.net_read_timeout';

    /**
     * MAX_EXECUTION_TIME
     * Milliseconds
     *
     * The execution timeout ONLY APPLIES TO "SELECT" statements
     * X > 0, enabled
     * X = 0, not enabled.
     *
     * @see https://dev.mysql.com/doc/refman/5.7/en/server-system-variables.html#sysvar_max_execution_time
     */
    protected int $maxExecutionTime = 890000;

    protected int $retryTimes = 2;
    protected int $retryWait = 250000;

    protected int $timeoutConnectSeconds = 3;

    protected int $errorReporting = E_ALL & ~E_WARNING & ~E_NOTICE;

    /**
     * The timeout in seconds for each attempt to read from the server.
     * There are retries if necessary.
     *
     * @see https://dev.mysql.com/doc/c-api/8.0/en/mysql-options.html
     *
     * @see https://github.com/php/php-src/blob/12ab4cbd00e0dae52a5db98dda6da885acb408f6/
     *      ext/mysqli/mysqli.c#L654
     * @see https://github.com/php/php-src/blob/a03c1ed7aa2325d91595dcf9371297ab45543517/
     *      ext/mysqli/tests/mysqli_constants.phpt#L24
     */
    protected int $timeoutReadSeconds = 89;

    /**
     * Internal network buffer of mysqlnd.net_cmd_buffer_size bytes for every connection
     *
     * More memory usage, in exchange for better performance
     *
     * @see http://php.net/manual/en/mysqlnd.config.php
     */
    protected int $internalCmdBufferSuze = 6144;

    /**
     * More memory for better performance
     *
     * Maximum read chunk size in bytes when reading the body of a MySQL command packet
     * The MySQL client server protocol encapsulates all its commands in packets.
     * The packets consist of a small header and a body with the actual payload
     *
     * If a packet body is larger than mysqlnd.net_read_buffer_size bytes,
     * mysqlnd has to call read() multiple times
     */
    protected int $internalNetReadBuffer = 49152;

    protected int $internalClientErrorLevel = MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT ^ MYSQLI_REPORT_INDEX;

    protected string $timeZone = 'UTC';

//    /**
//     * Connection flags
//     * MYSQLI_CLIENT_COMPRESS
//     *
//     * @see https://www.php.net/manual/en/mysqli.real-connect
//     */
//    protected ?int $internalWireFlags = null;

    /**
     * The number of seconds the server waits for activity
     * on a non-interactive TCP/IP or UNIX File connection before closing it
     */
    protected int $internalNonInteractiveTimeout = 7200;

//    /**
//     * Number of seconds the server waits for activity
//     * on an interactive connection before closing it
//     */
//    protected int $internalInteractiveTimeout = 14400;

    /**
     * Prevent entry of invalid values such as those that are out of range, or NULL specified for NOT NULL columns
     * TRADITIONAL = strict mode
     *
     * @see https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sqlmode_traditional
     */
    protected string $internalClientSQLMode = 'TRADITIONAL';

    /**
     * Recommended defaults:
     * false for rw connection
     * true for ro connection
     * true for async connection
     */
    protected bool $autocommit = false;

    protected string $charset = 'utf8mb4';

    /**
     * Transaction block will also carry over to the next script
     * which uses that connection if script execution ends before the transaction block does
     *
     * @see http://php.net/manual/en/features.persistent-connections.php
     */
    protected bool $persistent = false;

    /**
     * Readonly connection
     */
    protected bool $readonly = false;

    protected LoggerInterface $logger;

    protected MydbMysqli $mydbMysqli;

    protected int $ignoreUserAbort;

    protected string $internalTransactionIsolationLevelReadonly = 'READ COMMITTED';

    public function __construct(?LoggerInterface $logger = null, ?MydbMysqli $mydbMysqli = null)
    {
        $this->ignoreUserAbort = ignore_user_abort();
        $this->logger = $logger ?? new MydbLogger();
        $this->mydbMysqli = $mydbMysqli ?? new MydbMysqli();
        $this->setNetReadTimeout();
    }

    public function getMydbMysqli(): MydbMysqli
    {
        return $this->mydbMysqli;
    }

    /**
     */
    public function getInternalNonInteractiveTimeout(): int
    {
        return $this->internalNonInteractiveTimeout;
    }

    public function setInternalNonInteractiveTimeout(int $internalNonInteractiveTimeout): void
    {
        $this->internalNonInteractiveTimeout = $internalNonInteractiveTimeout;
        $this->setNetReadTimeout();
    }

    public function getInternalTransactionIsolationLevelReadonly(): string
    {
        return $this->internalTransactionIsolationLevelReadonly;
    }

    public function setInternalTransactionIsolationLevelReadonly(string $level): void
    {
        $this->internalTransactionIsolationLevelReadonly = $level;
    }

    public function getIgnoreUserAbort(): int
    {
        return $this->ignoreUserAbort;
    }

    public function setIgnoreUserAbort(int $ignoreUserAbort): void
    {
        $this->ignoreUserAbort = $ignoreUserAbort;
    }

    public function getMaxExecutionTime(): int
    {
        return $this->maxExecutionTime;
    }

    public function setMaxExecutionTime(int $maxExecutionTime): void
    {
        $this->maxExecutionTime = $maxExecutionTime;
        $this->setNetReadTimeout();
    }

    public function getRetryTimes(): int
    {
        return $this->retryTimes;
    }

    public function setRetryTimes(int $retryTimes): void
    {
        $this->retryTimes = $retryTimes;
    }

    public function getRetryWait(): int
    {
        return $this->retryWait;
    }

    public function setRetryWait(int $retryWait): void
    {
        $this->retryWait = $retryWait;
    }

    public function getTimeoutConnectSeconds(): int
    {
        return $this->timeoutConnectSeconds;
    }

    public function setTimeoutConnectSeconds(int $timeoutConnectSeconds): void
    {
        $this->timeoutConnectSeconds = $timeoutConnectSeconds;
        $this->setNetReadTimeout();
    }

    public function getErrorReporting(): int
    {
        return $this->errorReporting;
    }

    public function setErrorReporting(int $errorReporting): void
    {
        $this->errorReporting = $errorReporting;
    }

    public function getTimeoutReadSeconds(): int
    {
        return $this->timeoutReadSeconds;
    }

    public function setTimeoutReadSeconds(int $timeoutReadSeconds): void
    {
        $this->timeoutReadSeconds = $timeoutReadSeconds;
        $this->setNetReadTimeout();
    }

    public function getInternalCmdBufferSuze(): int
    {
        return $this->internalCmdBufferSuze;
    }

    public function setInternalCmdBufferSuze(int $internalCmdBufferSuze): void
    {
        $this->internalCmdBufferSuze = $internalCmdBufferSuze;
    }

    public function getInternalNetReadBuffer(): int
    {
        return $this->internalNetReadBuffer;
    }

    public function setInternalNetReadBuffer(int $internalNetReadBuffer): void
    {
        $this->internalNetReadBuffer = $internalNetReadBuffer;
    }

    public function getInternalClientErrorLevel(): int
    {
        return $this->internalClientErrorLevel;
    }

    public function setInternalClientErrorLevel(int $internalClientErrorLevel): void
    {
        $this->internalClientErrorLevel = $internalClientErrorLevel;
    }

    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    public function setTimeZone(string $timeZone): void
    {
        $this->timeZone = $timeZone;
    }

    public function getInternalClientSQLMode(): string
    {
        return $this->internalClientSQLMode;
    }

    public function setInternalClientSQLMode(string $internalClientSQLMode): void
    {
        $this->internalClientSQLMode = $internalClientSQLMode;
    }

    public function isAutocommit(): bool
    {
        return $this->autocommit;
    }

    public function setAutocommit(bool $autocommit): void
    {
        $this->autocommit = $autocommit;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function setCharset(string $charset): void
    {
        $this->charset = $charset;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    public function setPersistent(bool $persistent): void
    {
        $this->persistent = $persistent;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function setReadonly(bool $readonly): void
    {
        $this->readonly = $readonly;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function phpIniSet(string $key, string $value): void
    {
        if (false === ini_set($key, $value)) {
            throw new MydbException('Failed to ini_set ' . $key);
        }
    }

    private function setNetReadTimeout(): void
    {
        $timeoutSeconds = (int) (max(
            round($this->maxExecutionTime / 1000),
            $this->getInternalNonInteractiveTimeout(),
            $this->getTimeoutReadSeconds()
        )) + $this->getTimeoutConnectSeconds();

        $this->phpIniSet(self::PHP_INI_MYSQL_TIMEOUT, (string) $timeoutSeconds);
    }
}
