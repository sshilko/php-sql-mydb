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
use const MYSQLI_ASSOC;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 * @access protected
 */
class MydbMysqliResult
{
    protected const MYSQLI_ASSOC = MYSQLI_ASSOC;

    protected ?array $result = null;

    protected array $warnings;

    protected ?string $errorMessage = null;

    protected int $errorNumber = 0;

    protected int $fieldsCount;

    public function __construct(?mysqli_result $result, array $warnings, int $fieldsCount)
    {
        if (null !== $result) {
            $this->result = $result->fetch_all(self::MYSQLI_ASSOC);
            $result->free();
        }

        $this->warnings = $warnings;
        $this->fieldsCount = $fieldsCount;
    }

    public function getFieldCount(): int
    {
        return $this->fieldsCount;
    }

    /**
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function setErrorMessage(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function setErrorNumber(int $errorNumber): void
    {
        $this->errorNumber = $errorNumber;
    }

    public function getError(): ?string
    {
        if ($this->result) {
            return null;
        }

        if ($this->errorNumber > 0 || null !== $this->errorMessage) {
            if (null !== $this->errorMessage && '' !== $this->errorMessage) {
                return ((string) $this->errorNumber) . ' ' . $this->errorMessage;
            }

            // @codeCoverageIgnoreStart
            return (string) $this->errorNumber;
            // @codeCoverageIgnoreEnd
        }

        return null;
    }

    public function getResult(): ?array
    {
        return $this->result;
    }
}
