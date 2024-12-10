<?php

declare(strict_types=1);

namespace Grimoire\Structure;

use Grimoire\Cache\BlackHoleDriver;
use Grimoire\Util\StringFormatter;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Structure reading meta-information's from the database
 */
class DiscoveryStructure implements StructureInterface
{
    /** @var \Mysqli */
    protected $connection;
    /** @var CacheInterface|null */
    protected $cache;
    /** @var array|mixed */
    protected $structure = [];
    /** @var string */
    protected $foreign;

    /** @var StringFormatter */
    protected $stringFormatter;

    /**
     * @param string $foreign use "%s_id" to access $name . "_id" column in $row->$name
     */
    public static function create(\Mysqli $connection, ?CacheInterface $cache = null, string $foreign = '%s'): self
    {
        return new self($connection, new StringFormatter($connection), $cache, $foreign);
    }

    /**
     * Create autodiscovery structure
     *
     * @param string $foreign use "%s_id" to access $name . "_id" column in $row->$name
     */
    public function __construct(\Mysqli $connection, StringFormatter $stringFormatter, ?CacheInterface $cache = null, string $foreign = '%s')
    {
        $this->connection = $connection;
        $this->cache = $cache ?? new BlackHoleDriver();
        $this->foreign = $foreign;
        try {
            $this->structure = $this->cache->get('structure', []);
        } catch (InvalidArgumentException $e) {
            error_log('Cache get error: ' . $e->getMessage());
        }
        $this->stringFormatter = $stringFormatter;
    }

    /**
     * Save data to cache
     */
    public function __destruct()
    {
        try {
            $result = $this->cache->set('structure', $this->structure);
        } catch (InvalidArgumentException $e) {
            error_log('Cache set error: ' . $e->getMessage());
        }
    }

    public function getPrimary(string $table): string
    {
        $return = &$this->structure['primary'][$table];
        if (!isset($return)) {
            $return = '';
            foreach ($this->connection->query("EXPLAIN $table") as $column) {
                $column = array_values($column); // assoc array to numeric
                if ($column[3] === 'PRI') { // 3 - 'Key' is not compatible with PDO::CASE_LOWER
                    if ($return !== '') {
                        $return = ''; // multi-column primary key is not supported
                        break;
                    }
                    $return = $column[0];
                }
            }
        }
        return $return;
    }

    public function getReferencingColumn(string $name, string $table): string
    {
        $name = strtolower($name);
        $return = &$this->structure['referencing'][$table];
        if (!isset($return[$name])) {
            foreach (
                $this->connection->query(
                    "
                SELECT TABLE_NAME, COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME = " . $this->stringFormatter->quote($table) . "
                AND REFERENCED_COLUMN_NAME = " . $this->stringFormatter->quote(
                        $this->getPrimary($table)
                    ) //! may not reference primary key
                ) as $row
            ) {
                $row = array_values($row); // assoc array to numeric
                $return[strtolower($row[0])] = $row[1];
            }
        }
        return $return[$name];
    }

    public function getReferencingTable(string $name, string $table): string
    {
        return $name;
    }

    public function getReferencedColumn(string $name, string $table): string
    {
        return sprintf($this->foreign, $name);
    }

    public function getReferencedTable(string $name, string $table): string
    {
        $column = strtolower($this->getReferencedColumn($name, $table));
        $return = &$this->structure['referenced'][$table];
        if (!isset($return[$column])) {
            foreach (
                $this->connection->query(
                    "
                SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = " . $this->stringFormatter->quote($table) . "
            "
                ) as $row
            ) {
                $row = array_values($row); // assoc array to numeric
                $return[strtolower($row[0])] = $row[1];
            }
        }
        return $return[$column];
    }

    public function getSequence(string $table): ?string
    {
        return null;
    }

}
