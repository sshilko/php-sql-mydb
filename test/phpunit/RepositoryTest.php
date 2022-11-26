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

use sql\MydbRegistry;
use sql\MydbRepository;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class RepositoryTest extends includes\DatabaseTestCase
{
    public function testRegistry(): void
    {
        $registry = new MydbRegistry();
        $registry['db1'] = $this->getDefaultDb();

        $repository = new class($registry) extends MydbRepository {
            public function getDatabaseIdentifier(): string
            {
                return 'db1';
            }
        };

        self::assertSame($registry['db1'], $repository->getDatabase());
        self::assertSame('db1', $repository->getDatabaseIdentifier());
    }
}
