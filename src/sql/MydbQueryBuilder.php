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
use function is_string;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
class MydbQueryBuilder implements MydbQueryBuilderInterface
{

    protected MydbMysqliEscapeStringInterface $mysqli;

    public function __construct(MydbMysqliEscapeStringInterface $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function showColumnsLike(string $table, string $column): string
    {
        return "SHOW COLUMNS FROM " . $this->escape($table, '') . " LIKE " . $this->escape($column);
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function showKeys(string $table): string
    {
        return 'SHOW KEYS FROM ' . $this->escape($table, '');
    }

    /**
     * @param array<string, (float|int|\sql\MydbExpression|string|null)> $data
     * @throws \sql\MydbException\QueryBuilderException
     * @psalm-return string
     */
    public function insertOne(array $data, string $table, string $type): string
    {
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
     */
    public function buildUpdateWhereMany(array $columnSetWhere, array $where, string $table): string
    {
        $sql = 'UPDATE ' . $table;
        /**
         * @phpcs:disable SlevomatCodingStandard.Files.LineLength.LineTooLong
         * @phpcs:disable Generic.Files.LineLength.TooLong
         * @var array<array-key, array<array-key, array<array-key, (float|int|string|\sql\MydbExpression|null)>>> $columnSetWhere
         */
        foreach ($columnSetWhere as $column => $map) {
            /**
             * @psalm-suppress InvalidOperand
             */
            $sql .= ' SET ' . $column . ' = CASE';

            foreach ($map as $newValueWhere) {
                if (!isset($newValueWhere[0], $newValueWhere[1])) {
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
            $sql .= ' WHERE ' . $this->buildWhere($where);
        }

        return $sql;
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     * @param array<string, (float|int|string|\sql\MydbExpression|null)> $update
     */
    public function buildUpdateWhere(
        array $update,
        array $whereFields,
        string $table,
        array $whereNotFields = []
    ): ?string {
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

        if ('' !== $queryUpdate && '' !== $queryWhere) {
            return 'UPDATE ' . $table . ' SET ' . $queryUpdate . ' WHERE ' . $queryWhere;
        }

        return null;
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function buildDeleteWhere(string $table, array $fields = [], array $negativeFields = []): ?string
    {
        $queryWhere = $this->buildWhere($fields, $negativeFields);

        if ('' === $queryWhere) {
            return null;
        }

        /** @lang text */
        return 'DELETE FROM ' . $this->escape($table, '') . ' WHERE ' . $queryWhere;
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     * @todo will this need real db connection to escape()? add test for all possible cases
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    public function buildWhere(array $fields = [], array $negativeFields = [], array $likeFields = []): string
    {
        $where = [];

        foreach ($fields as $field => $value) {
            /**
             * @psalm-suppress InvalidOperand
             */
            $queryPart = (string) $field;
            $isNegative = in_array($field, $negativeFields, true);
            $inNull = false;

            if (null === $value) {
                $queryPart .= ' IS ' . ($isNegative ? 'NOT ' : '') . 'NULL';
            } elseif (is_array($value)) {
                if (1 === count($value)) {
                    $qvalue = implode('', $value);
                    $queryPart .= ($isNegative ? '!' : '') . '=';
                    $queryPartEscaped = $this->escape($qvalue);
                    $queryPart .= $queryPartEscaped;
                } else {
                    $queryPart .= ($isNegative ? ' NOT' : '') . " IN (";
                    $inVals = [];

                    foreach ($value as $val) {
                        if (null === $val) {
                            $inNull = true;
                        } else {
                            $inValEscaped = $this->escape($val);
                            $inVals[] = $inValEscaped;
                        }
                    }

                    $queryPart .= implode(',', $inVals) . ')';
                }
            } else {
                $equality = ($isNegative ? '!' : '') . "=";

                if (in_array($field, $likeFields, true)) {
                    $equality = ($isNegative ? ' NOT ' : ' ') . " LIKE ";
                }

                $queryPart .= $equality;
                $queryPartEscaped = $this->escape($value);
                $queryPart .= $queryPartEscaped;
            }

            if ($inNull) {
                $queryPart = sprintf(' ( %s OR %s IS NULL ) ', $queryPart, $field);
            }

            $where[] = $queryPart;
        }

        $condition = [];

        if (count($where)) {
            $condition[] = implode(' AND ', $where);
        }

        return implode(' AND ', $condition);
    }

    /**
     * @throws \sql\MydbException\QueryBuilderException
     */
    public function buildInsertMany(array $data, array $cols, string $table, bool $ignore, string $onDuplicate): string
    {
        /**
         * @phpcs:disable SlevomatCodingStandard.Functions.DisallowArrowFunction
         * @psalm-suppress MissingClosureParamType
         */
        $mapper = function ($item): string {
            $escapedArgs = implode(
                ', ',
                array_map(function ($input) {
                        /** @phan-suppress-next-line PhanThrowTypeAbsentForCall */
                        return $this->escape($input);
                }, $item),
            );

            return '(' . $escapedArgs . ')';
        };

        $values = array_map($mapper, $data);

        $query = "INSERT " . ($ignore ? 'IGNORE ' : '') . "INTO " . $table . " ";
        $query .= "(" . implode(', ', $cols) . ") VALUES " . implode(', ', $values);

        if ('' !== $onDuplicate) {
            $query .= ' ON DUPLICATE KEY UPDATE ' . $onDuplicate;
        }

        return $query;
    }

    /**
     * @param float|int|string|\sql\MydbExpression|null $unescaped
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

        if ($unescaped instanceof MydbExpression) {
            return (string) $unescaped;
        }

        if (is_null($unescaped)) {
            return '' !== $quote ? $quote . '' . $quote : '';
        }

        if (preg_match('/^(\w)*$/', $unescaped) || preg_match('/^(\w\s)*$/', $unescaped)) {
            return '' !== $quote ? $quote . $unescaped . $quote : $unescaped;
        }

        $result = $this->mysqli->realEscapeString($unescaped);
        if (null === $result) {
            throw new QueryBuilderException((new QueryBuilderEscapeException($unescaped))->getMessage());
        }

        return '' !== $quote ? $quote . $result . $quote : $result;
    }
}
