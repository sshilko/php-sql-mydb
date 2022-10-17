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

namespace sql\MydbInterface;

/**
 * An atomic DDL statement combines the data dictionary updates, storage engine operations,
 * and binary log writes associated with a DDL operation into a single, atomic operation.
 * The operation is either committed, with applicable changes persisted to the data dictionary,
 * storage engine, and binary log, or is rolled back, even if the server halts during the operation.
 *
 * @see https://dev.mysql.com/doc/refman/8.0/en/sql-data-definition-statements.html
 * @author Sergei Shilko <contact@sshilko.com>
 * @package sshilko/php-sql-mydb
 * @see https://github.com/sshilko/php-sql-mydb
 */
interface DataDefinitionStatementsSInterface
{
    public function dds(string $statement): void;
}
