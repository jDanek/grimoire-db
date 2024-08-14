<?php

declare(strict_types=1);

namespace Grimoire;

use Grimoire\Result\Result;
use Grimoire\Result\Row;
use Grimoire\Util\StringFormatter;

/**
 * Database representation
 */
class Database
{
    /** @var Config */
    protected $config;
    /** @var int */
    private $transactionDepth = 0;
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

    public function getConnection(): \Mysqli
    {
        return $this->config->getConnection();
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
     * Get table row
     *
     * @throws \Exception
     */
    public function row(string $table, int $id): ?Row
    {
        $result = new Result($this->config->getStructure()->getReferencingTable($table, ''), $this, true);;
        return $result->get($id);
    }


    /* --- TRANSACTIONS --- */

    public function beginTransaction(): void
    {
        if ($this->transactionDepth !== 0) {
            throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
        }

        $this->getConnection()->begin_transaction();
    }

    public function commit(): void
    {
        if ($this->transactionDepth !== 0) {
            throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
        }

        $this->getConnection()->commit();
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth !== 0) {
            throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
        }

        $this->getConnection()->rollback();
    }


    /* --- FORMATTERS --- */

    /**
     * @param mixed $val
     */
    public function quote($val): string
    {
        return $this->config->getStringFormatter()->quote($val);
    }

    /**
     * @param mixed $val
     * @return float|int|string
     */
    public function formatValue($val)
    {
        return $this->config->getStringFormatter()->formatValue($val);
    }


    /* --- HELPERS --- */

    public static function literal(string $value, ...$parameters): Literal
    {
        return new Literal($value, $parameters);
    }


    /* --- DEFERRED CALL --- */

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
