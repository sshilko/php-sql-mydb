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

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface MydbOptionsInterface
{
    public function getNonInteractiveTimeout(): int;

    public function setNonInteractiveTimeout(int $nonInteractiveTimeout): void;

    public function getServerSideSelectTimeout(): int;

    public function setServerSideSelectTimeout(int $seconds): void;

    public function getConnectTimeout(): int;

    public function setConnectTimeout(int $seconds): void;

    public function getErrorReporting(): int;

    public function setErrorReporting(int $errorReporting): void;

    public function getReadTimeout(): int;

    public function setReadTimeout(int $seconds): void;

    public function getNetworkBufferSize(): int;

    public function setNetworkBufferSize(int $bytes): void;

    public function getNetworkReadBuffer(): int;

    public function setNetworkReadBuffer(int $bytes): void;

    public function getClientErrorLevel(): int;

    public function setClientErrorLevel(int $mysqliReport): void;

    public function getTimeZone(): string;

    public function setTimeZone(string $timeZone): void;

    public function isAutocommit(): bool;

    public function setAutocommit(bool $autocommit): void;

    public function getCharset(): string;

    public function setCharset(string $charset): void;

    public function isPersistent(): bool;

    public function setPersistent(bool $persistent): void;

    public function isReadonly(): bool;

    public function setReadonly(bool $readonly): void;
}
