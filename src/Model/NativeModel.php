<?php

declare(strict_types=1);

namespace Grimoire\Model;

use Grimoire\ConnectionResolverInterface;
use Grimoire\Database;
use Grimoire\Result\Result;
use Grimoire\Result\Row;

/**
 * Methods for NativeModel from ModelQueryBuilder
 * @mixin ModelQueryBuilder
 *
 * @method static Result all(string|array|null ...$columns)
 * @method static Result where(array $conditions, string|array|null ...$columns)
 * @method static Result findMany(array $ids, string|array|null ...$columns)
 * @method static Row|null find(int|string $id, string|array|null ...$columns)
 * @method static Row findOrFail(int|string $id, string|array|null ...$columns)
 * @method static Row|mixed|null findOr(int|string $id, array|\Closure $columns, \Closure|null $callback = null)
 * @method static Row|null first(string|array|null ...$columns)
 * @method static Row firstOrFail(string|array|null ...$columns)
 * @method static Row|mixed|null firstOr(array|\Closure $columns, \Closure|null $callback = null)
 * @method static Row firstOrCreate(array $conditions, array $createData)
 * @method static Row|null last(string|array|null ...$columns)
 * @method static Row lastOrFail(string|array|null ...$columns)
 * @method static Row|mixed|null lastOr(array|\Closure $columns, \Closure|null $callback = null)
 * @method static mixed updateOrCreate(array $conditions, array $updateData, array $insertData, int|null $limit = null)
 * @method static false|Row|int insert(array ...$rows)
 * @method static false|int update(array $conditions, array ...$rows)
 * @method static false|int delete(array $conditions)
 */
abstract class NativeModel
{
    /** @var ConnectionResolverInterface */
    private static $resolver;
    /** @var ModelQueryBuilder */
    private $queryBuilder = null;
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
        if (static::$resolver === null) {
            throw new \RuntimeException('Database connection resolver must be set via NativeModel::setConnectionResolver() before using the model.');
        }

        $this->table = $table ?? $this->getTableName();
        $this->primaryColumn = $primaryColumn ?? $this->getConnection()->getStructure()->getPrimary($this->getTableName());
    }

    /**
     * Handle dynamic static method calls into the model.
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = new static;
        $queryBuilder = $instance->newQuery();

        // if method exists on query builder, call it
        if (method_exists($queryBuilder, $method)) {
            return $queryBuilder->$method(...$parameters);
        }

        // try to find scope{Method} method (Laravel style)
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($instance, $scopeMethod)) {
            return $instance->$scopeMethod($queryBuilder, ...$parameters);
        }

        throw new \BadMethodCallException("Method '{$method}' does not exist on model or query builder.");
    }

    /**
     * Handle dynamic method calls into the model from query builder.
     */
    public function __call($method, $parameters)
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = $this->newQuery();
        }

        if (method_exists($this->queryBuilder, $method)) {
            return $this->queryBuilder->$method(...$parameters);
        }

        throw new \BadMethodCallException("Method '{$method}' does not exist on query builder.");
    }

    /**
     * Start a new query on the model's table.
     */
    public static function query(): ModelQueryBuilder
    {
        return (new static)->newQuery();
    }

    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery(): ModelQueryBuilder
    {
        return new ModelQueryBuilder($this);
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
     * Get table name
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Get primary column name
     */
    public function getPrimaryColumn(): string
    {
        return $this->primaryColumn;
    }

    /**
     * Get auto-discovered scope methods
     */
    public function getScopeMethods(): array
    {
        static $scopesPerClass = [];
        $class = get_class($this);
        if (!isset($scopesPerClass[$class])) {
            $scopes = [];
            foreach (get_class_methods($this) as $method) {
                if (strlen($method) >= 5 && substr($method, 0, 5) === 'scope') {
                    $scopes[] = lcfirst(substr($method, 5));
                }
            }
            $scopesPerClass[$class] = $scopes;
        }
        return $scopesPerClass[$class];
    }

    /**
     * @see Database::beginTransaction
     */
    public static function beginTransaction(): void
    {
        static::resolveConnection()->beginTransaction();
    }

    /**
     * @see Database::commit
     */
    public static function commit(): void
    {
        static::resolveConnection()->commit();
    }

    /**
     * @see Database::rollBack
     */
    public static function rollBack(): void
    {
        static::resolveConnection()->rollBack();
    }
}
