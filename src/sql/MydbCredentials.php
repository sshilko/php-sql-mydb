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

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbCredentials
{
    protected string $host;
    protected string $username;
    protected string $passwd;
    protected string $dbname;
    protected ?int $port;
    protected ?string $socket;
    protected int $flags;

    public function __construct(
        string $host,
        string $username,
        string $passwd,
        string $dbname,
        ?int $port = null,
        ?string $socket = null,
        int $flags = 0
    ) {
        $this->host = $host;
        $this->username = $username;
        $this->passwd = $passwd;
        $this->dbname = $dbname;
        $this->port = $port;
        $this->socket = $socket;
        $this->flags = $flags;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswd(): string
    {
        return $this->passwd;
    }

    public function getDbname(): string
    {
        return $this->dbname;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getSocket(): ?string
    {
        return $this->socket;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }
}
