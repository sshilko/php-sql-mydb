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

use sql\MydbException\QueryBuilderEscapeException;
use sql\MydbException\QueryBuilderException;
use sql\MydbMysqli\MydbMysqliEscapeStringInterface;
use function array_map;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_null;
use function is_object;
use function is_string;
use function is_subclass_of;
use function key;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;
use function trim;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbQueryBuilder implements MydbQueryBuilderInterface
{

    public function __construct(protected MydbMysqliEscapeStringInterface $mysqli)
    {
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function showColumnsLike(string $table, string $column): string
    {
        if ('' === $table || '' === $column) {
            throw new QueryBuilderException();
        }

        return "SHOW COLUMNS FROM " . $this->escape($table, '') . " LIKE " . $this->escape($column);
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function showKeys(string $table): string
    {
        if ('' === $table) {
            throw new QueryBuilderException();
        }

        return 'SHOW KEYS FROM ' . $this->escape($table, '');
    }

    /**
     * @param array<string, (float|int|\sql\MydbExpressionInterface|string|null)> $data
     * @throws \sql\MydbException\QueryBuilderException
     * @psalm-return string
     */
    public function insertOne(array $data, string $table, string $type): string
    {
        if ('' === $table || 0 === count($data)) {
            throw new QueryBuilderException();
        }

        $names = $values = [];

        foreach ($data as $name => $value) {
            $names[]  = $this->escape($name, "");
            $values[] = $this->escape($value);
        }

        return sprintf('%s INTO %s (%s) VALUES (%s)', $type, $table, implode(',', $names), implode(',', $values));
    }

    /**
     * @param array  $columnSetWhere ['col1' => [ ['current1', 'new1'], ['current2', 'new2']]
     * @param array  $where          ['col2' => 'value2', 'col3' => ['v3', 'v4']]
     * @param string $table          'mytable'
     * @throws \sql\MydbException\QueryBuilderException
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    public function buildUpdateWhereMany(array $columnSetWhere, array $where, string $table): string
    {
        if ('' === $table) {
            throw new QueryBuilderException();
        }

        $sql = 'UPDATE ' . $table;
        /**
         * @phpcs:disable Generic.Files.LineLength.TooLong
         * @var array<array-key, array<array-key, array<array-key, (float|int|string|\sql\MydbExpressionInterface|null)>>> $columnSetWhere
         */
        foreach ($columnSetWhere as $column => $updateValuesMap) {
            /**
             * @psalm-suppress DocblockTypeContradiction
             */
            if (!is_string($column) || !is_array($updateValuesMap) || 0 === count($updateValuesMap)) {
                throw new QueryBuilderException();
            }
            /**
             * @psalm-suppress InvalidOperand
             */
            $sql .= ' SET ' . $column . ' = CASE';

            foreach ($updateValuesMap as $newValueWhere) {
                if (!isset($newValueWhere[0], $newValueWhere[1]) || 2 !== count($newValueWhere)) {
                    throw new QueryBuilderException();
                }

                $escapedWhereValue = $this->escape($newValueWhere[0]);
                $escapedThenValue  = $this->escape($newValueWhere[1]);

                /**
                 * @psalm-suppress InvalidOperand
                 */
                $sql .= ' WHEN (' . $column . ' = ' . $escapedWhereValue . ')';
                $sql .= ' THEN ' . $escapedThenValue;
            }

            /**
             * @psalm-suppress InvalidOperand
             */
            $sql .= ' ELSE ' . $column;
        }

        $sql .= ' END';

        if (count($where) > 0) {
            $sql .= ' ' . $this->buildWhere($where);
        }

        return $sql;
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     * @param array<string, (float|int|string|\sql\MydbExpressionInterface|null)> $update
     */
    public function buildUpdateWhere(
        array $update,
        array $whereFields,
        string $table,
        array $whereNotFields = [],
    ): ?string {
        if ('' === $table || [] === $update || is_int(key($update))) {
            throw new QueryBuilderException();
        }

        $values = [];
        $queryWhere = $this->buildWhere($whereFields, $whereNotFields);

        foreach ($update as $field => $value) {

            /**
             * @psalm-suppress RedundantCastGivenDocblockType
             */
            $f = (string) $field . ' = ' . $this->escape($value);
            $values[] = $f;
        }

        $queryUpdate = implode(', ', $values);

        $result = 'UPDATE ' . $table . ' SET ' . $queryUpdate;
        if ('' !== $queryWhere) {
            $result .= ' ' . $queryWhere;
        }

        return $result;
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function buildDeleteWhere(string $table, array $fields = [], array $negativeFields = []): ?string
    {
        if ('' === $table || 0 === count($fields) || !is_string(key($fields))) {
            throw new QueryBuilderException();
        }

        $queryWhere = $this->buildWhere($fields, $negativeFields);

        /** @lang text */
        return 'DELETE FROM ' . $this->escape($table, '') . ' ' . $queryWhere;
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     * @todo will this need real db connection to escape()? add test for all possible cases
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    public function buildWhere(array $fields, array $negativeFields = [], array $likeFields = []): string
    {
        if ([] === $fields) {
            throw new QueryBuilderException();
        }

        $where = [];

        /**
         * @psalm-var float|int|string|array|\sql\MydbExpressionInterface|null $value
         */
        foreach ($fields as $field => $value) {
            /**
             * @psalm-suppress InvalidOperand
             */
            $queryPart = (string) $field;
            $isNegative = in_array($field, $negativeFields, true);
            $inNull = false;

            /**
             * @TODO Expression support?
             */

            if (null === $value) {
                $queryPart .= ' IS ' . ($isNegative ? 'NOT ' : '') . 'NULL';
            } elseif (is_array($value)) {
                $queryPart .= ($isNegative ? ' NOT' : '') . " IN (";
                $inVals = [];

                /**
                 * @psalm-var float|int|string|\sql\MydbExpressionInterface|null $val
                 */
                foreach ($value as $val) {
                    if (null === $val) {
                        $inNull = true;
                    } else {
                        $inValEscaped = $this->escape($val);
                        $inVals[] = $inValEscaped;
                    }
                }

                $queryPart .= implode(',', $inVals) . ')';
            } else {
                $equality = ($isNegative ? '!' : '') . "=";

                if (in_array($field, $likeFields, true)) {
                    $equality = ($isNegative ? ' NOT ' : ' ') . "LIKE ";
                }

                $queryPart .= $equality;
                $queryPartEscaped = $this->escape($value);
                $queryPart .= $queryPartEscaped;
            }

            if ($inNull) {
                $queryPart = sprintf(
                    ' (%s %s %s IS %s) ',
                    $queryPart,
                    $isNegative ? 'AND' : 'OR',
                    $field,
                    $isNegative ? 'NOT NULL' : 'NULL',
                );
            }

            $where[] = $queryPart;
        }

        $condition = [];
        $condition[] = implode(' AND ', $where);

        return 'WHERE ' . trim(implode(' AND ', $condition));
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     * @see https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
     * @param array<string> $cols
     */
    public function buildInsertMany(array $data, array $cols, string $table, bool $ignore, string $onDuplicate): string
    {
        if ('' === $table || [] === $data || [] === $cols) {
            throw new QueryBuilderException();
        }

        /**
         * @phpcs:disable SlevomatCodingStandard.Functions.DisallowArrowFunction
         * @throws \sql\MydbException\QueryBuilderException
         */
        $mapper = function (array $item): string {
            $escapedArgs = implode(
                ', ',
                /**
                 * @psalm-var float|int|string|\sql\MydbExpressionInterface|null $input
                 * @throws \sql\MydbException\QueryBuilderException
                 */
                array_map(function ($input) {
                        /**
                         * @psalm-var float|int|string|\sql\MydbExpressionInterface|null $input
                         * @phan-suppress-next-line PhanThrowTypeAbsentForCall
                         */
                        return $this->escape($input);
                }, $item),
            );

            return '(' . $escapedArgs . ')';
        };

        $values = array_map($mapper, $data);

        $query = "INSERT " . ($ignore ? 'IGNORE ' : '') . "INTO " . $table . " ";
        $query .= "(" . implode(', ', $cols) . ") VALUES " . implode(', ', $values);

        if ('' !== $onDuplicate && false === $ignore) {
            $query .= ' ON DUPLICATE KEY UPDATE ' . $onDuplicate;
        }

        return $query;
    }

    /**
     * @param float|int|string|\sql\MydbExpressionInterface|null $unescaped
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @throws \sql\MydbException\QueryBuilderException
     * @todo reduce NPathComplexity
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    public function escape($unescaped, string $quote = "'"): string
    {
        if (is_float($unescaped)) {
            return (string) $unescaped;
        }

        if (is_int($unescaped)) {
            return (string) $unescaped;
        }

        /**
         * Not quoting '0x...' decimal values
         */
        if (is_string($unescaped) && 0 === strpos($unescaped, '0x') && preg_match('/^[a-zA-Z0-9]+$/', $unescaped)) {
            if (0 === strlen($unescaped) % 2) {
                return '0x' . strtoupper(substr($unescaped, 2));
            }
        }

        if (is_object($unescaped)) {
            /**
             * PHP <=7.4
             */
            if ($unescaped instanceof MydbExpressionInterface) {
                return (string) $unescaped;
            }

            /**
             * PHP >=8.0
             * @psalm-suppress ArgumentTypeCoercion
             */
            if (is_subclass_of($unescaped, 'Stringable')) {
                return (string) $unescaped;
            }
        }

        if (is_null($unescaped)) {
            return '' !== $quote ? $quote . '' . $quote : '';
        }

        /**
         * @psalm-suppress RedundantCastGivenDocblockType
         */
        if (preg_match('/^(\w)*$/', (string) $unescaped) || preg_match('/^(\w\s)*$/', (string) $unescaped)) {
            return '' !== $quote ? $quote . ((string) $unescaped) . $quote : (string) $unescaped;
        }

        /**
         * @psalm-suppress RedundantCastGivenDocblockType
         */
        $result = $this->mysqli->realEscapeString((string) $unescaped);
        if (null === $result) {
            /**
             * @psalm-suppress RedundantCastGivenDocblockType
             */
            throw new QueryBuilderException((new QueryBuilderEscapeException((string) $unescaped))->getMessage());
        }

        return '' !== $quote ? $quote . $result . $quote : $result;
    }
}
