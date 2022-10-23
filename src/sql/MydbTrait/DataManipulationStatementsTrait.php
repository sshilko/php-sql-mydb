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

namespace sql\MydbTrait;

use sql\MydbException;
use sql\MydbException\ConnectException;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
trait DataManipulationStatementsTrait
{
    /**
     * @throws ConnectException
     * @throws MydbException
     */
    public function callStoredProcedure(string $query): void
    {
        $this->command($query);
    }
}