<?php

declare(strict_types=1);

namespace Grimoire\Result;

use Grimoire\Database;

/**
 * Representation of filtered table grouped by some column
 */
class MultiResult extends Result
{
    /** @var Result */
    private $result;
    /** @var string */
    private $column;
    /** @var string */
    private $active;

    /** @access protected must be public because it is called from Row */
    public function __construct(string $table, Database $database, Result $result, string $column, string $active)
    {
        parent::__construct($table, $database);
        $this->result = $result;
        $this->column = $column;
        $this->active = $active;
    }

    /**
     * Specify referencing column
     */
    public function via(string $column): self
    {
        $this->column = $column;
        $this->conditions[0] = "$this->table.$column AND";
        $this->where[0] = '('
            . $this->whereIn("$this->table.$column", array_keys($this->result->getRows()))
            . ')';
        return $this;
    }

    /**
     * @return int|false
     */
    public function insertMulti(array $rows)
    {
        $args = [];
        foreach ($rows as $data) {
            if ($data instanceof \Traversable && !$data instanceof Result) {
                $data = iterator_to_array($data);
            }
            if (is_array($data)) {
                $data[$this->column] = $this->active;
            }
            $args[] = $data;
        }
        return parent::insertMulti($args);
    }

    /**
     * @return Row|false|int
     */
    public function insertUpdate(array $unique, array $insert, array $update = [])
    {
        $unique[$this->column] = $this->active;
        return parent::insertUpdate($unique, $insert, $update);
    }

    protected function single(): void
    {
        $this->where[0] = "($this->column = " . $this->database->quote($this->active) . ')';
    }

    /**
     * @return int|false
     */
    public function update(array $data)
    {
        $where = $this->where;
        $this->single();
        $return = parent::update($data);
        $this->where = $where;
        return $return;
    }

    /**
     * @return int|false
     */
    public function delete()
    {
        $where = $this->where;
        $this->single();
        $return = parent::delete();
        $this->where = $where;
        return $return;
    }

    public function select(...$columns): Result
    {
        $args = [];
        foreach ($columns as $column) {
            if (is_array($column)) {
                $args = array_merge($args, $column);
            } else {
                $args[] = $column;
            }
        }

        if (!$this->select) {
            array_unshift($args, "$this->table.$this->column");
        }
        // php 7.4 or later
        if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
            return parent::select(...$args);
        }
        // php 7.3 or earlier
        return call_user_func_array([$this, 'parent::select'], $args);
    }

    public function orderBy(...$columns): Result
    {
        if (!$this->order) { // Improve index utilization
            $subject = (isset($columns[0]) && is_array($columns[0]) ? $columns[0][0] : $columns[0]);
            $this->order[] = "$this->table.$this->column" . (preg_match('~\\bDESC$~i', $subject) ? ' DESC' : '');
        }

        // flatten the arguments to handle both array and variadic inputs
        $columns = array_merge(...array_map(function ($col) {
            return is_array($col) ? $col : [$col];
        }, $columns));

        // PHP 7.4 or later: Use splat operator for parent call
        if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
            return parent::orderBy(...$columns);
        }

        // PHP 7.3 or earlier: Use call_user_func_array for parent call
        return call_user_func_array([$this, 'parent::orderBy'], $columns);
    }

    /**
     * @deprecated use {@see orderBy()}
     */
    public function order($columns): Result
    {
        $columns = (is_array($columns) ? $columns : func_get_args());
        return $this->orderBy($columns);
    }

    public function aggregation(string $function): ?string
    {
        $join = $this->createJoins(implode(',', $this->conditions) . ",$function");
        $column = ($join ? "$this->table." : '') . $this->column;
        $query = "SELECT $function, $column FROM $this->table" . implode($join);
        if (!empty($this->where)) {
            $query .= ' WHERE ' . implode($this->where);
        }
        $query .= " GROUP BY $column";
        $aggregation = &$this->result->getAggregation($query);
        if (!isset($aggregation)) {
            $aggregation = [];
            foreach ($this->query($query, $this->parameters)->get_result() as $row) {
                $aggregation[$row[$this->column]] = $row;
            }
        }
        if (isset($aggregation[$this->active])) {
            return (string)reset($aggregation[$this->active]);
        }
        return null;
    }

    public function count(string $column = ''): int
    {
        return parent::count($column);
    }

    protected function execute(): void
    {
        if (empty($this->rows)) {
            $referencing = &$this->result->getReferencing($this->__toString());
            if (!isset($referencing)) {
                if (!$this->limit || count($this->result->getRows()) <= 1 || !empty($this->union)) {
                    parent::execute();
                } else { //! doesn't work with union
                    $result = clone $this;
                    $first = true;
                    foreach ($this->result->getRows() as $val) {
                        if ($first) {
                            $result->where[0] = "$this->column = " . $this->database->quote($val);
                            $first = false;
                        } else {
                            $clone = clone $this;
                            $clone->where[0] = "$this->column = " . $this->database->quote($val);
                            $result->union($clone);
                        }
                    }
                    $result->execute();
                    $this->rows = $result->getRows();
                }
                $referencing = [];
                foreach ($this->rows as $key => $row) {
                    $referencing[$row[$this->column]][$key] = $row;
                }
            }
            $this->data = &$referencing[$this->active];
            if (empty($this->data)) {
                $this->data = [];
            }
        }
    }
}
