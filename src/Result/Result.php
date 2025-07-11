<?php

declare(strict_types=1);

namespace Grimoire\Result;

use Doctrine\Instantiator\Exception\UnexpectedValueException;
use Grimoire\Database;
use Grimoire\Literal;
use Grimoire\Util\ThenForeachHelper;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Filtered table representation
 *
 * @method Result and (mixed $condition, mixed $parameters = []) Add AND condition
 * @method Result or (mixed $condition, mixed $parameters = []) Add OR condition
 */
class Result implements \Iterator, \ArrayAccess, \Countable, \JsonSerializable
{
    /** @var bool */
    protected $single;

    /** @var array */
    protected $select = [];
    /** @var array */
    protected $conditions = [];
    /** @var array */
    protected $where = [];
    /** @var array */
    protected $parameters = [];
    /** @var array */
    protected $order = [];

    /** @var int|null */
    protected $limit = null;
    /** @var int|null */
    protected $offset = null;
    /** @var string */
    protected $group = '';
    /** @var string */
    protected $having = '';
    /** @var bool */
    protected $lock = null;

    /** @var array */
    protected $union = [];
    /** @var array */
    protected $unionOrder = [];
    /** @var int|null */
    protected $unionLimit = null;
    /** @var int|null */
    protected $unionOffset = null;

    /** @var array<Row> */
    protected $data = [];
    /** @var array */
    protected $referencing = [];
    /** @var array */
    protected $aggregation = [];
    /** @var array|string */
    protected $accessed;
    /** @var array|string */
    protected $access;
    /** @var array */
    protected $keys = [];

    /** @var string */
    protected $table;
    /** @var Database */
    protected $database;
    /** @var string */
    protected $primary;
    /** @var array */
    protected $rows = [];
    /** @var array<Result> */
    protected $referenced = [];

    /**
     * Create table result
     *
     * @param bool $single single row
     */
    public function __construct(string $table, Database $database, bool $single = false)
    {
        $this->table = $table;
        $this->database = $database;
        $this->single = $single;
        $this->primary = $database->getStructure()->getPrimary($table);
    }

    /**
     * Save data to cache and empty result
     */
    public function __destruct()
    {
        if (!$this->select && !empty($this->rows)) {
            $access = $this->access;
            if (is_array($access)) {
                $access = array_filter($access);
            }
            try {
                $this->database->getCache()->set("$this->table;" . implode(',', $this->conditions), $access);
            } catch (InvalidArgumentException $e) {
                error_log('Cache set error: ' . $e->getMessage());
            }
        }
        $this->rows = [];
        unset($this->data);
        $this->data = [];
    }

    protected function limitString(?int $limit, ?int $offset = null): string
    {
        if (isset($limit)) {
            return " LIMIT $limit" . (isset($offset) ? " OFFSET $offset" : '');
        }
        return '';
    }

    protected function removeExtraDots(string $expression): string
    {
        return preg_replace(
            '~(?:\\b[a-z_][a-z0-9_.:]*[.:])?([a-z_][a-z0-9_]*)[.:]([a-z_*])~i',
            '\\1.\\2',
            $expression
        ); // rewrite tab1.tab2.col
    }

    protected function whereString(): string
    {
        $return = '';
        if (!empty($this->group)) {
            $return .= " GROUP BY $this->group";
        }
        if (!empty($this->having)) {
            $return .= " HAVING $this->having";
        }
        if (!empty($this->order)) {
            $return .= ' ORDER BY ' . implode(', ', $this->order);
        }
        $return = $this->removeExtraDots($return);

        $where = $this->where;
        if (!empty($where)) {
            $return = ' WHERE ' . implode($where) . $return;
        }

        $return .= $this->limitString($this->limit, $this->offset);
        if (is_bool($this->lock)) {
            $return .= ($this->lock ? ' FOR UPDATE' : ' LOCK IN SHARE MODE');
        }
        return $return;
    }

