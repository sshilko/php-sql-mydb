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

use sql\MydbException\OptionException;
use const E_ALL;
use const E_NOTICE;
use const E_WARNING;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbOptions
{
    protected const NET_CMD_BUFFER_SIZE_MIN = 4096;

    protected const NET_CMD_BUFFER_SIZE_MAX = 16384;

    protected const NET_READ_BUFFER_MIN = 8192;

    protected const NET_READ_BUFFER_MAX = 131072;

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
     * Scope: connection.
     *
     * Number of network command buffer extensions while sending commands from PHP to MySQL.
     *
     * mysqlnd allocates an internal command/network buffer of mysqlnd.net_cmd_buffer_size (php.ini) bytes
     * for every connection.
     * If a MySQL Client Server protocol command, for example, COM_QUERY ("normal&quot query),
     * does not fit into the buffer, mysqlnd will grow the buffer to what is needed for sending the command.
     * Whenever the buffer gets extended for one connection command_buffer_too_small will be incremented by one.
     *
     * If mysqlnd has to grow the buffer beyond its initial size of mysqlnd.net_cmd_buffer_size (php.ini) bytes
     * for almost every connection, you should consider to increase the default size to avoid re-allocations.
     *
     * The default can set either through the php.ini setting mysqlnd.net_cmd_buffer_size
     * or using mysqli_options(MYSQLI_OPT_NET_CMD_BUFFER_SIZE, int size).
     *
     * It is recommended to set the buffer size to no less than 4096 bytes because mysqlnd also uses
     * it when reading certain communication packet from MySQL.
     *
     * As of PHP 5.3.2 mysqlnd does not allow setting buffers smaller than 4096 bytes.
     *
     * Default 4096
     *
     * More memory usage, in exchange for better performance
     * @see mysqlnd.net_cmd_buffer_size
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
     *
     * This buffer controls how many bytes mysqlnd fetches from the PHP streams with one call.
     * If a result set has less than 32kB in size, mysqlnd will call the PHP streams network
     * functions only once, if it is larger more calls are needed
     *
     * Default 32768
     *
     * @see mysqlnd.net_read_buffer_size
     * @see http://php.net/manual/en/mysqlnd.config.php
     * @see http://blog.ulf-wendel.de/2007/php-mysqlnd-saves-40-memory-finally-new-tuning-options/
     */
    protected int $networkReadBuffer = 49152;

    /**
     * Sets mysqli error reporting mode
     *
     * >=8.1.0 The default value is now MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT.
     * < 8.1.0 MYSQLI_REPORT_OFF.
     *
     * MYSQLI_REPORT_OFF Turns reporting off
     * MYSQLI_REPORT_ERROR Report errors from mysqli function calls
     * MYSQLI_REPORT_STRICT Throw mysqli_sql_exception for errors instead of warnings
     * MYSQLI_REPORT_INDEX Report if no index or bad index was used in a query
     * MYSQLI_REPORT_ALL Set all options (report all)
     *
     * @see https://www.php.net/manual/en/function.mysqli-report.php
     */
    protected int $clientErrorLevel = MydbMysqli::MYSQLI_REPORT_ALL ^
                                      MydbMysqli::MYSQLI_REPORT_STRICT ^
                                      MydbMysqli::MYSQLI_REPORT_INDEX;

    /**
     * Set session time zone
     *
     * SET time_zone = timezone;
     *
     * - As the value 'SYSTEM', indicating that the server time zone is the same as the system time zone.
     * - As a string, an offset from UTC of the form [H]H:MM, prefixed with a + or -, such as '+10:00', '-6:00'
     *   Prior to MySQL 8.0.19, this value had to be in the range '-12:59' to '+13:00'
     * - As a named time zone, such as 'Europe/Helsinki', 'US/Eastern', 'MET', or 'UTC'.
     *
     * @see https://dev.mysql.com/doc/refman/8.0/en/time-zone-support.html
     */
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

    public function setReadTimeout(int $seconds): void
    {
        $this->readTimeout = $seconds;
    }

    public function getNetworkBufferSize(): int
    {
        return $this->networkBufferSize;
    }

    /**
     * @param int $bytes bytes
     * @throws OptionException
     */
    public function setNetworkBufferSize(int $bytes): void
    {
        if ($bytes < self::NET_CMD_BUFFER_SIZE_MIN || $bytes > self::NET_CMD_BUFFER_SIZE_MAX) {
            throw new OptionException();
        }
        $this->networkBufferSize = $bytes;
    }

    public function getNetworkReadBuffer(): int
    {
        return $this->networkReadBuffer;
    }

    /**
     * @throws OptionException
     */
    public function setNetworkReadBuffer(int $bytes): void
    {
        if ($bytes < self::NET_READ_BUFFER_MIN || $bytes > self::NET_READ_BUFFER_MAX) {
            throw new OptionException();
        }
        $this->networkReadBuffer = $bytes;
    }

    public function getClientErrorLevel(): int
    {
        return $this->clientErrorLevel;
    }

    /**
     * @throws OptionException
     */
    public function setClientErrorLevel(int $mysqliReport): void
    {
        if ($mysqliReport > 255 || $mysqliReport < 0) {
            throw new OptionException();
        }
        $this->clientErrorLevel = $mysqliReport;
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
