<?php

declare(strict_types=1);

namespace Grimoire;

use Grimoire\Cache\BlackHoleDriver;
use Grimoire\Result\Row;
use Grimoire\Structure\ConventionStructure;
use Grimoire\Structure\StructureInterface;
use Psr\SimpleCache\CacheInterface;

class Config
{
    /** @var \Mysqli */
    private $connection;
    /** @var StructureInterface */
    private $structure = null;
    /** @var CacheInterface */
    private $cache = null;
    /** @var bool|callable */
    private $debug = false;
    /** @var callable */
    private $debugTimer;
    /** @var bool */
    private $freeze = false;
    /** @var string */
    private $rowClass = Row::class;
    /** @var bool */
    private $jsonAsArray = false;

    public static function builder(
        \Mysqli $connection,
        ?StructureInterface $structure = null,
        ?CacheInterface $cache = null
    ): self {
        return new self($connection, $structure, $cache);
    }

    protected function __construct(
        \Mysqli $connection,
        ?StructureInterface $structure = null,
        ?CacheInterface $cache = null
    ) {
        $this->setConnection($connection);
        $this->setStructure($structure);
        $this->setCache($cache);
    }

    public function getConnection(): \Mysqli
    {
        return $this->connection;
    }

    private function setConnection(\Mysqli $connection): void
    {
        $this->connection = $connection;
    }

    public function getStructure(): StructureInterface
    {
        $this->structure = $this->structure ?: new ConventionStructure();
        return $this->structure;
    }

    /**
     * @param StructureInterface|null $structure null for new ConventionStructure()
     */
    public function setStructure(?StructureInterface $structure): self
    {
        $this->structure = $structure;
        return $this;
    }

    public function getCache(): ?CacheInterface
    {
        $this->cache = $this->cache ?: new BlackHoleDriver();
        return $this->cache;
    }

    /**
     * @param CacheInterface|null $cache null for new BlackHoleDriver()
     */
    public function setCache(?CacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @return bool|callable
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * Enable debugging queries, true for error_log($query), callback($query, $parameters) otherwise
     * @param bool|callable $debug
     */
    public function setDebug($debug = true): self
    {
        if (is_bool($debug) || is_callable($debug)) {
            $this->debug = $debug;
        } else {
            throw new \InvalidArgumentException('Debug must be bool or callable, typeof ' . gettype($debug) . ' given');
        }
        return $this;
    }

    public function getDebugTimer(): ?callable
    {
        return $this->debugTimer;
    }

    /**
     * Call $callback() after executing a query
     */
    public function setDebugTimer(callable $debugTimer): self
    {
        $this->debugTimer = $debugTimer;
        return $this;
    }

    public function isFreeze(): bool
    {
        return $this->freeze;
    }

    /**
     * Disable persistence
     */
    public function setFreeze(bool $freeze): self
    {
        $this->freeze = $freeze;
        return $this;
    }

    public function getRowClass(): string
    {
        return $this->rowClass;
    }

    /**
     * Class used for created objects
     */
    public function setRowClass(string $rowClass): self
    {
        $this->rowClass = $rowClass;
        return $this;
    }

    public function getJsonAsArray(): bool
    {
        return $this->jsonAsArray;
    }

    /**
     * Use array instead of object in Result JSON serialization
     */
    public function setJsonAsArray(bool $jsonAsArray = true): self
    {
        $this->jsonAsArray = $jsonAsArray;
        return $this;
    }
}
