<?php

namespace Grimoire\Test;

class SearchOrderTest extends AbstractGrimoireTestCase
{

    public function testSearchorderBy()
    {
        $data = [];
        foreach (
            $this->db->table('application', ['web LIKE ?', ['http://%']])
                ->orderBy('title')
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
