<?php

declare(strict_types=1);

namespace Grimoire\Transaction;

use Grimoire\Database;
use Grimoire\Exception\TransactionException;

class Transaction
{
    /** @var Database */
    protected $database;

    /** @var int */
    private $transactionDepth = 0;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Start a transaction if it hasn't been started yet.
     */
    public function beginTransaction(): void
    {
        if ($this->transactionDepth !== 0) {
            throw new \LogicException(__METHOD__ . '() is not allowed to be called within a transaction() callback.');
        }
        $this->database->getConnection()->begin_transaction();
    }

    /**
     * Commit the transaction if it is at the top depth.
     */
    public function commit(): void
    {
        if ($this->transactionDepth !== 0) {
            throw new \LogicException(__METHOD__ . '() is not allowed to be called within a transaction() callback.');
        }
        $this->database->getConnection()->commit();
    }

    /**
     * Rollback the transaction if there is an error.
     */
    public function rollBack(): void
    {
        if ($this->transactionDepth !== 0) {
            throw new \LogicException(__METHOD__ . '() is not allowed to be called within a transaction() callback.');
        }
        $this->database->getConnection()->rollback();
    }

    /**
     * Execute a callback within a transaction with retries.
     *
     * @param int $attempts The number of retry attempts
     * @return mixed
     */
    public function execute(callable $callback, int $attempts = 1)
    {
        $currentAttempt = 1;

        while ($currentAttempt <= $attempts) {
            if ($this->transactionDepth === 0) {
                $this->beginTransaction();
            }

            $this->transactionDepth++;
            try {
                // execute the callback
                $result = $callback($this->database);
                $this->transactionDepth--;
                if ($this->transactionDepth === 0) {
                    $this->commit();
                }

                // exit and return the result if successful
                return $result;
            } catch (\Throwable $e) {
                $this->transactionDepth--;
                if ($this->transactionDepth === 0) {
                    $this->rollBack();
                }

                // if we still have attempts left, retry
                if ($currentAttempt < $attempts) {
                    $currentAttempt++;
                    continue;
                }

                // if no attempts left, rethrow the exception
                throw new TransactionException('Transaction failed after ' . $attempts . ' attempts. Last error: ' . $e->getMessage(), 0, $e);
            }
        }

        // this return will never happen
        return false;
    }
}
