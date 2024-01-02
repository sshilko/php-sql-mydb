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

use mysqli;
use mysqli_result;
use sql\MydbMysqli\MydbMysqliResult;
use function array_merge;
use function array_values;
use function in_array;
use function max;
use function mysqli_report;
use function sprintf;
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
use const MYSQLI_TRANS_START_READ_WRITE;

/**
 * Facade for php mysqli extension
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 * @see https://www.php.net/manual/en/class.mysqli
 */
class MydbMysqli implements MydbMysqliInterface
{
    /**
     * Command to execute when connecting to MySQL server. Will automatically be re-executed when reconnecting.
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_INIT_COMMAND = MYSQLI_INIT_COMMAND;

    /**
     * Connect timeout in seconds
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_OPT_CONNECT_TIMEOUT = MYSQLI_OPT_CONNECT_TIMEOUT;

    /**
     * The size of the internal command/network buffer. Only valid for mysqlnd.
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_OPT_NET_CMD_BUFFER_SIZE = MYSQLI_OPT_NET_CMD_BUFFER_SIZE;

    /**
     * Maximum read chunk size in bytes when reading the body of a MySQL command packet. Only valid for mysqlnd.
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_OPT_NET_READ_BUFFER_SIZE = MYSQLI_OPT_NET_READ_BUFFER_SIZE;

    /**
     * Command execution result timeout in seconds. Available as of PHP 7.2.0.
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_OPT_READ_TIMEOUT = MYSQLI_OPT_READ_TIMEOUT;

    /**
     * Copy results from the internal mysqlnd buffer into the PHP variables fetched.
     * By default, mysqlnd will use a reference logic to avoid copying and duplicating results
     * held in memory. For certain result sets, for example, result sets with many small rows,
     * the copy approach can reduce the overall memory usage because PHP variables holding
     * results may be released earlier (available with mysqlnd only)
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_STORE_RESULT_COPY_DATA = MYSQLI_STORE_RESULT_COPY_DATA;

    /**
     * Appends "RELEASE" to mysqli_commit() or mysqli_rollback().
     * The RELEASE clause causes the server to disconnect the current client session
     * after terminating the current transaction
     *
     * @see https://dev.mysql.com/doc/refman/8.0/en/commit.html
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_TRANS_COR_RELEASE = MYSQLI_TRANS_COR_RELEASE;

    /**
     * Start the transaction as "START TRANSACTION READ ONLY" with mysqli_begin_transaction().
     *
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_TRANS_START_READ_ONLY = MYSQLI_TRANS_START_READ_ONLY;

    /**
     * Start the transaction as "START TRANSACTION READ WRITE" with mysqli_begin_transaction().
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_TRANS_START_READ_WRITE = MYSQLI_TRANS_START_READ_WRITE;

    /**
     * Appends "NO RELEASE" to mysqli_commit() or mysqli_rollback().
     * The NO RELEASE clause asks the server to not disconnect the current client session
     * after terminating the current transaction
     *
     * @see https://dev.mysql.com/doc/refman/8.0/en/commit.html
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_TRANS_COR_NO_RELEASE = MYSQLI_TRANS_COR_NO_RELEASE;

    /**
     * Set all options on (report all), report all warnings/errors.
     *
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_REPORT_ALL = MYSQLI_REPORT_ALL;

    /**
     * Report if no index or bad index was used in a query.
     *
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_REPORT_INDEX = MYSQLI_REPORT_INDEX;

    /**
     * Throw a mysqli_sql_exception for errors instead of warnings.
     *
     * @see https://www.php.net/manual/en/mysqli.constants.php
     */
    public const MYSQLI_REPORT_STRICT = MYSQLI_REPORT_STRICT;

    /**
     * Safe MySQL SQL_MODE
     * @see https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sqlmode_traditional
     */
    protected const SQL_MODE = 'TRADITIONAL';

    /**
     * Mysqli instance
     * The mysqli extension allows you to access the functionality provided by MySQL 4.1 and above
     *
     * @see http://dev.mysql.com/doc/
     * @see https://www.php.net/manual/en/mysqli.overview.php
     * @see https://www.php.net/manual/en/intro.mysqli.php
     * @see https://www.php.net/manual/en/class.mysqli
     */
    protected ?mysqli $mysqli = null;

    /**
     * Flag indicating whether physical connection was established with remote server
     * Is connected to remove server
     */
    protected bool $isConnected = false;

    /**
     * Flag indicating whether SQL transaction was started
     * WARNING: best-effort, only guaranteed when library is used correctly
     * Is transaction started
     */
    protected bool $isTransaction = false;

    public function __construct(?mysqli $resource = null)
    {
        if (!$resource) {
            return;
        }

        $this->mysqli = $resource;
    }

    /**
     * Allocate mysqli resource instance, no physical connection to remote is done
     *
     */
    public function init(): bool
    {
        if (null !== $this->mysqli) {
            /**
             * Prevent zombie connections
             */
            return false;
        }

        /**
         * @see https://php.net/manual/en/mysqli.construct.php
         * @see https://wiki.php.net/rfc/improve_mysqli
         */
        $init = new mysqli();
        $this->mysqli = $init;

        return true;
    }

