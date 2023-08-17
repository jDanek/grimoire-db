<?php

declare(strict_types=1);

namespace Grimoire\Result;

use Psr\SimpleCache\InvalidArgumentException;

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
    public function __construct(string $table, Result $result, string $column, string $active)
    {
        parent::__construct($table, $result->database);
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
     * @throws \ReflectionException
     */
    public function insertUpdate(array $unique, array $insert, array $update = [])
    {
        $unique[$this->column] = $this->active;
        return parent::insertUpdate($unique, $insert, $update);
    }

    protected function single(): void
    {
        $this->where[0] = "($this->column = " . $this->quote($this->active) . ')';
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

    public function select($columns): Result
    {
        $args = func_get_args();
        if (!$this->select) {
            array_unshift($args, "$this->table.$this->column");
        }
        return call_user_func_array([$this, 'parent::select'], $args);
    }

    public function order($columns): Result
    {
        if (!$this->order) { // improve index utilization
            $this->order[] = "$this->table.$this->column" . (preg_match('~\\bDESC$~i', $columns) ? ' DESC' : '');
        }
        $args = func_get_args();
        return call_user_func_array([$this, 'parent::order'], $args);
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
        $aggregation = &$this->result->aggregation[$query];
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

    /**
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     */
    protected function execute(): void
    {
        if (empty($this->rows)) {
            $referencing = &$this->result->referencing[$this->__toString()];
            if (!isset($referencing)) {
                if (!$this->limit || count($this->result->getRows()) <= 1 || !empty($this->union)) {
                    parent::execute();
                } else { //! doesn't work with union
                    $result = clone $this;
                    $first = true;
                    foreach ($this->result->getRows() as $val) {
                        if ($first) {
                            $result->where[0] = "$this->column = " . $this->quote($val);
                            $first = false;
                        } else {
                            $clone = clone $this;
                            $clone->where[0] = "$this->column = " . $this->quote($val);
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
