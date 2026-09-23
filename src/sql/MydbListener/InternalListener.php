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

namespace sql\MydbListener;

use Override;
use Psr\Log\LoggerInterface;
use sql\MydbEvent\InternalConnectionBegin;
use sql\MydbEvent\InternalConnectionEnd;
use sql\MydbEventMetadataInterface;
use sql\MydbListener;
use function in_array;
use function is_array;

/**
 * Default event listener; logs connection lifecycle events.
 *
 * Query begin/end events are intentionally not logged here; a custom listener
 * can subscribe to those through the constructor-injected logger.
 */
final class InternalListener extends MydbListener
{

    public function __construct(protected readonly ?LoggerInterface $logger = null)
    {
    }

    #[Override]
    protected function onEvent(MydbEventMetadataInterface $event): ?bool
    {
        if (in_array($event->getEventName(), [InternalConnectionBegin::class, InternalConnectionEnd::class], true)) {
            if ($this->logger) {
                $this->logger->debug(
                    'Received event: ' . $event->getEventName()
                );
            }
        }

        // @phpstan-ignore booleanOr.alwaysTrue, identical.alwaysTrue
        return is_array($event->getEventMetadata()) || null === $event->getEventMetadata();
    }
}
