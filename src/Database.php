<?php

declare(strict_types=1);

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
    protected $config;
    /** @var array */
    protected static $queue = null;

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
            // simple autodetect assoc array - if $where is an associative array,
            // it is necessary to wrap it to avoid creating variables from the array keys
            $keys = array_keys($where);
            if ($keys !== array_keys($keys)) {
                $where = ['condition' => $where];
            }
            call_user_func_array([$return, 'where'], $where);
        }
        return $return;
    }

    /**
     * Get table data
     *
     * Supported $where formats:
     * -------------------------
     * - ['column_name = ? AND another > ?', [param1, ...]]
     * - ['column_name', instance of Result class]
     * - ['column_name', [param1, param2, ...]]
     * - ['column_name' => 'param1', ...]
     * - ['column_name > ?' => 'param1', 'another' = 45, ...]
     * - ['column_name' => ['param1', ...]]
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

    public static function literal(string $value, ...$parameters): Literal
    {
        return new Literal($value, $parameters);
    }

    /**
     * Deferred call
     *
     * @param Row|callback $callback
     * @param ... $callback parameters for the callback
     */
    static function then($callback)
    {
        // static because it uses ob_start() which creates a global state
        if (self::$queue !== null) {
            self::$queue[] = func_get_args();
        } else { // top level call
            self::$queue = array(func_get_args());
            ob_start([self::class, 'out'], 2); // 2 - minimal value, 1 means 4096 before PHP 5.4
            while (self::$queue) {
                $original = self::$queue;
                self::$queue = []; // queue is refilled in self::out() and self::then() calls from callbacks
                foreach ($original as $results) {
                    if (!is_array($results)) {
                        // self::out() is called by ob_start() so that it can print or requeue the string
                        echo $results;
                    } else {
                        $callback = array_pop($results);
                        call_user_func_array($callback, $results);
                    }
                }
            }
            ob_end_flush();
            // mark top level call for the next time
            self::$queue = null;
        }
    }

    /** @access protected must be public because it is called by ob_start() */
    static function out(string $string): string
    {
        if (self::$queue === null) {
            return $string;
        }
        self::$queue[] = $string;
        return '';
    }
}
