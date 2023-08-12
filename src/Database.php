<?php

namespace Grimoire;

use Grimoire\Result\Result;
use Grimoire\Result\Row;

/**
 * Database representation
 * @property-write string $transaction Assign 'BEGIN', 'COMMIT' or 'ROLLBACK' to start or stop transaction
 */
class Database
{
    public const TRANSACTION_BEGIN = 'BEGIN';
    public const TRANSACTION_COMMIT = 'COMMIT';
    public const TRANSACTION_ROLLBACK = 'ROLLBACK';

    /** @var Config */
    public $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Direct call of the table name
     *
     * @param array $where (['condition', ['value', ...]]) passed to Result::where()
     */
    public function __call(string $table, array $where): Result
    {
        $return = new Result($this->config->getStructure()->getReferencingTable($table, ''), $this);
        if (!empty($where)) {
            call_user_func_array([$return, 'where'], $where);
        }
        return $return;
    }

    /**
     * Get table data
     *
     * @param array $where (['condition', ['value', ...]]) passed to Result::where()
     */
    public function table(string $table, array $where = []): Result
    {
        return $this->__call($table, $where);
    }

    /**
     * Get table row to use as $db->table[1]
     */
    public function __get(string $table): Result
    {
        return new Result($this->config->getStructure()->getReferencingTable($table, ''), $this, true);
    }

    /**
     * @throws \Exception
     */
    public function row(string $table, int $id): ?Row
    {
        $result = $this->__get($table);
        return $result->get($id);
    }

    /**
     * Set write-only properties
     */
    public function __set(string $name, $value): void
    {
        if (in_array($name, ['debug', 'freeze', 'debugTimer', 'rowClass', 'jsonAsArray'])) {
            try {
                call_user_func([$this->config, 'set' . ucfirst($name)], $value);
            } catch (\Exception $e) {
                // invalid ignore
            }
        }
        if ($name === 'transaction') {
            switch (strtoupper($value)) {
                case self::TRANSACTION_BEGIN:
                    $this->beginTransaction();
                    break;
                case self::TRANSACTION_COMMIT:
                    $this->commit();
                    break;
                case self::TRANSACTION_ROLLBACK:
                    $this->rollback();
                    break;
            }
        }
    }

    public function beginTransaction(): void
    {
        $this->config->getConnection()->begin_transaction();
    }

    public function commit(): void
    {
        $this->config->getConnection()->commit();
    }

    public function rollback(): void
    {
        $this->config->getConnection()->rollback();
    }
}
