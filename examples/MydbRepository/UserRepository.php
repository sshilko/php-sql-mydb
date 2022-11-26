<?php

declare(strict_types = 1);

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

// @codeCoverageIgnoreStart
namespace MydbRepository;

use sql\MydbRepository;

class UserRepository extends MydbRepository
{

    public function getDatabaseIdentifier(): string
    {
        return 'db1';
    }

    public function findById(int $id): ?array
    {
        $db = $this->getDatabase();

        return $db->select("SELECT * FROM users WHERE id = " . $db->escape($id));
    }
}
// @codeCoverageIgnoreEnd
