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
use sql\MydbEvent;
use sql\MydbEventMetadataInterface;
use sql\MydbException\EventException;
use sql\MydbListener;
use stdClass;
use function serialize;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MydbEventTest extends TestCase
{
    public function testMydbEventListenerBreak(): void
    {
        $class = new class extends MydbEvent {

            public function getEventMetadata(): ?array
            {
                return [];
            }

            protected function getListeners(): array
            {
                return [new class extends MydbListener {

                    protected function onEvent(MydbEventMetadataInterface $event): ?bool
                    {
                        serialize($event->getEventMetadata());

                        return false;
                    }
                }];
            }
        };

        self::assertNull((new $class())->notify());
    }

    public function testMydbEventListenerException(): void
    {
        $class = new class extends MydbEvent {

            public function getEventMetadata(): ?array
            {
                return [];
            }

            protected function getListeners(): array
            {
                return [new stdClass()];
            }
        };

        $this->expectException(EventException::class);
        (new $class())->notify();
    }

    public function testMydbInternalEventSuccess(): void
    {
        $event = new MydbEvent\InternalEvent();
        self::assertNull($event->getEventMetadata());

        $listener = new MydbListener\InternalListener();
        self::assertTrue($listener->observe($event));
    }
}
