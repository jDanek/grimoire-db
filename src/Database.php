<?php

declare(strict_types=1);

namespace Grimoire;

use Grimoire\Exception\MissingQueryParameterException;
use Grimoire\Exception\NamedQueryNotFoundException;
use Grimoire\Result\Result;
use Grimoire\Result\Row;
use Grimoire\Structure\StructureInterface;
use Grimoire\Transaction\Transaction;
use Grimoire\Util\StringFormatter;
use Psr\SimpleCache\CacheInterface;

/**
 * Database representation
 */
class Database
{
    /** @var Config */
    protected $config;
    /** @var Transaction|null */
    protected $transaction = null;
    /** @var array */
    protected static $queue = null;
    /** @var array<string, callable> */
    private $namedQueries = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
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
     * - ['column_name > ?' => 'param1', 'another' => 45, ...]
     * - ['column_name' => ['param1', ...]]
     *
     * @param array $where (['condition', ['value', ...]]) passed to Result::where()
     */
    public function table(string $table, array $where = []): Result
    {
        $return = new Result($this->getStructure()->getReferencingTable($table, ''), $this);
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
     * @throws \Throwable
     */
    public function row(string $table, int $id): ?Row
    {
        $result = new Result($this->getStructure()->getReferencingTable($table, ''), $this, true);
        return $result->get($id);
    }


    /* --- TRANSACTIONS --- */

    /**
     * Lazy initialize transaction and execute the callback.
     *
     * @example $database->transactional(function ($db) {
     *     $db->table('users')->insert(['name' => 'John']);
     * });
     */
    public function transactional(callable $callback, int $attempts = 1)
    {
        if ($this->transaction === null) {
            $this->transaction = new Transaction($this);
        }
        return $this->transaction->execute($callback, $attempts);
    }

    /**
     * Start a transaction manually, without using a callback.
     */
    public function beginTransaction(): void
    {
        if ($this->transaction === null) {
            $this->transaction = new Transaction($this);
        }
        $this->transaction->beginTransaction();
    }

    /**
     * Commit the transaction manually.
     */
    public function commit(): void
    {
        if ($this->transaction === null) {
            throw new \LogicException('No transaction is started.');
        }
        $this->transaction->commit();
    }

    /**
     * Rollback the transaction manually.
     */
    public function rollBack(): void
    {
        if ($this->transaction === null) {
            throw new \LogicException('No transaction is started.');
        }
        $this->transaction->rollBack();
    }

    /* --- NAMED QUERIES --- */

    public function registerNamedQuery(string $name, callable $queryCallback, array $requiredParams = []): void
    {
        if (isset($this->namedQueries[$name])) {
            throw new \LogicException("Named query '$name' already exists.");
        }
        $this->namedQueries[$name] = ['callback' => $queryCallback, 'required_params' => $requiredParams];
    }

    /**
     * @throws NamedQueryNotFoundException|MissingQueryParameterException
     */
    public function runNamedQuery(string $name, array $args = [])
    {
        if (!isset($this->namedQueries[$name])) {
            throw new NamedQueryNotFoundException("Named query '" . $name . "' not found.");
        }

        // validation of required parameters
        $named = $this->namedQueries[$name];
        $requiredParams = $named['required_params'];
        foreach ($requiredParams as $param) {
            if (!array_key_exists($param, $args)) {
                throw new MissingQueryParameterException("Missing required parameter: '" . $param . "'");
            }
        }

        return call_user_func($named['callback'], $this, ...array_values($args));
    }

    public function removeNamedQuery(string $name)
    {
        unset($this->namedQueries[$name]);
    }

    public function clearNamedQueries(): void
    {
        $this->namedQueries = [];
    }

    /* --- HELPERS --- */

    public static function literal(string $value, ...$parameters): Literal
    {
        return new Literal($value, $parameters);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getConnection(): \Mysqli
    {
        return $this->config->getConnection();
    }

    public function getStructure(): StructureInterface
    {
        return $this->config->getStructure();
    }

    public function getCache(): CacheInterface
    {
        return $this->config->getCache();
    }

    public function getStringFormatter(): StringFormatter
    {
        return $this->config->getStringFormatter();
    }

    /* --- SHORTHANDS --- */

    /**
     * @param mixed $val
     */
    public function quote($val): string
    {
        return $this->getStringFormatter()->quote($val);
    }

    /* --- DEFERRED CALL --- */

    /**
     * Deferred call
     *
     * @param Row|callback $callback
     * @param ... $callback parameters for the callback
     */
    public static function then($callback)
    {
        // static because it uses ob_start() which creates a global state
        if (self::$queue !== null) {
            self::$queue[] = func_get_args();
        } else { // top level call
            self::$queue = [func_get_args()];

            ob_start(function ($string) {
                if (self::$queue === null) {
                    return $string;
                }
                self::$queue[] = $string;
                return '';
            }, 2); // 2 - minimal value, 1 means 4096 before PHP 5.4

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
}
