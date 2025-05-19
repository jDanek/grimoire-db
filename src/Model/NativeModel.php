<?php

declare(strict_types=1);

namespace Grimoire\Model;

use Grimoire\ConnectionResolverInterface;
use Grimoire\Database;
use Grimoire\Result\Result;
use Grimoire\Result\Row;
use Grimoire\Util\StaticProxyTrait;

abstract class NativeModel
{
    use StaticProxyTrait;

    /** @var ConnectionResolverInterface */
    private static $resolver;
    /** @var string */
    protected $connectionName = null;
    /** @var string */
    protected $table;
    /** @var string */
    protected $primaryColumn;

    public function __construct(
        ?string $table = null,
        ?string $primaryColumn = null
    ) {
        if(static::$resolver === null) {
            throw new \RuntimeException('Database connection resolver must be set via NativeModel::setConnectionResolver() before using the model.');
        }

        $this->table = $table ?? $this->getTableName();
        $this->primaryColumn = $primaryColumn ?? $this->getConnection()->getStructure()->getPrimary($this->getTableName());
    }

    /**
     * Resolve a connection instance.
     */
    public static function resolveConnection(?string $connection = null): Database
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     */
    public static function getConnectionResolver(): ?ConnectionResolverInterface
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     */
    public static function setConnectionResolver(ConnectionResolverInterface $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Unset the connection resolver for models.
     */
    public static function unsetConnectionResolver(): void
    {
        static::$resolver = null;
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnection(): Database
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /**
     * Set the connection associated with the model.
     * @return static
     */
    public function setConnectionName(?string $name)
    {
        $this->connectionName = $name;
        return $this;
    }

    /**
     * Return table instance
     */
    protected function table(): Result
    {
        if ($this->table === null) {
            throw new \InvalidArgumentException('Table name is not set');
        }
        return $this->getConnection()->table($this->getTableName());
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
     * @throws \InvalidArgumentException|\Throwable
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
     * @throws RowNotFoundException|\Throwable
     */
    public function findOrFail($id, ...$columns): Row
    {
        $row = $this->find($id, ...$columns);

        if ($row instanceof Row) {
            return $row;
        }

        throw new RowNotFoundException($this->getTableName(), $id);
    }

    /**
     * @param int|string $id
     * @param array|\Closure $columns
     * @param \Closure|null $callback first argument is Database instance
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

        return $callback($this->getConnection());
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

        throw new RowNotFoundException($this->getTableName());
    }

    /**
     * @param array|\Closure $columns
     * @param \Closure|null $callback first argument is Database instance
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

        return $callback($this->getConnection());
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

        throw new RowNotFoundException($this->getTableName());
    }

    /**
     * @param array|\Closure $columns
     * @param \Closure|null $callback first argument is Database instance
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

        return $callback($this->getConnection());
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
