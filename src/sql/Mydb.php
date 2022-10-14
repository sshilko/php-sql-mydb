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

use Throwable;
use function array_map;
use function count;
use function ctype_digit;
use function ctype_xdigit;
use function error_reporting;
use function explode;
use function implode;
use function in_array;
use function intval;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace;
use function random_int;
use function sprintf;
use function strpos;
use function strtoupper;
use function substr;
use function usleep;

/**
 * Simple wrapper around PHP MySQL client
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class Mydb implements MydbInterface
{
    protected MydbMysqli $mysqliHandler;

    private string $hostname;

    private int $portNumber;

    private string $username;

    private string $password;

    private string $databaseName;

    private MydbOptions $options;

    public function __construct(string $host, int $port, string $user, string $pass, string $name, MydbOptions $options)
    {
        $this->hostname = $host;
        $this->portNumber = $port;
        $this->username = $user;
        $this->password = $pass;
        $this->databaseName = $name;

        $this->mysqliHandler = $options->getMydbMysqli();
        $this->options = $options;
    }

    /**
     * A destructor may be called as soon as there are no references to an object.
     *
     * @see http://php.net/manual/en/mysqli.close.php
     * @see http://php.net/manual/en/mysqli.ping.php (MysqlND not supports reconnect)
     */
    public function __destruct()
    {
        $this->close();
    }

    public function open(): void
    {
        $this->connect();
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint
     *
     * @throws MydbException
     * @throws MydbConnectionException
     *
     * @return array<array<(float|int|string|null)>>
     *
     * @psalm-return list<array<(float|int|string|null)>>
     */
    public function query(string $query): array
    {
        if (!$this->connect()) {
            throw new MydbConnectionException();
        }

        $result = false;

        $originalHandler = set_error_handler(static function () {
            return true;
        });
        if ($this->mysqliHandler->realQuery($query)) {
            $result = $this->mysqliHandler->storeResult(MydbMysqli::MYSQLI_STORE_RESULT_COPY_DATA);
            if (null === $result) {
                $this->onError('Failed to mysqli.store_result from mysqlnd to php userland', $query);
            }
        }
        set_error_handler($originalHandler);

        $sqlerror = $this->mysqliHandler->getError();
        if ($sqlerror) {
            $this->onError($sqlerror, $query);
        }

        $haswarnings = $this->mysqliHandler->getWarningCount();
        if ($haswarnings > 0) {
            $warnings = $this->mysqliHandler->getWarnings();
            if ($warnings) {
                do {
                    $this->onWarning($warnings->message, $query);
                } while ($warnings->next());
            }
        }

        if (!$result) {
            return [];
        }

        if (0 === $result->num_rows) {
            return [];
        }

        $resultArray = [];

        if ($this->mysqliHandler->getFieldCount()) {
            $resultArray = $result->fetch_all(MydbMysqli::MYSQLI_ASSOC);
            $result->free_result();
        }

        return $resultArray;
    }

    /**
     * With MYSQLI_ASYNC (available with mysqlnd), it is possible to perform query asynchronously.
     * mysqli_poll() is then used to get results from such queries.
     * @throws MydbException
     * @throws MydbConnectionException
     */
    public function async(string $command): void
    {
        if (false === $this->options->isAutocommit() ||
            $this->options->isPersistent() ||
            $this->options->isReadonly()) {
            throw new MydbException('Async is safe only with autocommit=true & non-persistent & rw configuration');
        }

        if (!$this->connect()) {
            throw new MydbConnectionException();
        }

        $this->mysqliHandler->mysqliQueryAsync($command);
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint
     *
     * @return array<array<(float|int|string|null)>>
     *
     * @psalm-return list<array<(float|int|string|null)>>
     */
    public function select(string $query): array
    {
        return $this->query($query);
    }

    public function delete(string $query): ?int
    {
        if ($this->command($query)) {
            return $this->mysqliHandler->getAffectedRows();
        }

        return null;
    }

    public function update(string $query): ?int
    {
        if ($this->command($query)) {
            return $this->mysqliHandler->getAffectedRows();
        }

        return null;
    }

    public function insert(string $query): ?string
    {
        if ($this->command($query)) {
            return (string) $this->mysqliHandler->getInsertId();
        }

        return null;
    }

    public function replace(string $query): ?string
    {
        if ($this->command($query)) {
            return (string)$this->mysqliHandler->getInsertId();
        }

        return null;
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive
     * @throws MydbException
     * @throws MydbConnectionException
     */
    public function command(string $query, ?int $retry = null): bool
    {
        if (!$this->connect()) {
            throw new MydbConnectionException();
        }

        $someresult = $this->mysqliHandler->realQuery($query);

        if (false === $someresult) {
            $mysqlError = $this->mysqliHandler->getError();

            if (null === $retry || $retry > 0) {
                if ($mysqlError) {
                    $this->onError($mysqlError, $query);
                }

                if (false !== strpos((string) $mysqlError, 'try restarting') ||
                    false !== strpos((string) $mysqlError, 'execution was interrupted')) {
                    $this->onCommandFailedRetry((string) $mysqlError, $query, $retry);
                } elseif (false !== strpos((string) $mysqlError, 'uplicate entry') ||
                    false !== strpos((string) $mysqlError, 'error in your SQL syntax')) {
                    $retry = -1;
                } elseif ($this->mysqliHandler->isServerGone()) {
                    /**
                     * MySQL server has gone away; Connection closed from server-side
                     * All state is lost
                     */
                    if ($this->options->isAutocommit()) {
                        /**
                         * If there was autocommit, we only lost last transmission; lets disconnect & retry
                         */
                        $this->mysqliHandler->close();
                    } else {
                        /**
                         * No autocommit == lost all changes, no need to retry anything
                         */
                        $retry = -1;
                    }
                } else {
                    /**
                     * Generic sleep, including 'MySQL server has gone away' and others
                     */
                    usleep(abs($this->options->getRetryWait()));
                }

                $retry = $retry > 0
                    ? $retry - 1
                    : $this->options->getRetryTimes();

                return $this->command($query, $retry);
            }

            if (0 === $retry || $retry <= 0) {
                if (false !== strpos((string) $mysqlError, 'try restarting') ||
                    false !== strpos((string) $mysqlError, 'execution was interrupted')) {
                    $this->onError((string) $mysqlError, $query);
                }

                $this->onError((string) $mysqlError, $query);
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<string>
     *
     * @throws MydbException
     *
     * @psalm-return list<string>
     */
    public function getEnumValues(string $table, string $column): array
    {
        $query = "SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'";

        $resultArray = $this->query($query);

        if (!isset($resultArray[0]['Type'])) {
            return [];
        }

        $type = strtolower((string) $resultArray[0]['Type']);
        if (!(
                0 === strpos($type, 'set(') || (0 === strpos($type, 'enum('))
            )
        ) {
            $this->onError("get_possible_values: requested row is not of type 'enum' or 'set'", $query);
        }

        /**
         * @psalm-suppress PossiblyFalseOperand
         */
        $values = explode(
            ',',
            preg_replace(
                "/'/",
                '',
                substr((string) $resultArray[0]['Type'], strpos((string) $resultArray[0]['Type'], '(') + 1, -1),
            ),
        );

        return array_map('strval', $values);
    }

    /**
     * @throws MydbConnectionException
     */
    public function escape(string $unescaped): string
    {
        if (is_numeric($unescaped) && ctype_digit($unescaped)) {
            return $unescaped;
        }

        if (preg_match('/^([a-zA-Z0-9_])*$/', $unescaped)) {
            return $unescaped;
        }

        if (!$this->connect()) {
            throw new MydbConnectionException();
        }

        return (string) $this->mysqliHandler->realEscapeString($unescaped);
    }

    public function deleteWhere(array $whereFields, string $table, array $whereNotFields = []): void
    {
        /** @lang text */
        $query =
            'DELETE FROM `' . $table . '`';

        $queryWhere = $this->buildWhereQuery($whereFields, $whereNotFields);

        if (!$queryWhere) {
            return;
        }

        $query .= ' WHERE ' . $queryWhere;
        $this->delete($query);
    }

    public function getPrimaryKey(string $table): ?string
    {
        $sql = 'SHOW KEYS FROM `' . $table . '`';

        $result = $this->query($sql);

        foreach ($result as $row) {
            if (!isset($row['Key_name'])) {
                continue;
            }

            if ('PRIMARY' === $row['Key_name']) {
                return isset($row['Column_name'])
                    ? (string) $row['Column_name']
                    : null;
            }
        }

        return null;
    }

    /**
     * @throws MydbException
     */
    public function updateWhere(array $update, array $whereFields, string $table, array $whereNotFields = []): bool
    {
        $values = [];
        $queryWhere = $this->buildWhereQuery($whereFields, $whereNotFields);

        foreach ($update as $field => $value) {
            /**
             * @psalm-suppress InvalidOperand
             */
            $f = '`' . (string) $field . '`' . '=';

            $escaped = $this->escapeValue($value);
            if (null !== $escaped) {
                $f .= $escaped;
            } elseif (is_numeric($value) && $value === intval($value)) {
                /**
                 * @psalm-suppress InvalidOperand
                 */
                $f .= $value;
            } else {
                $f .= "'" . $this->escape((string) $value) . "'";
            }

            $values[] = $f;
        }

        $queryUpdate = implode(', ', $values);

        if ($queryUpdate && $queryWhere) {
            $query = 'UPDATE `' . $table . '` SET ' . $queryUpdate . ' WHERE ' . $queryWhere;
            $affectedRows = $this->update($query);

            /**
             * mysqli_affected_rows
             * An integer greater than zero indicates the number of rows affected or retrieved.
             * Zero indicates that no records where updated for an UPDATE statement,
             * no rows matched the WHERE clause in the query or that no query has yet been executed.
             * -1 indicates that the query returned an error.
             */
            if ($affectedRows >= 0) {
                return true;
            }

            if (-1 === $affectedRows) {
                $this->onError('query returned an error', $query);
            }

            return false;
        }

        return false;
    }

    /**
     * @param array $columnSetWhere ['col1' => [ ['current1', 'new1'], ['current2', 'new2']]
     * @param array $where ['col2' => 'value2', 'col3' => ['v3', 'v4']]
     * @param string $table 'mytable'
     * @throws MydbException
     */
    public function updateWhereMany(array $columnSetWhere, array $where, string $table): void
    {
        $sql = 'UPDATE `' . $table . '`';
        foreach ($columnSetWhere as $column => $map) {
            /**
             * @psalm-suppress InvalidOperand
             */
            $sql .= ' SET `' . $column . '` = CASE';

            foreach ($map as $newValueWhere) {
                $escaped = $this->escapeValue($newValueWhere[0]);
                if (null !== $escaped) {
                    $whereKey = $escaped;
                } elseif (is_numeric($newValueWhere[0]) && intval($newValueWhere[0]) === $newValueWhere[0]) {
                    $whereKey = $newValueWhere[0];
                } else {
                    $whereKey = "'" . $this->escape($newValueWhere[0]) . "'";
                }

                /**
                 * @psalm-suppress InvalidOperand
                 */
                $sql .= ' WHEN (`' . $column . '` = ' . $whereKey . ') THEN ';

                $escaped = $this->escapeValue($newValueWhere[1]);
                if (null !== $escaped) {
                    $sql .= $escaped;
                } else {
                    $sql .= "'" . $this->escape((string) $newValueWhere[1]) . "'";
                }
            }

            /**
             * @psalm-suppress InvalidOperand
             */
            $sql .= ' ELSE `' . $column . '`';
        }

        $sql .= ' END WHERE ' . $this->buildWhereQuery($where);
        $this->update($sql);
    }

    public function insertMany(
        array $data,
        array $columns,
        string $table,
        bool $ignore = false,
        ?string $onDuplicateKeyUpdate = null
    ): void {
        $me = $this;
        /**
         * @phpcs:disable SlevomatCodingStandard.Functions.DisallowArrowFunction
         * @psalm-suppress MissingClosureParamType
         */
        $values = array_map(
            fn ($r) => '(' . implode(
                ', ',
                array_map(function ($input) use ($me) {
                    $escaped = $this->escapeValue($input);
                    if (null !== $escaped) {
                        return $escaped;
                    }

                    return "'" . $me->escape((string) $input) . "'";
                }, $r),
            ) . ') ',
            $data,
        );

        $query = "INSERT " . ($ignore ? 'IGNORE ' : '') . "INTO `" . $table . "` (`" . implode(
            '`, `',
            $columns,
        ) . "`) VALUES " . implode(', ', $values);

        if ($onDuplicateKeyUpdate) {
            $query .= ' ON DUPLICATE KEY UPDATE ' . $onDuplicateKeyUpdate;
        }
        $this->insert($query);
    }

    public function replaceOne(array $data, string $table): ?string
    {
        $names = [];
        $values = [];

        foreach ($data as $name => $value) {
            $names[] = $name;

            $escaped = $this->escapeValue($value);
            $v = $escaped ?? "'" . $this->escape($value) . "'";

            $values[] = $v;
        }

        $query = sprintf(
        /** @lang text */
            'REPLACE INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', $names),
            implode(',', $values)
        );

        return $this->replace($query);
    }

    public function insertOne(array $data, string $table): ?string
    {
        $names = [];
        $values = [];

        foreach ($data as $name => $value) {
            $names[] = $name;

            $escaped = $this->escapeValue($value);
            $v = $escaped ?? "'" . $this->escape((string) $value) . "'";

            $values[] = $v;
        }

        $query = sprintf(
            /** @lang text */
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', $names),
            implode(',', $values)
        );

        return $this->insert($query);
    }

    /**
     * @throws MydbConnectionException
     */
    public function setAutoCommit(bool $autocommit): bool
    {
        if (!$this->connect()) {
            throw new MydbConnectionException();
        }

        if ($this->mysqliHandler->autocommit($autocommit)) {
            $this->options->setAutocommit($autocommit);

            return true;
        }

        return false;
    }

    /**
     * @throws MydbConnectionException
     * @throws MydbException
     */
    public function beginTransaction(): void
    {
        if (!$this->connect()) {
            throw new MydbConnectionException();
        }

        if ($this->mysqliHandler->beginTransaction()) {
            return;
        }

        $this->onError('Cannot start db transaction');
    }

    /**
     * @throws MydbConnectionException
     * @throws MydbException
     */
    public function rollbackTransaction(): void
    {
        if (!$this->mysqliHandler->isConnected()) {
            throw new MydbConnectionException();
        }

        if ($this->mysqliHandler->rollback()) {
            return;
        }

        $this->onError('Cannot rollback db transaction');
    }

    /**
     * @throws MydbConnectionException
     * @throws MydbException
     */
    public function commitTransaction(): void
    {
        if (!$this->mysqliHandler->isConnected()) {
            throw new MydbConnectionException();
        }

        if ($this->mysqliHandler->commit()) {
            return;
        }

        $this->onError('Cannot commit db transaction');
    }

    /**
     * @throws MydbException
     */
    public function close(): void
    {
        if (!$this->mysqliHandler->isConnected()) {
            return;
        }

        try {
            /**
             * No autocommit
             * No transaction
             *
             * Default: commit all commands
             */
            if (false === $this->options->isAutocommit() && false === $this->mysqliHandler->isTransactionOpen()) {
                if (false === $this->mysqliHandler->commit(
                    /**
                     * RELEASE clause causes the server to disconnect the current client session
                     * after terminating the current transaction.
                     */
                    $this->options->isPersistent() ?
                    MydbMysqli::MYSQLI_TRANS_COR_NO_RELEASE :
                    MydbMysqli::MYSQLI_TRANS_COR_RELEASE
                )) {
                    $this->onError('Failed to commit to database during controlled disconnect');
                }
            }

            if (!$this->options->isPersistent()) {
                /**
                 * Explicitly closing open connections and freeing result sets is optional but recommended
                 * Server already closed connection from server-side
                 */
                if (!$this->mysqliHandler->close()) {
                    $this->onError('Failed to final close database during controlled disconnect');
                }
            }
        } catch (Throwable $e) {
            $this->onError($e->getMessage());
        }
    }

    protected function onCommandFailedRetry(string $message, string $query, ?int $atttempt = null): void
    {
        $this->onWarning($message, $query);
        usleep(random_int(50000, 100000 * min(1, abs((int) $atttempt))));
    }

    /**
     * @param float|int|string|MydbExpression $input
     */
    protected function escapeValue($input): ?string
    {
        if ($input instanceof MydbExpression) {
            return (string) $input;
        }

        if (is_string($input)) {
            $isHex = '0x' === substr($input, 0, 2) && ctype_xdigit(substr($input, 2));
            if ($isHex || 'NULL' === strtoupper($input)) {
                return $input;
            }
        }

        return null;
    }

    protected function onWarning(string $warningMessage, ?string $sql = null): void
    {
        $this->options->getLogger()->warning($warningMessage, ['sql' => $sql]);
    }

    /**
     * @throws MydbException
     */
    protected function onError(string $errorMessage, ?string $sql = null): void
    {
        $this->options->getLogger()->error($errorMessage, ['sql' => $sql]);

        throw new MydbException($errorMessage);
    }

    protected function setMysqliOptions(): bool
    {
        $c = $this->mysqliHandler->getMysqli();
        if (!$c) {
            return false;
        }
        $ignoreUserAbort = $this->options->getIgnoreUserAbort();

        $mysqliInit = sprintf('SET SESSION sql_mode = %s', $this->options->getInternalClientSQLMode());
        if ($ignoreUserAbort < 1) {
            $mysqliInit .= sprintf(', SESSION max_execution_time = %s', $this->options->getMaxExecutionTime());
        }

        return $c->options(MydbMysqli::MYSQLI_OPT_CONNECT_TIMEOUT, $this->options->getTimeoutConnectSeconds()) &&
               $c->options(MydbMysqli::MYSQLI_OPT_READ_TIMEOUT, $this->options->getTimeoutReadSeconds()) &&
               $c->options(MydbMysqli::MYSQLI_OPT_NET_CMD_BUFFER_SIZE, $this->options->getInternalCmdBufferSuze()) &&
               $c->options(MydbMysqli::MYSQLI_OPT_NET_READ_BUFFER_SIZE, $this->options->getInternalNetReadBuffer()) &&
               $c->options(MydbMysqli::MYSQLI_INIT_COMMAND, $mysqliInit);
    }

    /**
     * @throws MydbException
     */
    protected function afterConnectionSuccess(): void
    {
        $c = $this->mysqliHandler->getMysqli();
        if (!$c) {
            return;
        }

        $isReadonly = $this->options->isReadonly();

        /**
         * @todo error handling for query executions
         */
        $c->query(sprintf("SET session time_zone = '%s'", $this->options->getTimeZone()));
        $c->query(sprintf('SET session wait_timeout = %s', $this->options->getInternalNonInteractiveTimeout()));
        $c->set_charset($this->options->getCharset());

        if (!$isReadonly) {
            return;
        }

        if (false === $this->options->isPersistent()) {
            if (false === $c->autocommit(true)) {
                throw new MydbException('Failed setting db autocommit state for read-only scenario');
            }
        }

        $level = $this->options->getInternalTransactionIsolationLevelReadonly();
        $c->query('SET SESSION TRANSACTION ISOLATION LEVEL ' . $level);

        $readonly = $c->begin_transaction(MydbMysqli::MYSQLI_TRANS_START_READ_ONLY);
        if ($readonly) {
            return;
        }

        $c->query('START TRANSACTION READ ONLY');
    }

    protected function setPHPErrorReporting(int $level): int
    {
        return error_reporting($level);
    }

    /**
     * @throws MydbException
     */
    private function connect(?int $retry = null): bool
    {
        if ($this->mysqliHandler->isConnected()) {
            return true;
        }

        $connected = false;
        if ($this->mysqliHandler->init() && $this->setMysqliOptions()) {
            $reportingLevel = $this->setPHPErrorReporting($this->options->getErrorReporting());
            $connected = $this->mysqliHandler->realConnect(
                ($this->options->isPersistent() ? 'p:' : '') . $this->hostname,
                $this->username,
                $this->password,
                $this->databaseName,
                $this->portNumber
            );
            $this->setPHPErrorReporting($reportingLevel);
        }

        if (!$connected || $this->mysqliHandler->getConnectErrno()) {
            if ($this->mysqliHandler->getMysqli()) {
                if ($this->mysqliHandler->getConnectErrno()) {
                    $errorNumber = $this->mysqliHandler->getConnectErrno();
                    $errorText = $this->mysqliHandler->getConnectError();
                } else {
                    $errorNumber = $this->mysqliHandler->getErrNo();
                    $errorText = $this->mysqliHandler->getError();
                }
            } else {
                $errorNumber = (int) ($this->mysqliHandler->getConnectErrno());
                $errorText = 'Mysqli connectivity issues: ' . (string) $this->mysqliHandler->getConnectError();
            }

            $this->mysqliHandler->close();

            if ((2006 !== $errorNumber) || null !== $retry) {
                $this->onError((string) $errorText);
            }

            if (2002 === $errorNumber) {
                $retry = 0;
            }

            if ((null === $retry || $retry > 0)) {
                if ($retry > 0) {
                    usleep(abs($this->options->getRetryWait() * $retry));
                }

                $retry = $retry
                    ? $retry - 1
                    : $this->options->getRetryTimes();

                return $this->connect($retry);
            }

            if (0 === $retry || $retry < 0) {
                throw new MydbException(
                    'Cannot connect ' . $this->hostname . ': #' . (string) $errorNumber . ' ' . (string) $errorText,
                    (int) $errorNumber
                );
            }

            return false;
        }

        $this->mysqliHandler->mysqliReport($this->options->getInternalClientErrorLevel());

        if (!$this->options->isAutocommit()) {
            if (false === $this->mysqliHandler->autocommit(false)) {
                throw new MydbException('Failed setting db autocommit state');
            }
        }

        $this->checkServerVersion();

        $this->afterConnectionSuccess();

        return true;
    }

    /**
     * @return void
     * @throws MydbException
     */
    private function checkServerVersion(): void
    {
        if (!$this->mysqliHandler->isConnected()) {
            throw new MydbConnectionException();
        }

        if ($this->mysqliHandler->getServerVersion() < '50708') {
            /**
             * Minimum version
             * max_statement_time added MySQL 5.7.4; renamed max_execution_time MySQL 5.7.8
             */
            throw new MydbException('Minimum required MySQL server version is 50708');
        }
    }

    private function buildWhereQuery(array $fields = [], array $negativeFields = [], array $likeFields = []): string
    {
        $where = [];

        foreach ($fields as $field => $value) {
            /**
             * @psalm-suppress InvalidOperand
             */
            $queryPart = '`' . $field . '`';
            $isNegative = in_array($field, $negativeFields, true);
            $inNull = false;

            if (null === $value) {
                $queryPart .= ' IS ' . ($isNegative ? 'NOT ' : '') . 'NULL';
            } elseif (is_array($value)) {
                if (1 === count($value)) {
                    $qvalue = implode('', $value);
                    $queryPart .= ($isNegative ? '!' : '') . '=';

                    $escaped = $this->escapeValue($qvalue);
                    if (null !== $escaped) {
                        $queryPart .= $escaped;
                    } else {
                        $queryPart .= "'" . $this->escape($qvalue) . "'";
                    }
                } else {
                    $queryPart .= ($isNegative ? ' NOT' : '') . " IN (";
                    $inVals = [];

                    foreach ($value as $val) {
                        if (null === $val) {
                            $inNull = true;
                        } else {
                            $escaped = $this->escapeValue($val);
                            if (null !== $escaped) {
                                $inVals[] = $escaped;
                            } elseif (is_numeric($val) && intval($val) === $val) {
                                $inVals[] = $val;
                            } else {
                                $inVals[] = "'" . $this->escape($val) . "'";
                            }
                        }
                    }

                    $queryPart .= implode(',', $inVals) . ')';
                }
            } else {
                $equality = ($isNegative ? '!' : '') . "=";

                if (in_array($field, $likeFields, true)) {
                    $equality = ($isNegative ? ' NOT ' : ' ') . " LIKE ";
                }

                $queryPart .= $equality;

                $escaped = $this->escapeValue($value);
                if (null !== $escaped) {
                    $queryPart .= $escaped;
                } elseif (is_numeric($value) && $value === intval($value)) {
                    $queryPart .= (string) $value;
                } else {
                    /**
                     * @psalm-suppress InvalidOperand
                     */
                    $queryPart .= "'" . $this->escape($value) . "'";
                }
            }

            if ($inNull) {
                $queryPart = sprintf(' ( %s OR %s IS NULL ) ', $queryPart, $field);
            }

            $where[] = $queryPart;
        }

        $condition = [];

        if (count($where)) {
            $condition[] = implode(' AND ', $where);
        }

        $condition = implode(' AND ', $condition);

        return $condition;
    }
}
