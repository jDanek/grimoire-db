<?php
declare(strict_types=1);

namespace Grimoire\Structure;

/**
 * Information about tables and columns structure
 */
interface StructureInterface
{
    /**
     * Get primary key of a table in $db->$table()
     */
    public function getPrimary(string $table): string;

    /**
     * Get column holding foreign key in $table[$id]->$name()
     */
    public function getReferencingColumn(string $name, string $table): string;

    /**
     * Get target table in $table[$id]->$name()
     */
    public function getReferencingTable(string $name, string $table): string;

    /**
     * Get column holding foreign key in $table[$id]->$name
     */
    public function getReferencedColumn(string $name, string $table): string;

    /**
     * Get table holding foreign key in $table[$id]->$name
     */
    public function getReferencedTable(string $name, string $table): string;

    /**
     * Get sequence name, used by insert
     */
    public function getSequence(string $table): ?string;

}
