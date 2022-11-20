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

use sql\MydbEventMetadataInterface;
use sql\MydbListener;
use function is_array;
use function is_null;

class InternalListener extends MydbListener
{
    protected function onEvent(MydbEventMetadataInterface $event): ?bool
    {
        return is_array($event->getEventMetadata()) || is_null($event->getEventMetadata());
    }
}
