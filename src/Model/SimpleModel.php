<?php

namespace Grimoire\Model;

use Grimoire\Database;
use Grimoire\Result\Result;
use Grimoire\Result\Row;

abstract class SimpleModel
{
    /** @var Database */
    protected $database;
    /** @var string */
    protected $tableName;
    /** @var string */
    protected $primaryKey;


    public function __construct(Database $database, string $tableName = null, string $primaryKey = 'id')
    {
        $this->database = $database;
        $this->tableName = $tableName ?? $this->tableNameByClass(get_class($this));
        $this->primaryKey = $primaryKey ?? 'id';
    }

    /**
     * Determine table by class name (Application => application, ApplicationTag => application_tag, Author => author, Tag => tag)
     */
    private function tableNameByClass(string $className): string
    {
        $tableName = explode("\\", $className);
        $tableName = lcfirst(array_pop($tableName));

        $replace = []; // A => _a
        foreach (range("A", "Z") as $letter) {
            $replace[$letter] = "_" . strtolower($letter);
        }

        return strtr($tableName, $replace);
    }


    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function all(): Result
    {
        return $this->database->table($this->tableName);
    }

    /**
     * @param mixed $condition string possibly containing ? or [condition => parameters, ...]
     * @param mixed $parameters array accepted by \mysqli_stmt::execute or a scalar value
     */
    public function where($condition, $parameters = []): Result
    {
        return $this->all()
            ->where($condition, $parameters);
    }

    public function find($id): ?Row
    {
        try {
            return $this->where([$this->primaryKey => $id])
                ->fetch();
        } catch (\Throwable $t) {
            return null;
        }
    }

    public function first(): ?Row
    {
        try {
            return $this->all()
                ->limit(1)
                ->fetch();
        } catch (\Throwable $t) {
            return null;
        }
    }

    public function last(): ?Row
    {
        try {
            return $this->all()
                ->order($this->primaryKey . ' DESC')
                ->limit(1)
                ->fetch();
        } catch (\Throwable $t) {
            return null;
        }
    }
}
