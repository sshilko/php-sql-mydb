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

namespace phpunit;

use PHPUnit\Framework\TestCase;
use sql\MydbException;
use sql\MydbException\QueryBuilderEscapeException;
use function time;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MydbExceptionTest extends TestCase
{
    /**
     * @throws \sql\MydbException
     */
    public function testMyException(): void
    {
        $message = 'hello world ' . time();
        $exception = new MydbException($message);
        $this->expectException(MydbException::class);
        $this->expectExceptionMessage($message);

        throw $exception;
    }

    /**
     * @throws \sql\MydbException
     */
    public function testQueryBuilderException(): void
    {
        $message = 'hello builder ' . time();
        $exception = new QueryBuilderEscapeException($message);
        $this->expectException(QueryBuilderEscapeException::class);
        $this->expectExceptionMessage("Failed to escape value: " . $message);

        throw $exception;
    }
}
