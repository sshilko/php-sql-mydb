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

use sql\MydbException\EventException;

abstract class MydbEvent implements MydbEventInterface, MydbEventMetadataInterface
{

    /**
     * @psalm-var array<array-key, string>|null
     */
    private ?array $eventMetadata = null;

    public function getEventMetadata(): ?array
    {
        return $this->eventMetadata;
    }

    /**
     * @throws \sql\MydbException\EventException
     * @psalm-param array<array-key, string>|null $metadata
     */
    public function notify(?array $metadata = null): void
    {
        $this->eventMetadata = $metadata;

        foreach ($this->getListeners() as $listenerInstance) {
            if ($listenerInstance instanceof MydbListenerInterface) {
                if (false === $listenerInstance->observe($this)) {
                    break;
                }
            } else {
                throw new EventException();
            }
        }
    }

    /**
     * @return array<\sql\MydbListenerInterface>
     */
    protected function getListeners(): array
    {
        return [];
    }
}
