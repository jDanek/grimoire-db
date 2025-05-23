<?php

declare(strict_types=1);

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

    protected $id;

    public function __construct(array $row, Result $result, Database $database, $id = false)
    {
        $this->row = $row;
        $this->result = $result;
        $this->database = $database;
        $this->id = $id;
        if (array_key_exists($result->getPrimary(), $row)) {
            $this->primary = $row[$result->getPrimary()];
        }
    }

    /**
     * Get primary key value
     */
    public function __toString(): string
    {
        return (string)$this[$this->result->getPrimary()];
    }

    /**
     * Get referenced row
     */
    public function ref(string $name, ?string $viaColumn = null): ?Row
    {
        $dbConfig = $this->database->getConfig();

        $column = $viaColumn ?? $dbConfig->getStructure()->getReferencedColumn($name, $this->result->getTable());

        $referenced = &$this->result->getReferenced($name);
        if (!isset($referenced)) {
            $keys = [];
            foreach ((array)$this->result->getRows() as $row) {
                if ($row[$column] !== null) {
                    $keys[$row[$column]] = null;
                }
            }

            $table = $dbConfig->getStructure()->getReferencedTable($name, $this->result->getTable());
            $referenced = new Result($table, $this->database);
            $referenced->where("$table." . $dbConfig->getStructure()->getPrimary($table), array_keys($keys));
        }

        /** @var Row $rowClass */
        $rowClass = $dbConfig->getRowClass();
        return new $rowClass([], $referenced, $this->database, $this[$column]);
    }

    /**
     * Get referencing rows
     *
     * @param string $relatedTableName table name
     * @param array $where (['condition', ['values']])
     */
    public function related(string $relatedTableName, array $where = []): MultiResult
    {
        $table = $this->database->getStructure()->getReferencingTable(
            $relatedTableName,
            $this->result->getTable()
        );
        $column = $this->database->getStructure()->getReferencingColumn($table, $this->result->getTable());
        $return = new MultiResult(
            $table,
            $this->database,
            $this->result,
            $column,
            (string)$this[$this->result->getPrimary()]
        );
        $return->where("$table.$column", array_keys((array)$this->result->getRows())); // (array) - is null after insert
        if (!empty($where)) {
            call_user_func_array([$return, 'where'], $where);
        }
        return $return;
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

    /**
     * Get referenced row
     */
    public function __get(string $name): ?Row
    {
        return $this->ref($name);
    }

    /**
     * Test if referenced row exists
     */
    public function __isset(string $name): bool
    {
        $row = $this->ref($name);
        return $row[$row->result->getPrimary()] !== false;
    }

    /**
     * Store referenced value
     */
    public function __set(string $name, Row $value = null): void
    {
        $column = $this->database->getStructure()->getReferencedColumn($name, $this->result->getTable());
        $this[$column] = $value;
    }

    /**
     * Remove referenced column from data
     */
    public function __unset(string $name): void
    {
        $column = $this->database->getStructure()->getReferencedColumn($name, $this->result->getTable());
        unset($this[$column]);
    }

    protected function access(string $key, bool $delete = false): bool
    {
        if ($this->id === null) { // couldn't be found
            return false;
        }
        if (empty($this->row)) { // lazy loading
            $row = $this->result[$this->id];
            $this->row = ($row ? $row->row : null);
        }
        if ($this->row === null) { // not found
            return false;
        }

        if (
            $this->database->getCache()
            && !isset($this->modified[$key])
            && $this->result->access($key, $delete)
        ) {
            $id = ($this->primary ?? $this->row);
            $this->row = $this->result[$id]->row;
        }
        return true;
    }

    // IteratorAggregate implementation
    public function getIterator(): \ArrayIterator
    {
        $this->access('');
        return new \ArrayIterator((array)$this->row);
    }

    // Countable implementation
    public function count(): int
    {
        $this->access('');
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
        if (!$this->access($key)) {
            return false;
        }
        $return = array_key_exists($key, $this->row);
        if (!$return) {
            $this->access($key, true);
        }
        return $return;
    }

    /**
     * Get value of column
     * @param string $key column name
     * @return string false for non-existent rows
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        if (!$this->access($key)) {
            return false;
        }
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
        $this->access($key);
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
