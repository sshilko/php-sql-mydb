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

namespace sql\MydbException;

use Exception;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
class TransactionException extends CommonException
{
    protected const ERROR_BEGIN = 'Cannot start db transaction';
    protected const ERROR_ROLLBACK = 'Cannot rollback db transaction';
    protected const ERROR_COMMIT = 'Cannot commit db transaction';

    public static function getBeginException(): self
    {
        return new self(static::ERROR_BEGIN);
    }

    public static function getRollbackException(): self
    {
        return new self(static::ERROR_ROLLBACK);
    }

    public static function getCommitException(): self
    {
        return new self(static::ERROR_COMMIT);
    }

}