    /**
     * Set various options that affect mysqli resource, before connection is established
     *
     * @see https://www.php.net/manual/en/mysqli.options.php
     * @throws \sql\MydbException\EnvironmentException
     */
    public function setTransportOptions(MydbOptionsInterface $options, MydbEnvironmentInterface $environment): bool
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

    public function setTransactionIsolationLevel(string $level): bool
    {
        /**
         * SESSION is explicitly required,
         * otherwise 'The statement applies only to the next single transaction performed within the session'
         *
         * @see https://dev.mysql.com/doc/refman/8.0/en/set-transaction.html
         */
        return $this->realQuery(sprintf('SET SESSION TRANSACTION ISOLATION LEVEL %s', $level));
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

    /**
     * @see https://www.php.net/manual/en/mysqli.real-query.php
     */
    public function realQuery(string $query): bool
    {
        if ($this->mysqli && $this->isConnected()) {
            return $this->mysqli->real_query($query);
        }

        return false;
    }

    /**
     * React to mysqli resource changes after query/command execution
     */
    public function readServerResponse(MydbEnvironmentInterface $environment): ?MydbMysqliResult
    {
        if ($this->mysqli && $this->isConnected()) {
            $events = [];

            $warnings = [];
            
            $result = $this->extractServerResponse($environment, $events);

            $fieldsCount = $this->getFieldCount();

            if ($this->getWarningCount() > 0) {
                $warnings = array_merge($warnings, $this->getWarnings());
            }
            if ($events) {
                $warnings = array_merge($warnings, array_values($events));
            }

            /** @var array<array-key, string> $warnings */
            $response = new MydbMysqliResult($result, $warnings, $fieldsCount ?? 0);

            $error = $this->getError();
            if (null !== $error && '' !== $error) {
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

    /**
     * @see https://www.php.net/manual/en/mysqli.real-escape-string.php
     */
    public function realEscapeString(string $string): ?string
    {
        if (!$this->mysqli || !$this->isConnected()) {
            return null;
        }

        return $this->mysqli->real_escape_string($string);
    }

    /**
     * @see https://www.php.net/manual/en/mysqli.begin-transaction.php
     */
    public function beginTransactionReadwrite(): bool
    {
        if ($this->mysqli &&
            $this->isConnected() &&
            $this->mysqli->begin_transaction(self::MYSQLI_TRANS_START_READ_WRITE)) {
            $this->isTransaction = true;

            return true;
        }

        return false;
    }

    /**
     * @see https://www.php.net/manual/en/mysqli.begin-transaction.php
     */
    public function beginTransactionReadonly(): bool
    {
        if ($this->mysqli &&
            $this->isConnected() &&
            $this->mysqli->begin_transaction(self::MYSQLI_TRANS_START_READ_ONLY)) {
            $this->isTransaction = true;

            return true;
        }

        return false;
    }

    /**
     * @see https://www.php.net/manual/en/mysqli.rollback.php
     */
    public function rollback(): bool
    {
        /**
         * ignore isTransaction state, do not rely on it, instead do what user requested
         */
        if ($this->mysqli && $this->isConnected() && $this->mysqli->rollback(self::MYSQLI_TRANS_COR_NO_RELEASE)) {
            $this->isTransaction = false;

            return true;
        }

        return false;
    }

    /**
     * Commit transaction and release connection from server side
     */
    public function commitAndRelease(): bool
    {
        if ($this->mysqli && $this->isConnected() && $this->mysqli->commit(self::MYSQLI_TRANS_COR_RELEASE)) {
            $this->isTransaction = false;

            return true;
        }

        return false;
    }

    public function commit(): bool
    {
        if ($this->mysqli && $this->isConnected() && $this->mysqli->commit(self::MYSQLI_TRANS_COR_NO_RELEASE)) {
            $this->isTransaction = false;

            return true;
        }

        return false;
    }

    public function realConnect(
        string $host,
        string $username,
        string $password,
        string $dbname,
        ?int $port,
        ?string $socket,
        int $flags,
    ): bool {
        if ($this->mysqli && !$this->isConnected() && $this->mysqli->real_connect(
            $host,
            $username,
            $password,
            $dbname,
            (int) $port,
            (string) $socket,
            $flags
        )) {
            $this->isConnected = true;

            return true;
        }

        return false;
    }

    public function mysqliReport(int $level): bool
    {
        return mysqli_report($level);
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
            ? (int) $this->mysqli->affected_rows
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
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function getInsertId(): int|string|null
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
     * @phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedPassingByReference
     * @param array<int, string> $events
     */
    public function extractServerResponse(MydbEnvironmentInterface $environment, array &$events): ?mysqli_result
    {
        if (null === $this->mysqli) {
            return null;
        }

        /**
         * @psalm-suppress UnusedClosureParam
         */
        $environment->set_error_handler(static function (int $errno, string $error) use (&$events) {
            $events[$errno] = $error;

            return true;
        });

        $result = $this->mysqli->store_result(self::MYSQLI_STORE_RESULT_COPY_DATA);
        $environment->restore_error_handler();

        if (false === $result) {
            return null;
        }

        return $result;
    }

    public function getWarnings(): array
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

    /**
     * Returns fields count caused by query execution
     * Requires store_result to be called first
     * @see mysqli::store_result()
     */
    protected function getFieldCount(): ?int
    {
        return $this->mysqli ? $this->mysqli->field_count : null;
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
}
