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
use sql\MydbMysqli\MydbMysqliEscapeStringInterface;
use sql\MydbMysqli\MydbMysqliResultInterface;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface MydbMysqliInterface extends MydbMysqliEscapeStringInterface
{

    public function init(): bool;

    public function setTransportOptions(MydbOptionsInterface $options, MydbEnvironmentInterface $environment): bool;

    public function isTransactionOpen(): bool;

    public function setTransactionIsolationLevel(string $level): bool;

    public function isConnected(): bool;

    public function getMysqli(): ?mysqli;

    public function realQuery(string $query): bool;

    public function readServerResponse(MydbEnvironmentInterface $environment): ?MydbMysqliResultInterface;

    public function beginTransactionReadwrite(): bool;

    public function beginTransactionReadonly(): bool;

    public function rollback(): bool;

    public function commitAndRelease(): bool;

    public function commit(): bool;

    public function realConnect(
        string $host,
        string $username,
        string $password,
        string $dbname,
        ?int $port,
        ?string $socket,
        int $flags,
    ): bool;

    public function mysqliReport(int $level): bool;

    public function close(): bool;

    public function getConnectErrno(): ?int;

    public function getConnectError(): ?string;

    public function isServerGone(): bool;

    public function getError(): ?string;

    public function getErrNo(): ?int;

    public function getAffectedRows(): ?int;

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function getInsertId(): int|string|null;

    public function autocommit(bool $enable): bool;

    /**
     * @param array<int, string> $events
     * @phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedPassingByReference
     */
    public function extractServerResponse(MydbEnvironmentInterface $environment, array &$events): ?mysqli_result;

    public function getWarnings(): array;
}
