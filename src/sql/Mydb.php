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
use sql\MydbException\ConnectException;
use sql\MydbException\DisconnectException;
use sql\MydbException\InternalException;
use sql\MydbException\TransactionBeginException;
use sql\MydbException\TransactionCommitException;
use sql\MydbException\TransactionException;
use sql\MydbException\TransactionRollbackException;
use sql\MydbMysqli\MydbMysqliResult;
use Throwable;
use function array_map;
use function count;
use function ctype_digit;
use function ctype_xdigit;
use function explode;
use function implode;
use function in_array;
use function intval;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace;
use function sprintf;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class Mydb implements MydbInterface
{
    protected MydbMysqli $mysqli;

    protected MydbCredentials $credentials;

    protected MydbOptions $options;

    protected LoggerInterface $logger;

    protected MydbEnvironment $environment;

    protected bool $terminating = false;

    public function __construct(
        MydbCredentials $credentials,
        MydbOptions $options,
        LoggerInterface $logger,
        ?MydbMysqli $mysqli = null,
        ?MydbEnvironment $environment = null
    ) {
        $this->credentials = $credentials;
        $this->options = $options;
        $this->logger = $logger;
        $this->mysqli = $mysqli ?? new MydbMysqli();
        $this->environment = $environment ?? new MydbEnvironment();
    }

    /**
     * A destructor may be called as soon as there are no references to an object.
     *
     * @see http://php.net/manual/en/mysqli.close.php
     * @see http://php.net/manual/en/mysqli.ping.php (MysqlND not supports reconnect)
     * @throws MydbException
     */
    public function __destruct()
    {
        $this->terminating = true;
        $this->close();
    }

    /**
     * Open connection to remote server
     * @param int $retry retry failed connection attempts
     * @throws MydbException
     */
    public function open(int $retry = 0): bool
    {
        return $this->connect($retry);
    }

    /**
     * Execute raw SQL query and return results
     *
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint
     *
     * @return array<array<(float|int|string|null)>>|null
     * @psalm-return list<array<(float|int|string|null)>>|null
     * @throws ConnectException
     * @throws MydbException
     */
    public function query(string $query): ?array
    {
        if (!$this->connect()) {
            throw new ConnectException();
        }

        $result = $this->sendClientRequest($query);
        $packet = $this->readServerResponse($query);

        if (false === $result || null === $packet) {
            return null;
        }

        if ($packet->getFieldCount() > 0) {
            $payload = $packet->getResult();
            if (null === $payload) {
                $this->onError(
                    new InternalException($packet->getError() ?? 'Reading of the result set failed'),
                    $query
                );
            }

            return $payload;
        }

        return null;
    }

    /**
     * With MYSQLI_ASYNC (available with mysqlnd), it is possible to perform query asynchronously.
     * mysqli_poll() is then used to get results from such queries.
     * @throws MydbException
     * @throws ConnectException
     */
    public function async(string $command): void
    {
        if (!$this->connect()) {
            throw new ConnectException();
        }

        if (false === $this->options->isAutocommit() ||
            $this->options->isPersistent() ||
            $this->options->isReadonly()) {
            throw new MydbException('Async is safe only with autocommit=true & non-persistent & rw configuration');
        }

        $this->mysqli->mysqliQueryAsync($command);
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint
     * @throws MydbException
     * @throws ConnectException
     */
    public function select(string $query): ?array
    {
        return $this->query($query);
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function table(string $query): ?array
    {
        return $this->query($query);
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function values(string $query): ?array
    {
        return $this->query($query);
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function delete(string $query): ?int
    {
        if ($this->command($query)) {
            $rows = $this->mysqli->getAffectedRows();
            if (null === $rows) {
                $this->onError(new MydbException('Delete query returned error'), $query);
            }

            return $rows;
        }

        return null;
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function call(string $query): void
    {
        $this->command($query);
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function do(string $query): void
    {
        $this->command($query);
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function handler(string $query): void
    {
        $this->command($query);
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function dds(string $statement): void
    {
        $this->command($statement);
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function update(string $query): ?int
    {
        if ($this->command($query)) {
            $rows = $this->mysqli->getAffectedRows();
            if (null === $rows) {
                $this->onError(new MydbException('Update query returned error'), $query);
            }

            return $rows;
        }

        return null;
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function insert(string $query): ?string
    {
        if ($this->command($query)) {
            return (string) $this->mysqli->getInsertId();
        }

        return null;
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function replace(string $query): ?string
    {
        if ($this->command($query)) {
            return (string)$this->mysqli->getInsertId();
        }

        return null;
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive
     * @throws MydbException
     * @throws ConnectException
     */
    public function command(string $query): bool
    {
        if (!$this->connect()) {
            throw new ConnectException();
        }

        $result = $this->sendClientRequest($query);

        if (false === $result) {
            return false;
        }

        $packet = $this->readServerResponse($query);

        return null !== $packet;
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
            $this->onError(
                new MydbException("Column not of type 'enum' or 'set'"),
                $query
            );
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
     * @throws ConnectException
     * @throws MydbException
     */
    public function escape(string $unescaped): string
    {
        if (is_numeric($unescaped) && ctype_digit($unescaped)) {
            return $unescaped;
        }

        if (preg_match('/^(\w)*$/', $unescaped)) {
            return $unescaped;
        }

        if (!$this->connect()) {
            throw new ConnectException();
        }

        return (string) $this->mysqli->realEscapeString($unescaped);
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function deleteWhere(array $whereFields, string $table, array $whereNotFields = []): void
    {
        /** @lang text */
        $query = 'DELETE FROM `' . $table . '`';

        $queryWhere = $this->buildWhereQuery($whereFields, $whereNotFields);

        if (!$queryWhere) {
            return;
        }

        $query .= ' WHERE ' . $queryWhere;
        $this->delete($query);
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function getPrimaryKey(string $table): ?string
    {
        $sql = 'SHOW KEYS FROM `' . $table . '`';

        $result = $this->query($sql);

        if (null === $result) {
            return null;
        }

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

            return $affectedRows >= 0;
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
        ?string $onDuplicate = null
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

        if ($onDuplicate) {
            $query .= ' ON DUPLICATE KEY UPDATE ' . $onDuplicate;
        }
        $this->insert($query);
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
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
     * @throws ConnectException
     * @throws MydbException
     */
    public function beginTransaction(): void
    {
        if (!$this->connect()) {
            throw new ConnectException();
        }

        if ($this->mysqli->beginTransaction()) {
            return;
        }

        $this->onError(new TransactionBeginException());
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function rollbackTransaction(): void
    {
        if (!$this->mysqli->isConnected()) {
            throw new ConnectException();
        }

        if ($this->mysqli->rollback()) {
            return;
        }

        $this->onError(new TransactionRollbackException());
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function commitTransaction(): void
    {
        if (!$this->mysqli->isConnected()) {
            throw new ConnectException();
        }

        if ($this->mysqli->commit()) {
            return;
        }

        $this->onError(new TransactionCommitException());
    }

    /**
     * @throws MydbException
     */
    public function close(): void
    {
        if (!$this->mysqli->isConnected()) {
            return;
        }

        try {
            /**
             * No autocommit
             * No transaction
             *
             * Default: commit all commands
             */
            if (false === $this->options->isAutocommit() && false === $this->mysqli->isTransactionOpen()) {
                /**
                 * RELEASE clause causes the server to disconnect the current client session
                 * after terminating the current transaction.
                 */
                $commit = $this->mysqli->commit($this->options->isPersistent() ?
                    MydbMysqli::MYSQLI_TRANS_COR_NO_RELEASE :
                    MydbMysqli::MYSQLI_TRANS_COR_RELEASE);

                if (false === $commit) {
                    $this->onError(new TransactionCommitException());
                }
            }

            /**
             * Explicitly closing open connections and freeing result sets is optional but recommended
             * Server already closed connection from server-side
             */
            if (false === $this->mysqli->close()) {
                throw new DisconnectException();
            }
        } catch (MydbException $e) {
            $this->onError($e);
        } catch (Throwable $e) {
            $this->onError(new InternalException($e->getMessage()));
        }

        if ($this->terminating) {
            return;
        }

        $this->environment->gc_collect_cycles();
    }

    protected function sendClientRequest(string $query): bool
    {
        $originalHandler = $this->environment->set_error_handler();
        $result = $this->mysqli->realQuery($query);
        $this->environment->set_error_handler($originalHandler);

        return $result;
    }

    /**
     * @throws MydbException
     */
    protected function readServerResponse(string $query): ?MydbMysqliResult
    {
        $packet = $this->mysqli->readServerResponse($this->environment);
        if (null === $packet) {
            return null;
        }

        $warnings = $packet->getWarnings();
        if (count($warnings) > 0) {
            foreach ($warnings as $warningMessage) {
                $this->onWarning($warningMessage, $query);
            }
        }

        $errorMessage = $packet->getError();
        if (null !== $errorMessage) {
            if ($this->mysqli->isServerGone()) {
                $this->mysqli->close();
            }
            $this->onError(new InternalException($errorMessage), $query);
        }

        return $packet;
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
        $this->logger->warning($warningMessage, ['sql' => $sql]);
    }

    /**
     * @throws MydbException
     */
    protected function onError(MydbException $exception, ?string $sql = null): void
    {
        $this->logger->error($exception->getMessage(), ['sql' => $sql]);

        throw $exception;
    }

    /**
     * @throws MydbException
     */
    protected function afterConnectionSuccess(): void
    {
        $c = $this->mysqli->getMysqli();
        if (!$c) {
            return;
        }

        $isReadonly = $this->options->isReadonly();

        /**
         * @todo error handling for query executions
         */
        $c->query(sprintf("SET session time_zone = '%s'", $this->options->getTimeZone()));
        $c->query(sprintf('SET session wait_timeout = %s', $this->options->getNonInteractiveTimeout()));
        $c->set_charset($this->options->getCharset());

        if (!$isReadonly) {
            return;
        }

        if (false === $this->options->isPersistent()) {
            if (false === $c->autocommit(true)) {
                throw new MydbException('Failed setting db autocommit state for read-only scenario');
            }
        }

        $c->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $readonly = $c->begin_transaction(MydbMysqli::MYSQLI_TRANS_START_READ_ONLY);
        if ($readonly) {
            return;
        }
        $c->query('START TRANSACTION READ ONLY');
    }

    /**
     * @throws MydbException
     */
    protected function connect(int $retry = 0): bool
    {
        if ($this->mysqli->isConnected()) {
            return true;
        }

        $connected = false;
        $init0 = $this->mysqli->init();
        $init1 = $init0 && $this->mysqli->setTransportOptions($this->options, $this->environment);

        if ($init0 && $init1) {
            $reportingLevel = $this->environment->error_reporting($this->options->getErrorReporting());

            $connected = $this->mysqli->realConnect(
                ($this->options->isPersistent() ? 'p:' : '') . $this->credentials->getHost(),
                $this->credentials->getUsername(),
                $this->credentials->getPasswd(),
                $this->credentials->getDbname(),
                $this->credentials->getPort(),
                $this->credentials->getSocket(),
                $this->credentials->getFlags()
            );

            $this->environment->error_reporting($reportingLevel);
        }

        if (false === $connected) {
            $errorNumber = (string) ($this->mysqli->getConnectErrno() ?: $this->mysqli->getErrNo());
            $errorText = (string) ($this->mysqli->getConnectError() ?: $this->mysqli->getError());

            if (false === $this->mysqli->close()) {
                throw new DisconnectException();
            }

            $this->onWarning($errorNumber . ':' . $errorText);

            if ($retry > 0) {
                $retry -= 1;

                return $this->connect($retry);
            }

            return false;
        }

        $this->checkServerVersion();

        $this->mysqli->mysqliReport($this->options->getInternalClientErrorLevel());

        if (!$this->options->isAutocommit()) {
            if (false === $this->mysqli->autocommit(false)) {
                throw new MydbException('Failed setting db autocommit state');
            }
        }

        $this->afterConnectionSuccess();

        return true;
    }

    /**
     * @return void
     * @throws MydbException
     */
    protected function checkServerVersion(): void
    {
        if ($this->mysqli->isConnected() && $this->mysqli->getServerVersion() < '50708') {
            /**
             * Minimum version
             * max_statement_time added MySQL 5.7.4; renamed max_execution_time MySQL 5.7.8
             */
            throw new MydbException('Minimum required MySQL server version is 50708');
        }
    }

    protected function buildWhereQuery(array $fields = [], array $negativeFields = [], array $likeFields = []): string
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
