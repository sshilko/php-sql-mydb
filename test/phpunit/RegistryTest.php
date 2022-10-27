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
use sql\MydbException\RegistryException;
use sql\MydbRegistry;
use function serialize;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class RegistryTest extends TestCase
{
    public function testRegistryEmpty1(): void
    {
        $registry = new MydbRegistry();
        self::assertNull($registry->current());
    }

    public function testRegistryEmpty2(): void
    {
        $registry = new MydbRegistry();
        self::assertNull($registry->key());
    }

    public function testRegistryEmpty3(): void
    {
        $registry = new MydbRegistry();
        $this->expectException(RegistryException::class);
        $registry->offsetGet('a');
    }

    public function testRegistrySet(): void
    {
        $registry = new MydbRegistry();
        $this->expectException(RegistryException::class);
        $registry->offsetSet('a', 'b');
    }

    public function testRegistrySerialize1(): void
    {
        $registry = new MydbRegistry();
        $this->expectException(RegistryException::class);
        serialize($registry);
    }

    public function testRegistrySerialize2(): void
    {
        $registry = new MydbRegistry();
        $this->expectException(RegistryException::class);
        $registry->serialize();
    }

    public function testRegistryUnserialize1(): void
    {
        $registry = new MydbRegistry();
        $this->expectException(RegistryException::class);
        $registry->unserialize('hello');
    }

    public function testRegistryUnserialize2(): void
    {
        $registry = new MydbRegistry();
        $this->expectException(RegistryException::class);
        $registry->__unserialize('datahere');
    }

    public function testRegistryClone(): void
    {
        $registry = new MydbRegistry();
        $this->expectException(RegistryException::class);
        clone $registry;
    }
}
