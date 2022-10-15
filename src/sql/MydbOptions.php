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

use const E_ALL;
use const E_NOTICE;
use const E_WARNING;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbOptions
{
    /**
     * The execution timeout ONLY APPLIES TO "SELECT" statements, seconds
     * X > 0, enabled
     * X = 0, not enabled.
     *
     * @see https://dev.mysql.com/doc/refman/5.7/en/server-system-variables.html#sysvar_max_execution_time
     */
    protected int $serverSideSelectTimeout = 89;

    /**
     * MySql client connection timeout, seconds
     */
    protected int $connectTimeout = 5;

    protected int $errorReporting = E_ALL & ~E_WARNING & ~E_NOTICE;

    /**
     * The timeout in seconds for each attempt to read from the server.
     * @see https://dev.mysql.com/doc/c-api/8.0/en/mysql-options.html
     * @see https://github.com/php/php-src/blob/12ab4cbd00e0dae52a5db98dda6da885acb408f6/
     *      ext/mysqli/mysqli.c#L654
     * @see https://github.com/php/php-src/blob/a03c1ed7aa2325d91595dcf9371297ab45543517/
     *      ext/mysqli/tests/mysqli_constants.phpt#L24
     */
    protected int $readTimeout = 90;

    /**
     * Internal network buffer of mysqlnd.net_cmd_buffer_size bytes for every connection
     *
     * More memory usage, in exchange for better performance
     *
     * @see http://php.net/manual/en/mysqlnd.config.php
     */
    protected int $networkBufferSize = 6144;

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
    protected int $networkReadBuffer = 49152;

    protected int $internalClientErrorLevel = MydbMysqli::MYSQLI_REPORT_ALL ^
                                              MydbMysqli::MYSQLI_REPORT_STRICT ^
                                              MydbMysqli::MYSQLI_REPORT_INDEX;

    protected string $timeZone = 'UTC';

    /**
     * The number of seconds the server waits for activity
     * on a non-interactive TCP/IP or UNIX File connection before closing it
     */
    protected int $nonInteractiveTimeout = 7200;

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

    public function getNonInteractiveTimeout(): int
    {
        return $this->nonInteractiveTimeout;
    }

    public function setNonInteractiveTimeout(int $nonInteractiveTimeout): void
    {
        $this->nonInteractiveTimeout = $nonInteractiveTimeout;
    }

    public function getServerSideSelectTimeout(): int
    {
        return $this->serverSideSelectTimeout;
    }

    public function setServerSideSelectTimeout(int $seconds): void
    {
        $this->serverSideSelectTimeout = $seconds;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function setConnectTimeout(int $seconds): void
    {
        $this->connectTimeout = $seconds;
    }

    public function getErrorReporting(): int
    {
        return $this->errorReporting;
    }

    public function setErrorReporting(int $errorReporting): void
    {
        $this->errorReporting = $errorReporting;
    }

    public function getReadTimeout(): int
    {
        return $this->readTimeout;
    }

    public function setReadTimeout(int $readTimeout): void
    {
        $this->readTimeout = $readTimeout;
    }

    public function getNetworkBufferSize(): int
    {
        return $this->networkBufferSize;
    }

    public function setNetworkBufferSize(int $networkBufferSize): void
    {
        $this->networkBufferSize = $networkBufferSize;
    }

    public function getNetworkReadBuffer(): int
    {
        return $this->networkReadBuffer;
    }

    public function setNetworkReadBuffer(int $networkReadBuffer): void
    {
        $this->networkReadBuffer = $networkReadBuffer;
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
}
