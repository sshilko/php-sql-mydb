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
class MydbCredentials implements MydbCredentialsInterface
{

    /**
     * Database credentials - hostname
     * Can be either a host name or an IP address. Passing the null value or the string "localhost" to this parameter,
     * the local host is assumed. When possible, pipes will be used instead of the TCP/IP protocol.
     *
     * @see https://www.php.net/manual/en/mysqli.real-connect.php
     */
    protected string $host;

    /**
     * Database credentials - username
     *
     * @see https://www.php.net/manual/en/mysqli.real-connect.php
     */
    protected string $username;

    /**
     * Database credentials - password
     * If provided or null, the MySQL server will attempt to authenticate the user against those user records
     * which have no password only. This allows one username to be used with different permissions
     * (depending on if a password as provided or not).
     *
     * @see https://www.php.net/manual/en/mysqli.real-connect.php
     */
    protected string $passwd;

    /**
     * Database credentials - database name
     * If provided will specify the default database to be used when performing queries.
     *
     * @see https://www.php.net/manual/en/mysqli.real-connect.php
     */
    protected string $dbname;

    /**
     * Database credentials - port
     * Specifies the port number to attempt to connect to the MySQL server.
     *
     * @see https://www.php.net/manual/en/mysqli.real-connect.php
     */
    protected ?int $port;

    /**
     * Database credentials - socket
     * Specifies the socket or named pipe that should be used.
     *
     * @see https://www.php.net/manual/en/mysqli.real-connect.php
     */
    protected ?string $socket;

    /**
     * Database credentials - connection flags
     * With the parameter flags you can set different connection options
     * MYSQLI_CLIENT_COMPRESS - Use compression protocol
     * MYSQLI_CLIENT_SSL - Use SSL (encryption)
     * MULTI_STATEMENT flag is not supported in PHP
     *
     * @see https://www.php.net/manual/en/mysqli.real-connect.php
     */
    protected int $flags;

    public function __construct(
        string $host,
        string $username,
        string $passwd,
        string $dbname,
        ?int $port = null,
        ?string $socket = null,
        int $flags = 0,
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
