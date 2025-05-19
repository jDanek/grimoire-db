<?php

declare(strict_types=1);

namespace Grimoire;

interface ConnectionResolverInterface
{
    /**
     * Get a database connection instance.
     */
    public function connection(?string $name = null): Database;

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string;

    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(Config $config): void;
}
