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
use sql\MydbException\ConnectDefaultsException;
use sql\MydbException\ConnectException;
use sql\MydbException\DeleteException;
use sql\MydbException\DisconnectException;
use sql\MydbException\InternalException;
use sql\MydbException\ServerGoneException;
use sql\MydbException\TerminationSignalException;
use sql\MydbException\TransactionAutocommitException;
use sql\MydbException\TransactionBeginReadonlyException;
use sql\MydbException\TransactionBeginReadwriteException;
use sql\MydbException\TransactionCommitException;
use sql\MydbException\TransactionIsolationException;
use sql\MydbException\TransactionRollbackException;
use sql\MydbException\UpdateException;
use sql\MydbInterface\RemoteResourceInterface;
use sql\MydbListener\InternalListener;
use sql\MydbMysqli\MydbMysqliResultInterface;
use Throwable;
use function array_map;
use function count;
use function explode;
use function implode;
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
class Mydb implements MydbInterface, RemoteResourceInterface
{

    protected MydbMysqliInterface $mysqli;

    protected MydbOptionsInterface $options;

    protected MydbEnvironmentInterface $environment;

    protected MydbQueryBuilderInterface $queryBuilder;

    protected MydbListenerInterface $eventListener;

    protected bool $terminating = false;

    public function __construct(
        protected MydbCredentialsInterface $credentials,
        protected LoggerInterface $logger,
        ?MydbOptionsInterface $options = null,
        ?MydbMysqliInterface $mysqli = null,
        ?MydbEnvironmentInterface $environment = null,
        ?MydbQueryBuilderInterface $queryBuilder = null,
        ?MydbListenerInterface $eventListener = null,
    ) {
        $this->options = $options ?? new MydbOptions();
        $this->mysqli = $mysqli ?? new MydbMysqli();
        $this->environment = $environment ?? new MydbEnvironment();
        $this->queryBuilder = $queryBuilder ?? new MydbQueryBuilder($this->mysqli);
        $this->eventListener = $eventListener ?? new InternalListener($logger);
    }

    /**
     * A destructor may be called as soon as there are no references to an object.
     *
     * @see http://php.net/manual/en/mysqli.close.php
     * @see http://php.net/manual/en/mysqli.ping.php (MysqlND not supports reconnect)
     * @throws \sql\MydbException
     */
    public function __destruct()
    {
        $this->terminating = true;
        $this->close();
    }

    /**
     * Open connection to remote server
     * @param int $retry retry failed connection attempts
     * @throws \sql\MydbException
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
     * @psalm-return array<array-key, array<array-key, (float|int|string|null)>>|null
     * @throws \sql\MydbException\ConnectException
     * @throws \sql\MydbException
     */
    public function query(string $query): ?array
    {

        if (!$this->connect()) {
            throw new ConnectException();
        }

        $result = $this->sendClientRequest($query);
        /**
         * We should always read server response, no matter whether sendClientRequest result
         */
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

            /**
             * @var array<array-key, array<array-key, (float|int|string|null)>> $payload
             */
            return $payload;
        }

        return null;
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive
     * @throws \sql\MydbException
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
     * @throws \sql\MydbException
     * @psalm-return list<string>
     */
    public function getEnumValues(string $table, string $column): array
    {
        $query = $this->queryBuilder->showColumnsLike($table, $column);

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

        $input = substr((string) $result, (int) strpos((string) $result, '(') + 1, -1);

        // @codeCoverageIgnoreStart
        /**
         * @psalm-suppress DocblockTypeContradiction
         */
        if ('' === $input) {
            throw new MydbException();
        }
        // @codeCoverageIgnoreEnd

        $values = explode(',', preg_replace("/'/", '', $input));

        return array_map('strval', $values);
    }

    /**
     * @param float|int|string|\sql\MydbExpressionInterface|null $unescaped
     * @throws \sql\MydbException\ConnectException
     * @throws \sql\MydbException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @todo reduce NPathComplexity
     */
    public function escape($unescaped, string $quote = "'"): string
    {
        if (!$this->connect()) {
            throw new ConnectException();
        }

        return $this->queryBuilder->escape($unescaped, $quote);
    }

