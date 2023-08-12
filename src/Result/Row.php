<?php

namespace Grimoire\Result;

use Grimoire\Database;

/**
 * Single row representation
 */
class Row implements \IteratorAggregate, \ArrayAccess, \Countable, \JsonSerializable
{
    /** @var array */
    private $modified = [];
    /** @var array */
    protected $row;
    /** @var Result */
    protected $result;
    /** @var Database */
    protected $database;
    /** @var mixed */
    protected $primary;

    public function __construct(array $row, Result $result, Database $database)
    {
        $this->row = $row;
        $this->result = $result;
        $this->database = $database;
        if (array_key_exists($result->getPrimary(), $row)) {
            $this->primary = $row[$result->getPrimary()];
        }
    }

    /**
     * Get primary key value
     */
    public function __toString(): string
    {
        return $this[$this->result->getPrimary()];
    }

    /**
     * Get referenced row
     */
    public function ref(string $name): ?Row
    {
        return $this->__get($name);
    }

    /**
     * Get referenced row
     */
    public function __get(string $name): ?Row
    {
        $column = $this->database->getConfig()->getStructure()->getReferencedColumn($name, $this->result->getTable());

        $ref = $this->result->getReferenced($name);
        $referenced = &$ref; // only variables can be passed by reference
        if (!isset($referenced)) {
            $keys = [];
            foreach ($this->result->getRows() as $row) {
                if ($row[$column] !== null) {
                    $keys[$row[$column]] = null;
                }
            }
            if ($keys) {
                $table = $this->database->getConfig()->getStructure()->getReferencedTable(
                    $name,
                    $this->result->getTable()
                );
                $referenced = new Result($table, $this->database);
                $referenced->where(
                    "$table." . $this->database->getConfig()->getStructure()->getPrimary($table),
                    array_keys($keys)
                );
            } else {
                $referenced = [];
            }
        }
        // referenced row may not exist
        return $referenced[$this[$column]] ?? null;
    }

    /**
     * Test if referenced row exists
     */
    public function __isset(string $name): bool
    {
        return ($this->__get($name) !== null);
    }

    /**
     * Store referenced value
     */
    public function __set(string $name, Row $value = null): void
    {
        $column = $this->database->getConfig()->getStructure()->getReferencedColumn($name, $this->result->getTable());
        $this[$column] = $value;
    }

    /**
     * Remove referenced column from data
     */
    public function __unset(string $name): void
    {
        $column = $this->database->getConfig()->getStructure()->getReferencedColumn($name, $this->result->getTable());
        unset($this[$column]);
    }

    /**
     * Get referencing rows
     *
     * @param string $relatedTableName table name
     * @param array $where (['condition', ['values']])
     */
    public function __call(string $relatedTableName, array $where = []): MultiResult
    {
        $table = $this->database->getConfig()->getStructure()->getReferencingTable(
            $relatedTableName,
            $this->result->getTable()
        );
        $column = $this->database->getConfig()->getStructure()->getReferencingColumn($table, $this->result->getTable());
        $return = new MultiResult($table, $this->result, $column, $this[$this->result->getPrimary()]);
        $return->where("$table.$column", array_keys((array)$this->result->getRows())); // (array) - is null after insert
        if (!empty($where)) {
            call_user_func_array([$return, 'where'], $where);
        }
        return $return;
    }

    /**
     * Get referencing rows
     */
    public function related(string $relatedTableName, array $where = []): MultiResult
    {
        return $this->__call($relatedTableName, $where);
    }

    /**
     * Returns data obtained from the database
     */
    public function getData(): array
    {
        return $this->row;
    }

    /**
     * Update row
     *
     * @param array|null $data or null for all modified values
     * @return int|false number of affected rows or false in case of an error
     */
    public function update(array $data = null)
    {
        // update is an SQL keyword
        if (!isset($data)) {
            $data = $this->modified;
        }
        $result = new Result($this->result->getTable(), $this->database);
        $return = $result->where($this->result->getPrimary(), $this->primary)->update($data);
        $this->primary = $this[$this->result->getPrimary()];
        return $return;
    }

    /**
     * Delete row
     *
     * @return int|false number of affected rows or false in case of an error
     */
    public function delete()
    {
        // delete is an SQL keyword
        $result = new Result($this->result->getTable(), $this->database);
        $return = $result->where($this->result->getPrimary(), $this->primary)->delete();
        $this->primary = $this[$this->result->getPrimary()];
        return $return;
    }

    protected function access(string $key, bool $delete = false): void
    {
        if (
            $this->database->getConfig()->getCache()
            && !isset($this->modified[$key])
            && $this->result->access($key, $delete)
        ) {
            $id = ($this->primary ?? $this->row);
            $this->row = $this->result[$id]->row;
        }
    }

    // IteratorAggregate implementation
    public function getIterator(): \ArrayIterator
    {
        $this->access('');
        return new \ArrayIterator($this->row);
    }

    // Countable implementation
    public function count(): int
    {
        return count($this->row);
    }

    // ArrayAccess implementation

    /**
     * Test if column exists
     *
     * @param string $key column name
     */
    public function offsetExists($key): bool
    {
        $this->access($key);
        $return = array_key_exists($key, $this->row);
        if (!$return) {
            $this->access($key, true);
        }
        return $return;
    }

    /**
     * Get value of column
     * @param string $key column name
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        $this->access($key);
        if (!array_key_exists($key, $this->row)) {
            $this->access($key, true);
        }
        return $this->row[$key];
    }

    /**
     * Store value in column
     *
     * @param string $key column name
     */
    public function offsetSet($key, $value): void
    {
        $this->row[$key] = $value;
        $this->modified[$key] = $value;
    }

    /**
     * Remove column from data
     *
     * @param string $key column name
     */
    public function offsetUnset($key): void
    {
        unset($this->row[$key]);
        unset($this->modified[$key]);
    }

    // JsonSerializable implementation

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->row;
    }

}