    protected function createJoins(string $val): array
    {
        $return = [];
        preg_match_all('~\\b([a-z_][a-z0-9_.:]*[.:])[a-z_*]~i', $val, $matches);
        foreach ($matches[1] as $names) {
            $parent = $this->table;
            if ($names !== "$parent.") { // case-sensitive
                preg_match_all('~\\b([a-z_][a-z0-9_]*)([.:])~i', $names, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    [, $name, $delimiter] = $match;

                    $structure = $this->database->getStructure();

                    $table = $structure->getReferencedTable($name, $parent);
                    $column = ($delimiter === ':'
                        ? $structure->getPrimary($parent)
                        : $structure->getReferencedColumn($name, $parent)
                    );
                    $primary = ($delimiter === ':'
                        ? $structure->getReferencedColumn($parent, $table)
                        : $structure->getPrimary($table)
                    );
                    $return[$name] = " LEFT JOIN $table" . ($table != $name ? " AS $name" : '') . " ON $parent.$column = $name.$primary"; // should use alias if the table is used on more places
                    $parent = $name;
                }
            }
        }
        return $return;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimary(): string
    {
        return $this->primary;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function &getAggregation(?string $query): ?array
    {
        return $this->aggregation[$query];
    }

    public function &getReferencing(?string $key): ?array
    {
        return $this->referencing[$key];
    }

    public function &getReferenced(?string $name): ?Result
    {
        return $this->referenced[$name];
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Returns row specified by primary key.
     *
     * @param mixed $key
     * @throws \Throwable
     */
    public function get($key, bool $single = true): ?Row
    {
        $this->single = $single;
        $result = $this->offsetGet($key);
        return $result instanceof Row ? $result : null;
    }

    /**
     * Get SQL query
     */
    public function __toString(): string
    {
        $return = 'SELECT ';
        $join = $this->createJoins(
            implode(',', $this->conditions) . ',' . implode(
                ',',
                $this->select
            ) . ",$this->group,$this->having," . implode(',', $this->order)
        );
        if (empty($this->rows) && !is_string($this->accessed)) {
            try {
                $this->accessed = $this->database->getCache()->get(
                    "$this->table;" . implode(',', $this->conditions),
                    []
                );
            } catch (InvalidArgumentException $e) {
                error_log('Cache get error: ' . $e->getMessage());
                $this->accessed = [];
            }

            $this->access = $this->accessed;
        }
        if (!empty($this->select)) {
            $return .= $this->removeExtraDots(implode(', ', $this->select));
        } elseif (!empty($this->accessed)) {
            $return .= ($join ? "$this->table." : '') . implode(
                    ', ' . ($join ? "$this->table." : ''),
                    array_keys($this->accessed)
                );
        } else {
            $return .= ($join ? "$this->table." : '') . '*';
        }
        $return .= " FROM $this->table" . implode($join) . $this->whereString();
        if (!empty($this->union)) {
            $return = "($return)" . implode($this->union);
            if (!empty($this->unionOrder)) {
                $return .= ' ORDER BY ' . implode(', ', $this->unionOrder);
            }
            $return .= $this->limitString($this->unionLimit, $this->unionOffset);
        }
        return $return;
    }

    /**
     * @return false|\mysqli_stmt
     */
    protected function query(string $query, array $parameters = [])
    {
        $dbConfig = $this->database->getConfig();

        if ($dbConfig->getDebug()) {
            if (!is_callable($dbConfig->getDebug())) {
                $debug = "$query;";
                if (!empty($parameters)) {
                    $debug .= ' -- ' . implode(', ', array_map([$this->database->getStringFormatter(), 'quote'], $parameters));
                }
                $pattern = '(^' . preg_quote(dirname(__FILE__)) . '(\\.php$|[/\\\\]))'; // can be static
                foreach (debug_backtrace() as $backtrace) {
                    // stop on first file outside Grimoire source codes
                    if (isset($backtrace['file']) && !preg_match($pattern, $backtrace['file'])) {
                        error_log("$backtrace[file]:$backtrace[line]:$debug\n");
                        break;
                    }
                }
                //error_log("$backtrace[file]:$backtrace[line]:$debug\n", 0);
            } elseif (call_user_func($dbConfig->getDebug(), $query, $parameters) === false) {
                return false;
            }
        }

        $return = $dbConfig->getConnection()->prepare($query);
        if ($return !== false) {
            $paramsCount = count($parameters);
            if ($paramsCount > 0) {
                $bindParams = array_map([$this->database->getStringFormatter(), 'formatValue'], $parameters);
                $types = str_repeat('s', $paramsCount);
                $bindParams = array_values($bindParams); // mysqli does not support named parameters
                $return->bind_param($types, ...$bindParams);
            }
            if ($return->execute() === false) {
                $return = false;
            }
        } else {
            $return = false;
        }

        if ($dbConfig->getDebugTimer()) {
            call_user_func($dbConfig->getDebugTimer());
        }
        return $return;
    }

    /**
     * Shortcut for call_user_func_array([$this, 'insert'], $rows)
     *
     * @param array $rows
     * @return int|false number of affected rows or false in case of an error
     */
    public function insertMulti(array $rows)
    {
        if ($this->database->getConfig()->isFreeze()) {
            return false;
        }
        if (!$rows) {
            return 0;
        }
        $data = reset($rows);
        $parameters = [];
        if ($data instanceof Result) {
            $parameters = $data->parameters; //! other parameters
            $data = (string)$data;
        } elseif ($data instanceof \Traversable) {
            $data = iterator_to_array($data);
        }
        $insert = $data;
        if (is_array($data)) {
            $values = [];
            foreach ($rows as $value) {
                if ($value instanceof \Traversable) {
                    $value = iterator_to_array($value);
                }
                $values[] = $this->database->quote($value);
                foreach ($value as $val) {
                    if ($val instanceof Literal && $val->getParameters()) {
                        $parameters = array_merge($parameters, $val->getParameters());
                    }
                }
            }

            $insert = !empty($data)
                ? '(' . implode(', ', array_keys($data)) . ') VALUES ' . implode(', ', $values)
                : 'DEFAULT VALUES';
        }
        // requires empty $this->parameters
        $return = $this->query("INSERT INTO $this->table $insert", $parameters);
        if ($return === false) {
            return false;
        }
        $this->rows = [];

        $count = $return->affected_rows;
        $return->close();

        return $count;
    }

    /**
     * Insert row in a table
     *
     * @param array|\Traversable|Result|string ...$data [$column => $value] | Traversable for single row insert or Result | string for INSERT ... SELECT
     * @return Row|false|int inserted Row or false in case of an error or number of affected rows for INSERT ... SELECT
     */
    public function insert(...$rows)
    {
        $return = $this->insertMulti($rows);

        if (!$return) {
            return false;
        }

        // ensure that $rows[0] contains the data to create a new instance of the row
        $data = $rows[0] ?? [];

        if (!is_array($data)) {
            return $return;
        }

        if (!isset($data[$this->primary])) {
            $id = $this->database->getConnection()->insert_id;
            if ($id) {
                $data[$this->primary] = $id;
            }
        }

        // create new row instance
        /** @var Row $rowClass */
        $rowClass = $this->database->getConfig()->getRowClass();
        return new $rowClass($data, $this, $this->database);
    }

    /**
     * Update all rows in result set
     *
     * @param array $data [column => value, ...]
     * @return int|false number of affected rows or false in case of an error
     */
    public function update(array $data)
    {
        if ($this->database->getConfig()->isFreeze()) {
            return false;
        }
        if (empty($data)) {
            return 0;
        }
        $values = [];
        $parameters = [];
        foreach ($data as $key => $val) {
            // doesn't use binding because $this->parameters can be filled by ?
            $values[] = "$key = " . $this->database->quote($val);
            if ($val instanceof Literal && $val->getParameters()) {
                $parameters = array_merge($parameters, $val->getParameters());
            }
        }
        if (!empty($this->parameters)) {
            $parameters = array_merge($parameters, $this->parameters);
        }
        $return = $this->query("UPDATE $this->table SET " . implode(', ', $values) . $this->whereString(), $parameters);
        if ($return === false) {
            return false;
        }
        $count = $return->affected_rows;
        $return->close();

        return $count;
    }

    /**
     * Insert row or update if it already exists
     *
     * @param array $unique [column => value]
     * @param array $insert [column => value]
     * @param array $update [column => value], empty array means use $insert
     * @return int|false number of affected rows or false in case of an error
     */
    public function insertUpdate(array $unique, array $insert, array $update = [])
    {
        if (empty($update)) {
            $update = $insert;
        }
        $insert = $unique + $insert;
        $values = '(' . implode(', ', array_keys($insert)) . ') VALUES ' . $this->database->quote($insert);

        $set = [];
        if (empty($update)) {
            $update = $unique;
        }
        foreach ($update as $key => $val) {
            $set[] = "$key = " . $this->database->quote($val);
        }
        return $this->insert("$values ON DUPLICATE KEY UPDATE " . implode(', ', $set));
    }

    /**
     * Get last insert ID
     */
    public function insertId(): int
    {
        return (int)$this->database->getConnection()->insert_id;
    }

    /**
     * Delete all rows in result set
     * @return int|false number of affected rows or false in case of an error
     */
    public function delete()
    {
        if ($this->database->getConfig()->isFreeze()) {
            return false;
        }
        $return = $this->query("DELETE FROM $this->table" . $this->whereString(), $this->parameters);
        if ($return === false) {
            return false;
        }
        $count = $return->affected_rows;
        $return->close();

        return $count;
    }

    /**
     * Add select clause, more calls appends to the end
     *
     * @param string|array $columns for example ['column1', 'column2'], 'column, MD5(column) AS column_md5', empty string to reset previously set columns
     * @return Result
     */
    public function select(...$columns): self
    {
        $this->__destruct();

        $columns = ($columns === ['*']) ? [] : $columns; // remove '*'
        if (!empty($columns)) {
            foreach ($columns as $column) {
                if (is_array($column)) {
                    $this->select = array_merge($this->select, $column);
                } else {
                    $this->select[] = $column;
                }
            }
        } else {
            $this->select = [];
        }
        return $this;
    }

    /**
     * Add where condition, more calls appends with AND
     *
     * @param mixed $condition string possibly containing ? or [condition => parameters, ...]
     * @param mixed $parameters array accepted by \mysqli_stmt::execute or a scalar value
     */
    public function where($condition, $parameters = []): self
    {
        $args = func_get_args();
        return $this->whereOperator('AND', $args);
    }

    protected function whereOperator(string $operator, array $args): self
    {
        $condition = $args[0];
        $parameters = (count($args) > 1 ? $args[1] : []);
        if (is_array($condition)) { // where(['column1' => 1, 'column2 > ?' => 2])
            foreach ($condition as $key => $val) {
                $this->where($key, $val);
            }
            return $this;
        }
        $this->__destruct();
        $this->conditions[] = "$operator $condition";
        $condition = $this->removeExtraDots((string)$condition);
        if (count($args) != 2 || strpbrk($condition, '?')) { // where('column < ? OR column > ?', [1, 2])
            // check for unsupported negation
            if (strpos($condition, '!') !== false && strpos($condition, '!=') === false) {
                throw new \UnexpectedValueException('Negation in the form \'' . $condition . '\' is not supported.');
            }

            if (count($args) != 2 || !is_array($parameters)) { // where('column < ? OR column > ?', 1, 2)
                $parameters = array_slice($args, 1);
            }
            $this->parameters = array_merge($this->parameters, $parameters);
        } elseif ($parameters === null) { // where('column', null)
            [$condition, $negated] = $this->negateHandler($condition);
            $condition .= ' IS' . ($negated ? ' NOT' : '') . ' NULL';
        } elseif ($parameters instanceof Result) { // where('column', $db->$table())
            $clone = clone $parameters;
            if (empty($clone->select)) {
                $clone->select($this->database->getStructure()->getPrimary($clone->table));
            }

            $in = [];
            foreach ($clone as $row) {
                $row = array_values(iterator_to_array($row));
                if ($clone instanceof MultiResult && count($row) > 1) {
                    array_shift($row);
                }
                $in[] = $this->database->quote((count($row) === 1 ? $row[0] : $row));
            }
            if (!empty($in)) {
                [$condition, $negated] = $this->negateHandler($condition);
                $condition .= ($negated ? ' NOT' : '') . ' IN (' . implode(', ', $in) . ')';
            } else {
                $condition = "($condition) IS NOT NULL AND $condition IS NULL"; // $condition = 'NOT id'
            }
        } elseif (!is_array($parameters)) { // where('column', 'x')
            [$condition, $negated] = $this->negateHandler($condition);
            $condition .= ($negated ? ' != ' : ' = ') . $this->database->quote($parameters);
        } else { // where('column', [1, 2])
            $condition = $this->whereIn($condition, $parameters);
        }
        $this->where[] = preg_match('~^\)+$~', $condition)
            ? $condition
            : ($this->where ? " $operator " : '') . "($condition)";

        return $this;
    }

    protected function whereIn(string $condition, array $parameters): string
    {
        if (empty($parameters)) {
            $condition = "($condition) IS NOT NULL AND $condition IS NULL";
        } else {
            [$condition, $negated] = $this->negateHandler($condition);
            $column = $condition;
            $condition .= ($negated ? ' NOT' : '') . ' IN ' . $this->database->quote($parameters);
            $nulls = array_filter($parameters, 'is_null');
            if (!empty($nulls)) {
                $not = ($negated ? ' NOT' : '');
                $condition = "$condition OR $column IS$not NULL";
            }
        }
        return $condition;
    }

    /**
     * @param string $column
     * @return array{
     *       column: string,
     *       negated: bool
     * }
     */
    protected function negateHandler(string $column): array
    {
        $negated = $column[0] === '!';
        $column = ltrim($column, '!');
        return [
            $column,
            $negated,
        ];
    }

    public function __call(string $name, array $args): self
    {
        $operator = strtoupper($name);
        switch ($operator) {
            case 'AND':
            case 'OR':
                return $this->whereOperator($operator, $args);
        }
        trigger_error("Call to undefined method Result::$name()", E_USER_ERROR);
    }

    /**
     * Shortcut for where()
     * @param string $where
     * @param mixed $parameters
     * @param ... $parameters
     * @return Result fluent interface
     */
    public function __invoke(string $where, $parameters = []): self
    {
        $args = func_get_args();
        return $this->whereOperator('AND', $args);
    }

    /**
     * Add order clause, more calls appends to the end
     *
     * @param string|array ...$columns 'column1, column2 DESC' or ['column1', 'column2 DESC'], empty string to reset previous order
     */
    public function orderBy(...$columns): self
    {
        $this->rows = [];

        // Flatten the arguments to handle both variadic and array input
        $columns = array_merge(...array_map(function ($col) {
            return is_array($col) ? $col : [$col];
        }, $columns));

        if (!empty($columns)) {
            foreach ($columns as $column) {
                if (!empty($this->union)) {
                    $this->unionOrder[] = $column;
                } else {
                    $this->order[] = $column;
                }
            }
        } elseif (!empty($this->union)) {
            $this->unionOrder = [];
        } else {
            $this->order = [];
        }

        return $this;
    }

    /**
     * Add order clause, more calls appends to the end
     *
     * @param string|array $columns 'column1, column2 DESC' or ['column1', 'column2 DESC'], empty string to reset previous order
     * @param ... $columns
     * @deprecated use {@see orderBy()}
     */
    public function order($columns): self
    {
        $columns = (is_array($columns) ? $columns : func_get_args());
        return $this->orderBy($columns);
    }

    /**
     * Set limit clause, more calls rewrite old values
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        $this->rows = [];
        if (!empty($this->union)) {
            $this->unionLimit = +$limit;
            $this->unionOffset = +$offset;
        } else {
            $this->limit = +$limit;
            $this->offset = +$offset;
        }
        return $this;
    }

    /**
     * Sets offset using page number, more calls rewrite old values.
     * @param array|null $totals if the value $totals is specified, then it is populated with the results of the count. ['total_items' => int, 'total_pages' => int]
     * @throws \Throwable
     */
    public function page(int $page, int $itemsPerPage, ?array &$totals = null): self
    {
        if (func_num_args() > 2) {
            $totalItems = $this->count('*');
            $totalPages = (int)ceil($totalItems / $itemsPerPage);
            $totals = [
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ];
        }

        if ($page < 1) {
            $itemsPerPage = 0;
        }

        return $this->limit($itemsPerPage, ($page - 1) * $itemsPerPage);
    }

    /**
     * Set group clause, more calls rewrite old values
     */
    public function group(string $columns, string $having = ''): self
    {
        $this->__destruct();
        $this->group = $columns;
        $this->having = $having;
        return $this;
    }

    /**
     * Set select FOR UPDATE or LOCK IN SHARE MODE
     */
    public function lock(bool $exclusive = true): self
    {
        $this->lock = $exclusive;
        return $this;
    }

    public function union(Result $result, bool $all = false): self
    {
        $this->union[] = ' UNION ' . ($all ? 'ALL ' : '') . "($result)";
        $this->parameters = array_merge($this->parameters, $result->getParameters());
        return $this;
    }

    /**
     * Execute aggregation function
     */
    public function aggregation(string $function): ?string
    {
        $join = $this->createJoins(implode(',', $this->conditions) . ",$function");
        $query = "SELECT $function FROM $this->table" . implode($join);
        if (!empty($this->where)) {
            $query .= ' WHERE ' . implode($this->where);
        }
        $result = $this->query($query, $this->parameters)->get_result()->fetch_assoc();
        if ($result !== false) {
            $arr = array_values($result);
            return (string)reset($arr);
        }
        return null;
    }

    /**
     * Count number of rows
     * @throws \Throwable
     */
    public function count(string $column = ''): int
    {
        if (empty($column)) {
            $this->execute();
            return count($this->data);
        }
        return (int)$this->aggregation("COUNT($column)");
    }

    /**
     * Return minimum value from a column
     */
    public function min(string $column): int
    {
        return (int)$this->aggregation("MIN($column)");
    }

    /**
     * Return maximum value from a column
     */
    public function max(string $column): int
    {
        return (int)$this->aggregation("MAX($column)");
    }

    /**
     * Return sum of values in a column
     */
    public function sum(string $column): int
    {
        return (int)$this->aggregation("SUM($column)");
    }

    /**
     * Execute the built query
     * @throws \Throwable
     */
    protected function execute(): void
    {
        if (empty($this->rows)) {
            $result = false;
            $exception = null;
            $parameters = [];
            foreach (
                array_merge(
                    $this->select,
                    [$this, $this->group, $this->having],
                    $this->order,
                    $this->unionOrder
                ) as $val
            ) {
                if (($val instanceof Literal || $val instanceof self) && $val->getParameters()) {
                    $parameters = array_merge($parameters, $val->getParameters());
                }
            }
            try {
                $result = $this->query($this->__toString(), $parameters);
            } catch (\Throwable $exception) {
                // handled later
            }
            if ($result === false) {
                if (!$this->select && $this->accessed) {
                    $this->accessed = '';
                    $this->access = [];
                    $result = $this->query($this->__toString(), $parameters);
                } elseif ($exception) {
                    throw $exception;
                }
            }
            $this->rows = [];
            if ($result !== false) {
                foreach ($result->get_result() as $key => $row) {
                    if (isset($row[$this->primary])) {
                        $key = $row[$this->primary];
                        if (!is_string($this->access)) {
                            $this->access[$this->primary] = true;
                        }
                    }

                    // create new row instance
                    /** @var Row $rowClass */
                    $rowClass = $this->database->getConfig()->getRowClass();
                    $this->rows[$key] = new $rowClass($row, $this, $this->database);
                }
            }
            $this->data = $this->rows;
        }
    }

    /**
     * Fetch next row of result
     *
     * @param string $column column name to return or an empty string for the whole row
     * @return string|null|Row|false string or null with $column, Row without $column, false if there is no row
     * @throws \Throwable
     */
    public function fetch(string $column = '')
    {
        // no $this->select($column) because next calls can access different columns
        $this->execute();
        $return = current($this->data);
        next($this->data);
        if ($return !== false && $column !== '') {
            return $return[$column];
        }
        return $return;
    }

    /**
     * Fetch all rows as associative array
     *
     * @param string $value column name used for an array value or an empty string for the whole row
     */
    public function fetchPairs(string $key, string $value = ''): array
    {
        $return = [];
        $clone = clone $this;
        if ($value !== '') {
            $clone->select = [];
            $clone->select("$key, $value"); // MultiResult adds its column
        } elseif (!empty($clone->select)) {
            array_unshift($clone->select, $key);
        } else {
            $clone->select = ["$key, $this->table.*"];
        }
        foreach ($clone as $row) {
            $values = array_values(iterator_to_array($row));
            if ($value !== '' && $clone instanceof MultiResult) {
                array_shift($values);
            }
            $return[(string)$values[0]] = ($value !== ''
                ? $values[(array_key_exists(1, $values) ? 1 : 0)]
                : $row
            ); // isset($values[1]) - fetchPairs('id', 'id')
        }
        return $return;
    }

    /**
     * Pass result to callback
     *
     * @param callback $callback with signature (Result $result)
     */
    function then(callable $callback): void
    {
        Database::then($callback, $this);
    }

    /**
     * Pass each row to callback
     *
     * @param callback $callback with signature (Row $row, $id)
     */
    public function thenForeach(callable $callback): void
    {
        $foreach = new ThenForeachHelper($callback);
        Database::then([$foreach, '__invoke'], $this);
    }

    public function access(string $key, bool $delete = false): bool
    {
        if ($delete) {
            if (is_array($this->access)) {
                $this->access[$key] = false;
            }
            return false;
        }

        if (empty($key)) {
            $this->access = '';
        } elseif (!is_string($this->access)) {
            $this->access[$key] = true;
        }

        if (empty($this->select) && !empty($this->accessed) && (empty($key) || !isset($this->accessed[$key]))) {
            $this->accessed = '';
            $this->rows = [];
            return true;
        }

        return false;
    }

    protected function single(): void
    {
    }

    // Iterator implementation (not IteratorAggregate because $this->data can be changed during iteration)

    /**
     * @throws \Throwable
     */
    public function rewind(): void
    {
        $this->execute();
        $this->keys = array_keys($this->data);
        reset($this->keys);
    }

    /**
     * @return Row
     */
    public function current(): Row
    {
        return $this->data[current($this->keys)];
    }

    /**
     * @return string row ID
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return current($this->keys);
    }

    public function next(): void
    {
        next($this->keys);
    }

    public function valid(): bool
    {
        return current($this->keys) !== false;
    }

    // ArrayAccess implementation

    /**
     * Test if row exists
     * @param string|array $key row ID or array for where conditions
     * @throws \Throwable
     */
    public function offsetExists($key): bool
    {
        $row = $this->offsetGet($key);
        return isset($row);
    }

    /**
     * Get specified row
     * @param string|array $key row ID or array for where conditions
     * @return Row|null
     * @throws \Throwable
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        if ($this->single && empty($this->data)) {
            $clone = clone $this;
            $clone->single = false; // execute as normal query
            if (is_array($key)) {
                $clone->where($key)->limit(1);
            } else {
                $clone->where($this->primary, $key);
            }
            $return = $clone->fetch();
            if ($return) {
                return $return;
            }
        } else {
            $this->execute();
            if (is_array($key)) {
                foreach ($this->data as $row) {
                    foreach ($key as $k => $v) {
                        if (isset($v) && $row[$k] !== null ? $row[$k] != $v : $row[$k] !== $v) {
                            continue 2;
                        }
                    }
                    return $row;
                }
            } elseif (isset($this->data[$key])) {
                return $this->data[$key];
            }
        }
        return null;
    }

    /**
     * Mimic row
     *
     * @param string $key row ID
     * @param Row $value
     * @throws \Throwable
     */
    public function offsetSet($key, $value): void
    {
        $this->execute();
        $this->data[$key] = $value;
    }

    /**
     * Remove row from result set
     *
     * @param string $key row ID
     * @throws \Throwable
     */
    public function offsetUnset($key): void
    {
        $this->execute();
        unset($this->data[$key]);
    }

    // JsonSerializable implementation

    /**
     * @throws \Throwable
     */
    public function jsonSerialize(): array
    {
        $this->execute();
        if ($this->database->getConfig()->getJsonAsArray()) {
            return array_values($this->data);
        } else {
            return $this->data;
        }
    }
}
