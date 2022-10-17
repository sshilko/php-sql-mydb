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

use mysqli;
use function array_merge;
use function array_values;
use function in_array;
use function max;
use function mysqli_init;
use function mysqli_query;
use function mysqli_report;
use function sprintf;
use const MYSQLI_ASYNC;
use const MYSQLI_INIT_COMMAND;
use const MYSQLI_OPT_CONNECT_TIMEOUT;
use const MYSQLI_OPT_NET_CMD_BUFFER_SIZE;
use const MYSQLI_OPT_NET_READ_BUFFER_SIZE;
use const MYSQLI_OPT_READ_TIMEOUT;
use const MYSQLI_REPORT_ALL;
use const MYSQLI_REPORT_INDEX;
use const MYSQLI_REPORT_STRICT;
use const MYSQLI_STORE_RESULT_COPY_DATA;
use const MYSQLI_TRANS_COR_NO_RELEASE;
use const MYSQLI_TRANS_COR_RELEASE;
use const MYSQLI_TRANS_START_READ_ONLY;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbMysqli
{
    public const MYSQLI_INIT_COMMAND = MYSQLI_INIT_COMMAND;
    public const MYSQLI_OPT_CONNECT_TIMEOUT = MYSQLI_OPT_CONNECT_TIMEOUT;
    public const MYSQLI_OPT_NET_CMD_BUFFER_SIZE = MYSQLI_OPT_NET_CMD_BUFFER_SIZE;
    public const MYSQLI_OPT_NET_READ_BUFFER_SIZE = MYSQLI_OPT_NET_READ_BUFFER_SIZE;
    public const MYSQLI_OPT_READ_TIMEOUT = MYSQLI_OPT_READ_TIMEOUT;
    public const MYSQLI_STORE_RESULT_COPY_DATA = MYSQLI_STORE_RESULT_COPY_DATA;
    public const MYSQLI_TRANS_COR_RELEASE = MYSQLI_TRANS_COR_RELEASE;
    public const MYSQLI_TRANS_START_READ_ONLY = MYSQLI_TRANS_START_READ_ONLY;
    public const MYSQLI_TRANS_COR_NO_RELEASE = MYSQLI_TRANS_COR_NO_RELEASE;
    public const MYSQLI_REPORT_ALL = MYSQLI_REPORT_ALL;
    public const MYSQLI_REPORT_INDEX = MYSQLI_REPORT_INDEX;
    public const MYSQLI_REPORT_STRICT = MYSQLI_REPORT_STRICT;

    protected const SQL_MODE = 'TRADITIONAL';

    protected ?mysqli $mysqli = null;
    protected bool $isConnected = false;
    protected bool $isTransaction = false;

    public function __construct(?mysqli $resource = null)
    {
        if (!$resource) {
            return;
        }

        $this->mysqli = $resource;
    }

    public function init(): bool
    {
        if (null !== $this->mysqli) {
            /**
             * Prevent zombie connections
             */
            return false;
        }

        $init = mysqli_init();
        if ($init) {
            $this->mysqli = $init;

            return true;
        }

        $this->mysqli = null;

        return false;
    }

    public function setTransportOptions(MydbOptions $options, MydbEnvironment $environment): bool
    {
        if (null === $this->mysqli) {
            return false;
        }

        $ignoreUserAbort = $environment->ignore_user_abort();
        $selectTimeout = $options->getServerSideSelectTimeout();

        /**
         * Prevent entry of invalid values such as those that are out of range, or NULL specified for NOT NULL columns
         * TRADITIONAL = strict mode
         *
         * @see https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sqlmode_traditional
         */
        $mysqliInit = sprintf('SET SESSION sql_mode = %s', self::SQL_MODE);
        if ($ignoreUserAbort < 1) {
            $mysqliInit .= sprintf(', SESSION max_execution_time = %s', $selectTimeout * 10000);
        }

        $connectTimeout = $options->getConnectTimeout();
        $readTimeout = $options->getReadTimeout();
        $netReadTimeout = (string) (max($selectTimeout, $readTimeout) + $connectTimeout);

        return
            $environment->setMysqlndNetReadTimeout($netReadTimeout) &&
            $this->mysqli->options(self::MYSQLI_INIT_COMMAND, $mysqliInit) &&
            $this->mysqli->options(self::MYSQLI_OPT_CONNECT_TIMEOUT, $connectTimeout) &&
            $this->mysqli->options(self::MYSQLI_OPT_READ_TIMEOUT, $readTimeout) &&
            $this->mysqli->options(self::MYSQLI_OPT_NET_CMD_BUFFER_SIZE, $options->getNetworkBufferSize()) &&
            $this->mysqli->options(self::MYSQLI_OPT_NET_READ_BUFFER_SIZE, $options->getNetworkReadBuffer());
    }

    public function isTransactionOpen(): bool
    {
        /**
         * Ignore autocommit setting here
         */
        return $this->isTransaction;
    }

    public function isConnected(): bool
    {
        return $this->mysqli && $this->isConnected;
    }

    public function getMysqli(): ?mysqli
    {
        return $this->mysqli;
    }

    public function realQuery(string $query): bool
    {
        if ($this->mysqli && $this->isConnected()) {
            return $this->mysqli->real_query($query);
        }

        return false;
    }

    public function readServerResponse(MydbEnvironment $environment): ?MydbMysqliResult
    {
        if ($this->mysqli && $this->isConnected()) {
            $events = [];
            $oldHandler = $environment->set_error_handler(static function (int $errno, string $error) use (&$events) {
                $events[$errno] = $error;

                return true;
            });
            $result = $this->mysqli->store_result(self::MYSQLI_STORE_RESULT_COPY_DATA);
            $environment->set_error_handler($oldHandler);

            $fieldsCount = $this->getFieldCount();

            $warnings = [];
            if ($this->getWarningCount() > 0) {
                $warnings = array_merge($warnings, $this->getWarnings());
            }
            if ($events) {
                $warnings = array_merge($warnings, array_values($events));
            }

            $response = new MydbMysqliResult(false === $result ? null : $result, $warnings, $fieldsCount ?? 0);

            $error = $this->getError();
            if ($error) {
                $response->setErrorMessage($error);
            }

            $errno = $this->getErrNo();
            if ($errno > 0) {
                $response->setErrorNumber($errno);
            }

            return $response;
        }

        return null;
    }

    public function realEscapeString(string $string): ?string
    {
        if (!$this->mysqli || !$this->isConnected()) {
            return null;
        }

        return $this->mysqli->real_escape_string($string);
    }

    /**
     * @psalm-suppress MissingParamType
     */
    public function beginTransaction(...$args): bool
    {
        if ($this->mysqli && $this->isConnected() && $this->mysqli->begin_transaction(...$args)) {
            $this->isTransaction = true;

            return true;
        }

        return false;
    }

    /**
     * @psalm-suppress MissingParamType
     */
    public function rollback(...$args): bool
    {
        if ($this->mysqli && $this->isConnected() && $this->mysqli->rollback(...$args)) {
            $this->isTransaction = false;

            return true;
        }

        return false;
    }

    /**
     * @psalm-suppress MissingParamType
     */
    public function commit(...$args): bool
    {
        if ($this->mysqli && $this->isConnected() && $this->mysqli->commit(...$args)) {
            $this->isTransaction = false;

            return true;
        }

        return false;
    }

    /**
     * @psalm-suppress MissingParamType
     */
    public function realConnect(...$args): bool
    {
        if ($this->mysqli && !$this->isConnected() && $this->mysqli->real_connect(...$args)) {
            $this->isConnected = true;

            return true;
        }

        return false;
    }

    public function mysqliReport(int $level): bool
    {
        return mysqli_report($level);
    }

    public function mysqliQueryAsync(string $command): void
    {
        $mysqli = $this->getMysqli();
        if (!$mysqli || !$this->isConnected()) {
            return;
        }

        mysqli_query($mysqli, $command, MYSQLI_ASYNC);
    }

    public function close(): bool
    {
        if ($this->mysqli) {
            if ($this->isConnected()) {
                /**
                 * Ignore close() success/failure
                 */
                $this->mysqli->close();
                $this->isConnected = false;
            }

            $this->mysqli = null;

            return true;
        }

        return false;
    }

    public function getConnectErrno(): ?int
    {
        return $this->mysqli
            ? $this->mysqli->connect_errno
            : null;
    }

    public function getConnectError(): ?string
    {
        return $this->mysqli
            ? $this->mysqli->connect_error
            : null;
    }

    public function getServerVersion(): ?int
    {
        return $this->mysqli
            ? $this->mysqli->server_version
            : null;
    }

    public function isServerGone(): bool
    {
        return in_array($this->getErrNo(), [2002, 2006], true);
    }

    public function getError(): ?string
    {
        return $this->mysqli
            ? $this->mysqli->error
            : null;
    }

    public function getErrNo(): ?int
    {
        return $this->mysqli
            ? $this->mysqli->errno
            : null;
    }

    public function getAffectedRows(): ?int
    {
        $rows = $this->mysqli
            ? $this->mysqli->affected_rows
            : null;
        if (0 === $rows || $rows > 0) {
            return $rows;
        }

        /**
         * mysqli_affected_rows
         * An integer greater than zero indicates the number of rows affected or retrieved.
         * Zero indicates that no records where updated for an UPDATE statement,
         * no rows matched the WHERE clause in the query or that no query has yet been executed.
         * -1 indicates that the query returned an error.
         */
        return null;
    }

    /**
     * @return int|string|null
     */
    public function getInsertId()
    {
        return $this->mysqli
            ? $this->mysqli->insert_id
            : null;
    }

    public function autocommit(bool $enable): bool
    {
        if ($this->mysqli && $this->mysqli->autocommit($enable)) {
            if ($enable) {
                /**
                 * Some statement implicitly commit transaction
                 * @see https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html
                 */
                $this->isTransaction = false;
            }

            return true;
        }

        return false;
    }

    /**
     * Returns fields count caused by query execution
     * Requires store_result to be called first
     * @see mysqli::store_result()
     */
    protected function getFieldCount(): ?int
    {
        return $this->mysqli
            ? $this->mysqli->field_count
            : null;
    }

    /**
     * Returns warnings caused by query execution
     * Requires store_result to be called first
     * @see mysqli::store_result()
     */
    protected function getWarningCount(): ?int
    {
        return $this->mysqli
            ? $this->mysqli->warning_count
            : null;
    }

    protected function getWarnings(): array
    {
        if ($this->mysqli) {
            $warnings = $this->mysqli->get_warnings();
            $array = [];
            do {
                $array[] = $warnings->message;
            } while ($warnings->next());

            return $array;
        }

        return [];
    }
}