    /**
     * @throws \sql\MydbException
     * @throws \sql\MydbException\ConnectException
     * @return ?array<string>
     */
    public function getPrimaryKeys(string $table): ?array
    {
        $result = $this->query($this->queryBuilder->showKeys($table));

        if (null === $result) {
            return null;
        }

        $keys = [];
        foreach ($result as $row) {
            if (isset($row['Key_name']) && 'PRIMARY' === $row['Key_name'] && isset($row['Column_name'])) {
                $keys[] = (string) $row['Column_name'];
            }
        }

        return [] !== $keys ? $keys : null;
    }

    /**
     * @throws \sql\MydbException
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
     * @throws \sql\MydbException
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
     * @throws \sql\MydbException\ConnectException
     * @throws \sql\MydbException
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
     * @throws \sql\MydbException
     */
    public function close(): void
    {
        if (false === $this->mysqli->isConnected()) {
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
                    throw new TransactionCommitException();
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
     * @throws \sql\MydbException\ConnectException
     * @throws \sql\MydbException
     */
    public function replace(string $query): ?string
    {
        return $this->insert($query);
    }

    /**
     * @throws \sql\MydbException\ConnectException
     * @throws \sql\MydbException
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
     * @throws \sql\MydbException
     * @throws \sql\MydbException\ConnectException
     */
    public function select(string $query): ?array
    {
        return $this->query($query);
    }

    /**
     * @throws \sql\MydbException
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
     * @throws \sql\MydbException
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
     * @throws \sql\MydbException
     */
    public function deleteWhere(array $whereFields, string $table, array $whereNotFields = []): ?int
    {
        $query = $this->queryBuilder->buildDeleteWhere($table, $whereFields, $whereNotFields);
        if (null === $query) {
            return null;
        }

        return $this->delete($query);
    }

    /**
     * @param array<string, (float|int|string|\sql\MydbExpressionInterface|null)> $update
     * @throws \sql\MydbException
     */
    public function updateWhere(array $update, array $whereFields, string $table, array $whereNotFields = []): ?int
    {
        $query = $this->queryBuilder->buildUpdateWhere($update, $whereFields, $table, $whereNotFields);

        if ('' !== $query && null !== $query) {
            return $this->update($query);
        }

        return null;
    }

    /**
     * @param array $columnSetWhere ['col1' => [ ['current1', 'new1'], ['current2', 'new2']]
     * @param array $where ['col2' => 'value2', 'col3' => ['v3', 'v4']]
     * @param string $table 'mytable'
     * @throws \sql\MydbException
     */
    public function updateWhereMany(array $columnSetWhere, array $where, string $table): void
    {
        $sql = $this->queryBuilder->buildUpdateWhereMany($columnSetWhere, $where, $table);
        $this->update($sql);
    }

    /**
     * @throws \sql\MydbException\ConnectException
     * @throws \sql\MydbException
     * @psalm-param array<array-key, array<array-key, (float|int|string|\sql\MydbExpressionInterface|null)>> $data
     * @param array<string> $cols
     */
    public function insertMany(
        array $data,
        array $cols,
        string $table,
        bool $ignore = false,
        string $onDuplicateSql = '',
    ): void {
        $sql = $this->queryBuilder->buildInsertMany($data, $cols, $table, $ignore, $onDuplicateSql);
        $this->insert($sql);
    }

    /**
     * @throws \sql\MydbException
     * @param array<string, (float|int|\sql\MydbExpressionInterface|string|null)> $data
     */
    public function replaceOne(array $data, string $table): ?string
    {
        $query = $this->queryBuilder->insertOne($data, $table, MydbQueryBuilderInterface::SQL_REPLACE);

        return $this->replace($query);
    }

    /**
     * @throws \sql\MydbException
     * @param array<string, (float|int|\sql\MydbExpressionInterface|string|null)> $data
     */
    public function insertOne(array $data, string $table): ?string
    {
        $query = $this->queryBuilder->insertOne($data, $table, MydbQueryBuilderInterface::SQL_INSERT);

        return $this->insert($query);
    }

    /**
     * @throws \sql\MydbException\EnvironmentException
     * @throws \sql\MydbException\TerminationSignalException
     * @throws \sql\MydbException\EventException
     */
    protected function sendClientRequest(string $query): bool
    {
        $this->environment->startSignalsTrap();
        $this->environment->set_error_handler();

        (new MydbEvent\InternalQueryBegin($query))->setListeners([$this->eventListener])->notify();
        $result = $this->mysqli->realQuery($query);
        (new MydbEvent\InternalQueryEnd($query, $result))->setListeners([$this->eventListener])->notify();

        $this->environment->restore_error_handler();
        $hasPendingSignals = $this->environment->endSignalsTrap();
        if (null !== $hasPendingSignals && count($hasPendingSignals) > 0) {
            throw new TerminationSignalException();
        }

        return $result;
    }

    /**
     * @throws \sql\MydbException
     */
    protected function readServerResponse(string $query): ?MydbMysqliResultInterface
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
                 * server closed connection, do close() and ignore result of operation
                 */
                $this->mysqli->close();
                $this->onError(new ServerGoneException($errorMessage), $query);
            } else {
                $this->onError(new InternalException($errorMessage), $query);
            }
        }

