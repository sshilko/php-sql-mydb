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
 */

declare(strict_types = 1);

namespace phpunit;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class ResourceTest extends includes\BaseTestCase
{
    public function testOpen(): void
    {
        $db = $this->getDefaultDb();
        self::assertTrue($db->open());
    }

    public function testClose(): void
    {
        $db = $this->getDefaultDb();
        self::assertNull($db->close());
    }

    public function testOpenClose(): void
    {
        $db = $this->getDefaultDb();
        self::assertTrue($db->open());
        self::assertNull($db->close());
    }
}
