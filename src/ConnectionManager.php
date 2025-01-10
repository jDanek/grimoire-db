<?php

namespace Grimoire;

class ConnectionManager
{
    const DEFAULT_CONNECTION = 'default';

    /** @var ConnectionManager */
    private static $instance = null;

    /** @var array<string, Config> */
    private $configs = [];
    /** @var array<string, Database> */
    private $instances = [];
    /** @var string|null */
    private $defaultConnectionName = null;

    private function __construct()
    {
    }

    public static function getInstance(): ConnectionManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set default connection with key 'default'
     */
    public function setDefaultConnection(Config $config, bool $overwrite = false): void
    {
        $this->addConnection(self::DEFAULT_CONNECTION, $config, $overwrite);
    }

    /**
     * Returns default connection if exists
     */
    public function getDefaultConnection(): Database
    {
        return $this->getConnection(self::DEFAULT_CONNECTION);
    }

    public function addConnection(string $name, Config $config, bool $overwrite = false): void
    {
        if (!$overwrite && isset($this->configs[$name])) {
            throw new \RuntimeException("Connection '{$name}' already exists.");
        }

        $this->configs[$name] = $config;
        if ($this->defaultConnectionName === null) {
            $this->defaultConnectionName = $name;
        }
    }

    /**
     * @param string|null $name if null, returns default connection
     */
    public function getConnection(string $name = null): Database
    {
        $name = $name ?? $this->defaultConnectionName;

        if (!isset($this->configs[$name])) {
            throw new \RuntimeException("Configuration for connection '{$name}' is missing.");
        }

        // lazy loading instance
        if (!isset($this->instances[$name])) {
            $config = $this->configs[$name];
            $this->instances[$name] = new Database($config);
        }

        return $this->instances[$name];
    }

    public function removeConnection(string $name): void
    {
        unset($this->configs[$name]);
        unset($this->instances[$name]);
    }

    public function removeAllConnections(): void
    {
        $this->configs = [];
        $this->instances = [];
    }
}

