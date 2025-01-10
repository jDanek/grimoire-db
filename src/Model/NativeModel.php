<?php

namespace Grimoire\Model;

use Grimoire\ConnectionManager;
use Grimoire\Database;
use Grimoire\Result\Result;
use Grimoire\Result\Row;

abstract class NativeModel
{
    /** @var Database */
    private $database;
    /** @var string */
    protected $table;
    /** @var string */
    protected $primaryColumn;

    public function __construct(Database $database, ?string $table = null, string $primaryColumn = null)
    {
        $this->database = $database;
        $this->table = $table ?? $this->table;
        $this->primaryColumn = $primaryColumn ?? $this->database->getStructure()->getPrimary($this->table);
    }

    /**
     * Return static instance of model, use database instance from ConnectionManager
     *
     * @return static (return type 'static' is supported in PHP > 8.0, 'self' does not override methods from the child)
     */
    public static function statical()
    {
        return new static(ConnectionManager::getInstance()->getConnection());
    }

    /**
     * Return table instance
     *
     * @return Result
     */
    protected function table(): Result
    {
        if ($this->table === null) {
            throw new \InvalidArgumentException('Table name is not set');
        }
        return $this->database->table($this->table);
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * @param string|array|null $columns for example ['column1', 'column2'], 'column, MD5(column) AS column_md5', empty string to reset previously set columns
     * @return Result
     */
    public function all(...$columns): Result
    {
        return $this->table()
            ->select(...$columns);
    }

    /**
     * Get table data
     *
     * Supported $conditions formats:
     * -------------------------
     * - ['column_name = ? AND another > ?', [param1, ...]]
     * - ['column_name', instance of Result class]
     * - ['column_name', [param1, param2, ...]]
     * - ['column_name' => 'param1', ...]
     * - ['column_name > ?' => 'param1', 'another' => 45, ...]
     * - ['column_name' => ['param1', ...]]
     *
     * @param array $conditions (['condition', ['value', ...]]) passed to {@see Result::where()}
     */
    public function where(array $conditions, ...$columns): Result
    {
        return $this->table()
            ->select(...$columns)
            ->where($conditions);
    }

    /**
     * @param array $ids array of ids string or int
     */
    public function findMany(array $ids, ...$columns): Result
    {
        return $this->where([$this->primaryColumn => $ids], ...$columns);
    }

    /**
     * @param int|string $id get single row by id
     * @throws \LogicException|\Throwable
     */
    public function find($id, ...$columns): ?Row
    {
        if (is_array($id)) {
            throw new \LogicException('The value array is not supported, use the findMany() method for the array');
        }

        $result = $this->findMany([$id], ...$columns);

        if (($r = $result->fetch()) instanceof Row) {
            return $r;
        }

        return null;
    }

    /**
     * @param string|int $id
     * @throws RowNotFoundException|\Throwable
     */
    public function findOrFail($id, ...$columns): Row
    {
        $row = $this->find($id, ...$columns);

        if ($row instanceof Row) {
            return $row;
        }

        throw new RowNotFoundException($this->table, $id);
    }

    /**
     * @param int|string $id
     * @param array|\Closure $columns
     * @return Row|mixed|null
     * @throws \Throwable
     */
    public function findOr($id, $columns = [], ?\Closure $callback = null)
    {
        if ($columns instanceof \Closure) {
            $callback = $columns;
            $columns = [];
        }

        if (!is_null($row = $this->find($id, $columns ?? []))) {
            return $row;
        }

        return $callback($this->database);
    }


    /**
     * @throws \Throwable
     */
    public function first(...$columns): ?Row
    {
        $row = $this->all(...$columns)
            ->orderBy($this->primaryColumn . ' ASC')
            ->limit(1);

        if (($r = $row->fetch()) instanceof Row) {
            return $r;
        }

        return null;
    }

    /**
     * @throws RowNotFoundException|\Throwable
     */
    public function firstOrFail(...$columns): ?Row
    {
        $row = $this->first(...$columns);

        if ($row instanceof Row) {
            return $row;
        }

        throw new RowNotFoundException($this->table);
    }

    /**
     * @param array|\Closure $columns
     * @param \Closure|null $callback
     * @return Row|mixed|null
     * @throws \Throwable
     */
    public function firstOr($columns = [], ?\Closure $callback = null)
    {
        if ($columns instanceof \Closure) {
            $callback = $columns;
            $columns = [];
        }

        if (!is_null($row = $this->first($columns ?? []))) {
            return $row;
        }

        return $callback($this->database);
    }


    /**
     * @throws \Throwable
     */
    public function firstOrCreate(array $conditions, array $createData): Row
    {
        if (empty($createData)) {
            throw new \InvalidArgumentException('Argument $createData cannot be empty.');
        }

        $result = $this->where($conditions)
            ->orderBy($this->primaryColumn . ' ASC')
            ->limit(1);

        if (($r = $result->fetch()) instanceof Row) {
            return $r;
        }

        return $this->table()->insert($createData);
    }

    /**
     * @throws \Throwable
     */
    public function last(...$columns): ?Row
    {
        $row = $this->all(...$columns)
            ->orderBy($this->primaryColumn . ' DESC')
            ->limit(1);

        if (($r = $row->fetch()) instanceof Row) {
            return $r;
        }

        return null;
    }

    /**
     * @throws RowNotFoundException|\Throwable
     */
    public function lastOrFail(...$columns): ?Row
    {
        $row = $this->last(...$columns);

        if ($row instanceof Row) {
            return $row;
        }

        throw new RowNotFoundException($this->table);
    }


    /**
     * @throws \Throwable
     */
    public function updateOrCreate(array $conditions, array $updateData, array $insertData, ?int $limit = null)
    {
        if (empty($updateData) || empty($insertData)) {
            throw new \InvalidArgumentException('Argument $updateData and $insertData cannot be empty.');
        }

        $result = $this->where($conditions);

        if ($limit !== null) {
            $result->limit(max(1, $limit));
        }

        if (($r = $result->fetch()) instanceof Row) {
            return $r->update($updateData);
        }

        return $this->table()->insert($insertData);
    }
}
