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

namespace sql\MydbEvent;

class InternalConnectionEnd extends InternalEvent
{
    public function __construct(string $host, string $dbname, bool $success)
    {
        $this->data = [
            'host' => $host,
            'dbname' => $dbname,
            'success' => $success,
        ];
    }
}