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

use Override;
use SensitiveParameter;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
readonly class MydbCredentials implements MydbCredentialsInterface
{

    /**
     * Database credentials - hostname
     * Can be either a host name or an IP address. Passing the null value or the string "localhost" to this parameter,
     * the local host is assumed. When possible, pipes will be used instead of the TCP/IP protocol.
     *
     * @param string $host hostname or IP address
     * @param string $username username
     * @param string $passwd password
     * @param string $dbname database name
     * @param int|null $port port number
     * @param string|null $socket socket or named pipe
     * @param int $flags connection flags
     * @see https://www.php.net/manual/en/mysqli.real-connect.php
     */
    public function __construct(
        protected string $host,
        protected string $username,
        #[SensitiveParameter]
        protected string $passwd,
        protected string $dbname,
        protected ?int $port = null,
        protected ?string $socket = null,
        protected int $flags = 0,
    ) {
    }

    #[Override]
    public function getHost(): string
    {
        return $this->host;
    }

    #[Override]
    public function getUsername(): string
    {
        return $this->username;
    }

    #[Override]
    public function getPasswd(): string
    {
        return $this->passwd;
    }

    #[Override]
    public function getDbname(): string
    {
        return $this->dbname;
    }

    #[Override]
    public function getPort(): ?int
    {
        return $this->port;
    }

    #[Override]
    public function getSocket(): ?string
    {
        return $this->socket;
    }

    #[Override]
    public function getFlags(): int
    {
        return $this->flags;
    }
}
