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

use Psr\Log\LoggerInterface;
use sql\MydbException\AsyncException;
use sql\MydbException\ConnectException;
use sql\MydbException\DeleteException;
use sql\MydbException\DisconnectException;
use sql\MydbException\InternalException;
use sql\MydbException\TerminationSignalException;
use sql\MydbException\TransactionAutocommitException;
use sql\MydbException\TransactionBeginReadonlyException;
use sql\MydbException\TransactionBeginReadwriteException;
use sql\MydbException\TransactionCommitException;
use sql\MydbException\TransactionRollbackException;
use sql\MydbException\UpdateException;
use sql\MydbMysqli\MydbMysqliResult;
use Throwable;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_null;
use function is_string;
use function preg_match;
use function preg_replace;
use function sprintf;
use function stripos;
use function strpos;
use function substr;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Mydb implements
    MydbInterface,
    MydbInterface\EncoderInterface,
    MydbInterface\CommandInterface,
    MydbInterface\QueryInterface,
    MydbInterface\DataManipulationStatementsInterface,
    MydbInterface\TransactionInterface,
    MydbInterface\AsyncInterface,
    MydbInterface\AdministrationStatementsInterface,
    MydbInterface\RemoteResourceInterface
{

    protected MydbMysqli $mysqli;

    protected MydbCredentials $credentials;

    protected MydbOptions $options;

    protected LoggerInterface $logger;

    protected MydbEnvironment $environment;

    protected bool $terminating = false;

    public function __construct(
        MydbCredentials $credentials,
        LoggerInterface $logger,
        ?MydbOptions $options = null,
        ?MydbMysqli $mysqli = null,
        ?MydbEnvironment $environment = null
    ) {
        $this->credentials = $credentials;
        $this->logger = $logger;
        $this->options = $options ?? new MydbOptions();
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
            throw new AsyncException('Async is safe only with autocommit=true & non-persistent & rw configuration');
        }

        if ($this->mysqli->isTransactionOpen()) {
            throw new AsyncException('Detected transaction pending, refusing to execute async query');
        }

        if (false === $this->mysqli->mysqliQueryAsync($command)) {
            throw new AsyncException('Async command failed');
        }
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
     * @throws MydbException
     * @psalm-return list<string>
     */
    public function getEnumValues(string $table, string $column): array
    {
        return $this->getIterableValues($table, $column);
    }

    /**
     * @param float|int|string|MydbExpression|null $unescaped
     * @throws ConnectException
     * @throws MydbException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @todo reduce NPathComplexity
     */
    public function escape($unescaped, string $quote = "'"): string
    {
        if (is_float($unescaped)) {
            return (string) $unescaped;
        }

        if (is_int($unescaped)) {
            return (string) $unescaped;
        }

        if (is_string($unescaped)) {
            if ('null' === $unescaped || 'NULL' === $unescaped) {
                return 'NULL';
            }

            /**
             * Not quoting '0x...' decimal values
             */
            if (0 === strpos($unescaped, '0x') && preg_match('/[a-zA-Z0-9]+/', $unescaped)) {
                return $unescaped;
            }
        }

        if ($unescaped instanceof MydbExpression) {
            return (string) $unescaped;
        }

        if (is_null($unescaped)) {
            return '';
        }

        if (preg_match('/^(\w)*$/', $unescaped)) {
            return '' !== $quote
                ? $quote . $unescaped . $quote
                : $unescaped;
        }

        if (!$this->connect()) {
            throw new ConnectException();
        }

        $escaped = (string) $this->mysqli->realEscapeString($unescaped);

        return '' !== $quote
            ? $quote . $escaped . $quote
            : $escaped;
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function getPrimaryKey(string $table): ?string
    {
        /**
         * @todo refactor to composite keys, unique keys etc. multiple-values
         */
        $sql = 'SHOW KEYS FROM `' . $this->escape($table, '') . '`';

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
     * @throws ConnectException
     * @throws MydbException
     */
    public function beginTransaction(): void
    {
        if (!$this->connect()) {
            throw new ConnectException();
        }

        if ($this->options->isReadonly()) {
            if ($this->mysqli->beginTransactionReadonly()) {
                return;
            }
            $this->onError(new TransactionBeginReadonlyException());
        } else {
            if ($this->mysqli->beginTransactionReadwrite()) {
                return;
            }
            $this->onError(new TransactionBeginReadwriteException());
        }
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
             * Default: commit all commands if transaction was NOT open
             */
            if (false === $this->options->isAutocommit() && false === $this->mysqli->isTransactionOpen()) {
                /**
                 * RELEASE clause causes the server to disconnect the current client session
                 * after terminating the current transaction.
                 */
                $commit = $this->options->isPersistent()
                    ? $this->mysqli->commit()
                    : $this->mysqli->commitAndRelease();

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

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function replace(string $query): ?string
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
    public function insert(string $query): ?string
    {
        if ($this->command($query)) {
            return (string) $this->mysqli->getInsertId();
        }

        return null;
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
     * @throws ConnectException
     * @throws DeleteException
     * @throws MydbException
     */
    public function delete(string $query): ?int
    {
        if ($this->command($query)) {
            $rows = $this->mysqli->getAffectedRows();
            if (null === $rows) {
                $this->onError(new DeleteException(), $query);
            }

            return $rows;
        }

        return null;
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     * @throws UpdateException
     */
    public function update(string $query): ?int
    {
        if ($this->command($query)) {
            $rows = $this->mysqli->getAffectedRows();
            if (null === $rows) {
                $this->onError(new UpdateException(), $query);
            }

            return $rows;
        }

        return null;
    }

    /**
     * @throws MydbException
     * @throws ConnectException
     */
    public function deleteWhere(array $whereFields, string $table, array $whereNotFields = []): ?int
    {
        /** @lang text */
        $query = 'DELETE FROM ' . $this->escape($table, '`');

        $queryWhere = $this->buildWhereQuery($whereFields, $whereNotFields);

        if ('' === $queryWhere) {
            return null;
        }

        $query .= ' WHERE ' . $queryWhere;

        return $this->delete($query);
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
            $f = '`' . (string) $field . '`' . ' = ' . $this->escape($value);
            $values[] = $f;
        }

        $queryUpdate = implode(', ', $values);

        if ('' !== $queryUpdate && '' !== $queryWhere) {
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
                /**
                 * @psalm-suppress InvalidOperand
                 */
                $sql .= ' WHEN (`' . $column . '` = ' . $this->escape($newValueWhere[0]) . ')';
                $sql .= ' THEN ' . $this->escape($newValueWhere[1]);
            }

            /**
             * @psalm-suppress InvalidOperand
             */
            $sql .= ' ELSE `' . $column . '`';
        }

        $sql .= ' END WHERE ' . $this->buildWhereQuery($where);
        $this->update($sql);
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
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
            static fn ($r) => '(' . implode(
                ', ',
                array_map(static function ($input) use ($me) {
                        /** @phan-suppress-next-line PhanThrowTypeAbsentForCall */
                        return $me->escape((string) $input);
                }, $r),
            ) . ') ',
            $data,
        );

        $query = "INSERT " . ($ignore ? 'IGNORE ' : '') . "INTO `" . $table . "` (`" . implode(
            '`, `',
            $columns,
        ) . "`) VALUES " . implode(', ', $values);

        if (null !== $onDuplicate) {
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
            $names[] = $this->escape($name, "`");
            $values[] = $this->escape($value);
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

    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function insertOne(array $data, string $table): ?string
    {
        $names = [];
        $values = [];

        foreach ($data as $name => $value) {
            $names[] = $this->escape($name, "`");
            $values[] = $this->escape($value);
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
     * @throws MydbException
     * @throws ConnectException
     * @psalm-return list<string>
     */
    protected function getIterableValues(string $table, string $column): array
    {
        $query = "SHOW COLUMNS FROM `" . $this->escape($table, '') . "` ";
        $query .= "LIKE " . $this->escape($column);

        $resultArray = $this->query($query);
        $result = isset($resultArray[0]['Type'])
            ? (string) $resultArray[0]['Type']
            : null;

        $match = false;
        $types = ['enum', 'set'];
        foreach ($types as $type) {
            if (0 === stripos((string)$result, $type . '(')) {
                $match = $type;

                break;
            }
        }

        if (false === $match) {
            $this->onError(new MydbException("Column not of type '" . implode(',', $types) . "'"));
        }

        /**
         * @psalm-suppress PossiblyFalseOperand
         */
        $input = substr((string) $result, (int) strpos((string) $result, '(') + 1, -1);
        if (false === $input) {
            throw new InternalException();
        }
        $values = explode(',', preg_replace("/'/", '', $input));

        return array_map('strval', $values);
    }

    /**
     * @throws MydbException\EnvironmentException
     * @throws TerminationSignalException
     */
    protected function sendClientRequest(string $query): bool
    {
        $this->environment->startSignalsTrap();
        $this->environment->set_error_handler();

        $result = $this->mysqli->realQuery($query);

        $this->environment->restore_error_handler();
        if ($this->environment->endSignalsTrap()) {
            throw new TerminationSignalException();
        }

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
                /**
                 * server closed connection
                 */
                $this->mysqli->close();
            }
            $this->onError(new InternalException($errorMessage), $query);
        }

        return $packet;
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

        /**
         * @todo error handling for query executions
         */
        $c->query(sprintf("SET session time_zone = '%s'", $this->options->getTimeZone()));
        $c->query(sprintf('SET session wait_timeout = %s', $this->options->getNonInteractiveTimeout()));
        $c->set_charset($this->options->getCharset());

        if (!$this->options->isReadonly()) {
            return;
        }

        if (false === $c->autocommit(true)) {
            throw new TransactionAutocommitException();
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
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @todo reduce NPathComplexity
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

            $this->onWarning($errorNumber . ' ' . $errorText);

            if ($retry > 0) {
                $retry -= 1;

                return $this->connect($retry);
            }

            return false;
        }

        if ($this->mysqli->getServerVersion() < '50708') {
            /**
             * Minimum version
             * max_statement_time added MySQL 5.7.4 later renamed to max_execution_time in MySQL 5.7.8
             */
            throw new MydbException('Minimum required MySQL server version is 50708');
        }

        $this->mysqli->mysqliReport($this->options->getClientErrorLevel());

        if (false === $this->mysqli->autocommit($this->options->isAutocommit())) {
            throw new TransactionAutocommitException();
        }

        $this->afterConnectionSuccess();

        return true;
    }

    /**
     * @throws ConnectException
     * @throws MydbException
     */
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
                    $queryPart .= $this->escape($qvalue);
                } else {
                    $queryPart .= ($isNegative ? ' NOT' : '') . " IN (";
                    $inVals = [];

                    foreach ($value as $val) {
                        if (null === $val) {
                            $inNull = true;
                        } else {
                            $inVals[] = $this->escape($val);
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
                $queryPart .= $this->escape($value);
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
