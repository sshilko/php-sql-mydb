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
     * @psalm-return array<array-key, mixed>|null
     */
    abstract public function getEventMetadata(): ?array;

    /**
     * @throws \sql\MydbException\EventException
     */
    public function notify(): void
    {
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
    abstract protected function getListeners(): array;
}
