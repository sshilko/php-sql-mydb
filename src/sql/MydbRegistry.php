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

use ArrayAccess;
use Countable;
use Iterator;
use Serializable;
use sql\MydbException\RegistryException;
use Traversable;
use function count;
use function current;
use function key;
use function next;
use function reset;
use function serialize;

/**
 * Singleton or registry helper to manage multiple Mydb instances
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbRegistry implements ArrayAccess, Countable, Traversable, Iterator, Serializable
{

    /**
     * @var array<string, MydbInterface>
     */
    protected array $instance = [];

    /**
     * @throws RegistryException
     */
    public function serialize(): ?string
    {
        throw new RegistryException();
    }

    /**
     * @throws RegistryException
     * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function unserialize($data): void
    {
        throw new RegistryException(serialize($data));
    }

    /**
     * Return the current element
     */
    public function current(): ?MydbInterface
    {
        $result = current($this->instance);
        if (false === $result) {
            return null;
        }

        return $result;
    }

    /**
     * Return the key of the current element
     */
    public function key(): ?string
    {
        $result = key($this->instance);
        if (null === $result) {
            return null;
        }

        return $result;
    }

    /**
     * Move forward to next element
     */
    public function next(): void
    {
        next($this->instance);
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind(): void
    {
        reset($this->instance);
    }

    /**
     * Checks if current position is valid
     * @return bool The return value will be boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid(): bool
    {
        return false !== current($this->instance);
    }

    public function count(): int
    {
        return count($this->instance);
    }

    /**
     * Whether an offset exists
     *
     * @phpcs:disable SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint
     * @param mixed $offset
     * @return bool true on success or false on failure.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->instance[$offset]);
    }

    /**
     * Offset to retrieve
     *
     * @phpcs:disable SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint
     * @param mixed $offset
     * @throws RegistryException
     */
    public function offsetGet($offset): MydbInterface
    {
        if ($this->offsetExists($offset)) {
            return $this->instance[$offset];
        }

        throw new RegistryException();
    }

    /**
     * Offset to set
     *
     * @phpcs:disable SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint
     * @param mixed $offset
     * @param MydbInterface $value
     * @throws RegistryException
     */
    public function offsetSet($offset, $value): void
    {
        if ($value instanceof MydbInterface && !$this->offsetExists($offset)) {
            $this->instance[$offset] = $value;

            return;
        }

        throw new RegistryException();
    }

    /**
     * Offset to unset
     *
     * @phpcs:disable SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        if (!$this->offsetExists($offset)) {
            return;
        }

        unset($this->instance[$offset]);
    }

    /**
     * @throws RegistryException
     */
    public function __clone()
    {
        throw new RegistryException();
    }

    /**
     * @throws RegistryException
     */
    public function __serialize(): array
    {
        throw new RegistryException();
    }

    /**
     * @throws RegistryException
     */
    public function __unserialize(string $data): void
    {
        throw new RegistryException(serialize($data));
    }
}
