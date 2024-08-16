<?php

declare(strict_types=1);

namespace Grimoire\Test\Helpers;

use Grimoire\Structure\ConventionStructure;

class SoftwareConventionStructure extends ConventionStructure
{
    function getReferencedTable(string $name, string $table): string
    {
        switch ($name) {
            case 'maintainer':
                return parent::getReferencedTable('author', $table);
        }
        return parent::getReferencedTable($name, $table);
    }
}
