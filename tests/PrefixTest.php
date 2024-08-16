<?php

namespace Grimoire\Test;

use Grimoire\Config;
use Grimoire\Database;
use Grimoire\Structure\ConventionStructure;

class PrefixTest extends AbstractGrimoireTestCase
{

    public function testPrefix()
    {
        $config = Config::builder($this->connection)
            ->setStructure(new ConventionStructure('id', '%s_id', '%s', 'prefix_'));

        $prefix = new Database($config);
        $applications = $prefix->table('application', ['author.name', 'Jakub Vrana']);

        $this->assertEquals(
            'SELECT prefix_application.* FROM prefix_application LEFT JOIN prefix_author AS author ON prefix_application.author_id = author.id WHERE (author.name = \'Jakub Vrana\')',
            $applications
        );
    }
}
