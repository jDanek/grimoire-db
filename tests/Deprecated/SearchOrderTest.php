<?php

namespace Grimoire\Test\Deprecated;

use Grimoire\Test\AbstractGrimoireTestCase;

class SearchOrderTest extends AbstractGrimoireTestCase
{

    public function testSearchOrder()
    {
        $data = [];
        foreach (
            $this->db->table('application', ['web LIKE ?', ['http://%']])
                ->order('title')
                ->limit(3) as $application
        ) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
            'Dibi',
            'JUSH',
        ], $data);
    }
}
