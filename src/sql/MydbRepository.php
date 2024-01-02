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

abstract class MydbRepository implements MydbRepositoryInterface
{

    abstract public function getDatabaseIdentifier(): string;

    public function __construct(private MydbRegistry $registry)
    {
    }

    public function getDatabase(): MydbInterface
    {
        return $this->registry[$this->getDatabaseIdentifier()];
    }
}
