<?php

namespace Grimoire\Structure;

/**
 * Structure described by some rules
 */
class ConventionStructure implements StructureInterface
{
    /** @var string */
    protected $primary;
    /** @var string */
    protected $foreign;
    /** @var string */
    protected $table;
    /** @var string */
    protected $prefix;

    /**
     * Create conventional structure
     *
     * @param string $primary %s stands for table name
     * @param string $foreign %1$s stands for key used after ->, %2$s for table name
     * @param string $table %1$s stands for key used after ->, %2$s for table name
     * @param string $prefix prefix for all tables
     */
    public function __construct(
        string $primary = 'id',
        string $foreign = '%s_id',
        string $table = '%s',
        string $prefix = ''
    ) {
        $this->primary = $primary;
        $this->foreign = $foreign;
        $this->table = $table;
        $this->prefix = $prefix;
    }

    public function getPrimary(string $table): string
    {
        return sprintf($this->primary, $this->getColumnFromTable($table));
    }

    public function getReferencingColumn(string $name, string $table): string
    {
        return $this->getReferencedColumn(substr($table, strlen($this->prefix)), $this->prefix . $name);
    }

    public function getReferencingTable(string $name, string $table): string
    {
        return $this->prefix . $name;
    }

    public function getReferencedColumn(string $name, string $table): string
    {
        return sprintf($this->foreign, $this->getColumnFromTable($name), substr($table, strlen($this->prefix)));
    }

    public function getReferencedTable(string $name, string $table): string
    {
        return $this->prefix . sprintf($this->table, $name, $table);
    }

    public function getSequence(string $table): ?string
    {
        return null;
    }

    protected function getColumnFromTable(string $name): string
    {
        if (
            $this->table !== '%s'
            && preg_match('(^' . str_replace('%s', '(.*)', preg_quote($this->table)) . '$)', $name, $match)
        ) {
            return $match[1];
        }
        return $name;
    }

}
