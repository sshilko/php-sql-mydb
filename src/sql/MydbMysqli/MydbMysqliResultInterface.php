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

namespace sql\MydbMysqli;

use mysqli_result;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 * @access protected
 */
interface MydbMysqliResultInterface
{
    /**
     * @psalm-param array<array-key, string> $warnings
     */
    public function __construct(?mysqli_result $result, array $warnings, int $fieldsCount);

    public function getFieldCount(): int;

    /**
     * @psalm-return array<array-key, string>
     */
    public function getWarnings(): array;

    public function setErrorMessage(string $errorMessage): void;

    public function setErrorNumber(int $errorNumber): void;

    public function getError(): ?string;

    public function getResult(): ?array;
}
