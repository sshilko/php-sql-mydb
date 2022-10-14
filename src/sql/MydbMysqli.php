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
use function mysqli_init;
use function mysqli_query;
use function mysqli_report;
use const MYSQLI_ASYNC;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbMysqli
{
    private ?mysqli $mysqli = null;
    private bool $isConnected = false;
    private bool $isTransaction = false;

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

    /**
     * @return \mysqli_result|false
     */
    public function storeResult(int $mode = 0)
    {
        if ($this->mysqli && $this->isConnected()) {
            return $this->mysqli->store_result($mode);
        }

        return false;
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
        if ($this->mysqli && $this->isConnected() && $this->mysqli->close()) {
            $this->isConnected = false;
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

    public function getFieldCount(): ?int
    {
        return $this->mysqli
            ? $this->mysqli->field_count
            : null;
    }

    public function getWarningCount(): ?int
    {
        return $this->mysqli
            ? $this->mysqli->warning_count
            : null;
    }

    public function getWarnings(): ?\mysqli_warning
    {
        return $this->mysqli
            ? $this->mysqli->get_warnings()
            : null;
    }

    public function getAffectedRows(): ?int
    {
        return $this->mysqli
            ? $this->mysqli->affected_rows
            : null;
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
}
