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

use Override;
use sql\MydbEvent;
use sql\MydbEventInterface;

class InternalEvent extends MydbEvent
{

    /**
     * @psalm-var array<\sql\MydbListenerInterface>
     */
    protected array $listeners = [];

    /**
     * @param array<array-key, mixed>|null $data
     */
    public function __construct(protected ?array $data = null)
    {
    }

    #[Override]
    public function getEventMetadata(): ?array
    {
        return $this->data;
    }

    /**
     * @psalm-param array<\sql\MydbListenerInterface> $listeners
     */
    public function setListeners(array $listeners): MydbEventInterface
    {
        $this->listeners = $listeners;

        return $this;
    }

    /**
     * @psalm-return array<\sql\MydbListenerInterface>
     */
    #[Override]
    protected function getListeners(): array
    {
        return $this->listeners;
    }
}
