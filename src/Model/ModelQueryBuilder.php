<?php

declare(strict_types=1);

namespace Grimoire\Model;

use Grimoire\Result\Result;
use Grimoire\Result\Row;

/**
 * Query Builder for NativeModel
 */
class ModelQueryBuilder
{
    /** @var NativeModel */
    protected $model;
    /** @var Result */
    protected $query;

    public function __construct(NativeModel $model)
    {
        $this->model = $model;
        $this->query = $model->getConnection()->table($model->getTableName());
    }

    /**
     * Proxy for all methods to Result object
     */
    public function __call($method, $parameters)
    {
        // if method is scope method on model, call it
        if (method_exists($this->model, $method) && in_array($method, $this->model->getScopeMethods() ?? [])) {
            return $this->model->$method($this, ...$parameters);
        }

        // try to find scope{Method} method (Laravel style)
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this->model, $scopeMethod)) {
            return $this->model->$scopeMethod($this, ...$parameters);
        }

        $result = $this->query->$method(...$parameters);

        // if Result returns itself, return this builder for fluent interface
        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    /**
     * @param string|array|null $columns
     * @return Result
     */
    public function all(...$columns): Result
    {
        return $this->query->select(...$columns);
    }

    /**
     * @param array $conditions
     * @param string|array|null ...$columns
     * @return Result
     */
    public function where(array $conditions, ...$columns): Result
    {
        return $this->query
            ->select(...$columns)
            ->where($conditions);
    }

    /**
     * @param array $ids
     * @param string|array|null ...$columns
     * @return Result
     */
    public function findMany(array $ids, ...$columns): Result
    {
        return $this->where([$this->model->getPrimaryColumn() => $ids], ...$columns);
    }

    /**
     * @param int|string $id
     * @param string|array|null ...$columns
     * @return Row|null
     * @throws \Throwable
     */
    public function find($id, ...$columns): ?Row
    {
        if (is_array($id)) {
            throw new \InvalidArgumentException('The value array is not supported, use the findMany($ids) method for the array.');
        }

        $result = $this->findMany([$id], ...$columns);

        if (($r = $result->fetch()) instanceof Row) {
            return $r;
        }

        return null;
    }

    /**
     * @param string|int $id
     * @param string|array|null ...$columns
     * @return Row
     * @throws RowNotFoundException|\Throwable
     */
    public function findOrFail($id, ...$columns): Row
    {
        $row = $this->find($id, ...$columns);

        if ($row instanceof Row) {
            return $row;
        }

        throw new RowNotFoundException($this->model->getTableName(), $id);
    }

    /**
     * @param int|string $id
     * @param array|\Closure $columns
     * @param \Closure|null $callback
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

        return $callback($this->model->getConnection());
    }

    /**
     * @param string|array|null ...$columns
     * @return Row|null
     * @throws \Throwable
     */
    public function first(...$columns): ?Row
    {
        $row = $this->all(...$columns)
            ->orderBy($this->model->getPrimaryColumn() . ' ASC')
            ->limit(1);

        if (($r = $row->fetch()) instanceof Row) {
            return $r;
        }

        return null;
    }

    /**
     * @param string|array|null ...$columns
     * @return Row
     * @throws RowNotFoundException|\Throwable
     */
    public function firstOrFail(...$columns): Row
    {
        $row = $this->first(...$columns);

        if ($row instanceof Row) {
            return $row;
        }

        throw new RowNotFoundException($this->model->getTableName());
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

        return $callback($this->model->getConnection());
    }

    /**
     * @param array $conditions
     * @param array $createData
     * @return Row
     * @throws \Throwable
     */
    public function firstOrCreate(array $conditions, array $createData): Row
    {
        if (empty($createData)) {
            throw new \InvalidArgumentException('Argument $createData cannot be empty.');
        }

        $result = $this->where($conditions)
            ->orderBy($this->model->getPrimaryColumn() . ' ASC')
            ->limit(1);

        if (($r = $result->fetch()) instanceof Row) {
            return $r;
        }

        return $this->query->insert($createData);
    }

    /**
     * @param string|array|null ...$columns
     * @return Row|null
     * @throws \Throwable
     */
    public function last(...$columns): ?Row
    {
        $row = $this->all(...$columns)
            ->orderBy($this->model->getPrimaryColumn() . ' DESC')
            ->limit(1);

        if (($r = $row->fetch()) instanceof Row) {
            return $r;
        }

        return null;
    }

    /**
     * @param string|array|null ...$columns
     * @return Row
     * @throws RowNotFoundException|\Throwable
     */
    public function lastOrFail(...$columns): Row
    {
        $row = $this->last(...$columns);

        if ($row instanceof Row) {
            return $row;
        }

        throw new RowNotFoundException($this->model->getTableName());
    }

    /**
     * @param array|\Closure $columns
     * @param \Closure|null $callback
     * @return Row|mixed|null
     * @throws \Throwable
     */
    public function lastOr($columns = [], ?\Closure $callback = null)
    {
        if ($columns instanceof \Closure) {
            $callback = $columns;
            $columns = [];
        }

        if (!is_null($row = $this->last($columns ?? []))) {
            return $row;
        }

        return $callback($this->model->getConnection());
    }

    /**
     * @param array $conditions
     * @param array $updateData
     * @param array $insertData
     * @param int|null $limit
     * @return mixed
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

        return $this->query->insert($insertData);
    }

    /**
     * @param array ...$rows
     * @return false|Row|int
     */
    public function insert(...$rows)
    {
        return $this->query->insert(...$rows);
    }

    /**
     * @param array $conditions
     * @param array $data
     * @return false|int
     */
    public function update(array $conditions, array $data)
    {
        return $this->where($conditions)->update($data);
    }

    /**
     * @param array $conditions
     * @return false|int
     */
    public function delete(array $conditions)
    {
        return $this->where($conditions)->delete();
    }
}
