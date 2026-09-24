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

use mysqli_stmt;
use Override;
use function is_bool;
use function is_float;
use function is_int;
use const MYSQLI_ASSOC;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MydbPreparedStatement implements MydbPreparedStatementInterface
{

    public function __construct(protected mysqli_stmt $stmt)
    {
    }

    #[Override]
    public function bind(array $params): bool
    {
        if ([] === $params) {
            return true;
        }

        $types = '';
        $bound = [];
        foreach ($params as $key => $param) {
            $types .= match (true) {
                is_int($param), is_bool($param) => 'i',
                is_float($param) => 'd',
                default => 's',
            };
            /**
             * mysqli bind_param() requires arguments by reference; keep a
             * reference into the original array so unpacking works.
             */
            $bound[$key] = &$params[$key];
        }

        /**
         * mysqli bind_param() intentionally takes its values by reference and
         * Psalm cannot model the variadic by-reference unpacking of a spread
         * array. The values are understood at runtime (each bound key holds a
         * reference into $params), so the call is safe by construction.
         *
         * @psalm-suppress TooFewArguments
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        return $this->stmt->bind_param($types, ...$bound);
    }

    #[Override]
    public function execute(): bool
    {
        return $this->stmt->execute();
    }

    #[Override]
    public function fetchAll(): ?array
    {
        $result = $this->stmt->get_result();
        if (false === $result) {
            return null;
        }

        /**
         * @psalm-suppress MixedAssignment
         */
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        return $rows;
    }

    #[Override]
    public function getAffectedRows(): ?int
    {
        $rows = (int) $this->stmt->affected_rows;
        if (0 === $rows || $rows > 0) {
            return $rows;
        }

        /**
         * -1 indicates that the query returned an error.
         */
        return null;
    }

    #[Override]
    public function getInsertId(): int|string|null
    {
        return $this->stmt->insert_id;
    }

    #[Override]
    public function close(): void
    {
        $this->stmt->close();
    }
}