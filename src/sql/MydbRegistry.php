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

namespace sql;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbRegistry
{
    /**
     * @var array<string, MydbInterface>
     */
    protected static array $instance = [];

    /**
     * @return array<string>
     *
     * @psalm-return list<string>
     */
    public static function listInstances(): array
    {
        return array_keys(static::$instance);
    }

    public static function hasInstance(string $id): bool
    {
        return isset(static::$instance[$id]);
    }

    /**
     * @throws MydbCommonException
     */
    public static function getInstance(string $id): MydbInterface
    {
        if (!isset(static::$instance[$id])) {
            throw new MydbCommonException('Instance id=' . $id . '  is not set');
        }

        return static::$instance[$id];
    }

    /**
     * @throws MydbCommonException
     */
    public static function setInstance(string $id, ?MydbInterface $instance): void
    {
        if (isset(static::$instance[$id])) {
            if (null !== $instance) {
                throw new MydbCommonException('Instance id=' . $id . '  already set');
            }
            unset(static::$instance[$id]);

            return;
        }

        if (null === $instance) {
            unset(static::$instance[$id]);
        } else {
            static::$instance[$id] = $instance;
        }
    }
}