        return $packet;
    }

    protected function onWarning(string $warningMessage, ?string $sql = null): void
    {
        $this->logger->warning($warningMessage, ['sql' => $sql]);
    }

    /**
     * @throws \sql\MydbException
     */
    protected function onError(MydbException $exception, ?string $sql = null): void
    {
        $this->logger->error($exception->getMessage(), ['sql' => $sql]);

        throw $exception;
    }

    /**
     * @throws \sql\MydbException\DisconnectException
     * @throws \sql\MydbException\TransactionAutocommitException
     * @throws \sql\MydbException\EnvironmentException
     * @throws \sql\MydbException\EventException
     * @throws \sql\MydbException
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

            $host = ($this->options->isPersistent() ? 'p:' : '') . $this->credentials->getHost();
            $dbname = $this->credentials->getDbname();

            (new MydbEvent\InternalConnectionBegin($host, $dbname))
                ->setListeners([$this->eventListener])->notify();
            $connected = $this->mysqli->realConnect(
                $host,
                $this->credentials->getUsername(),
                $this->credentials->getPasswd(),
                $dbname,
                $this->credentials->getPort(),
                $this->credentials->getSocket(),
                $this->credentials->getFlags()
            );
            (new MydbEvent\InternalConnectionEnd($host, $dbname, $connected))
                ->setListeners([$this->eventListener])->notify();

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
                --$retry;

                return $this->connect($retry);
            }

            return false;
        }

        $this->mysqli->mysqliReport($this->options->getClientErrorLevel());

        if (false === $this->mysqli->autocommit($this->options->isAutocommit())) {
            throw new TransactionAutocommitException();
        }

        /**
         * Here's a summary of what variables are set by each statement: SET NAMES vs SET CHARSET
         *
         * Variable                 SET NAMES   SET CHARSET
         * character_set_client     argument    argument
         * character_set_results    argument    argument
         * character_set_connection argument    default for default db
         * collation_connection     argument*   default for default db
         *
         * SET CHARSET doesn't set the connection charset to the charset you specify in the argument,
         * instead it sets it to the charset for your current default database
         */
        $defaults = $this->mysqli->realQuery(
            sprintf(
                "SET time_zone = '%s', wait_timeout = %d, names '%s'",
                $this->options->getTimeZone(),
                $this->options->getNonInteractiveTimeout(),
                $this->options->getCharset()
            )
        );

        if (false === $defaults) {
            throw new ConnectDefaultsException();
        }

        $isoLevel = $this->options->getTransactionIsolationLevel();
        if (null !== $isoLevel && false === $this->mysqli->setTransactionIsolationLevel($isoLevel)) {
            throw new TransactionIsolationException();
        }

        if ($this->options->isReadonly() && false === $this->mysqli->beginTransactionReadonly()) {
            throw new TransactionAutocommitException();
        }

        return true;
    }
}
