<?php

namespace Grimoire\Test\Deprecated;

use Grimoire\Test\AbstractGrimoireTestCase;

class PairsTest extends AbstractGrimoireTestCase
{

    public function testPairs()
    {
        $this->assertEquals([
            1 => 'Adminer',
            4 => 'Dibi',
            2 => 'JUSH',
            3 => 'Nette',
        ],
            $this->db->table('application')->order('title')->fetchPairs('id', 'title')
        );

        $this->assertEquals([
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
        ],
            $this->db->table('application')->order('id')->fetchPairs('id', 'id')
        );
    }
}
