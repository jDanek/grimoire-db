<?php

namespace Grimoire\Test;

class AggregationTest extends AbstractGrimoireTestCase
{

    public function testAggregation()
    {
        $count = $this->db->table('application')->count("*");
        $this->assertEquals($count, 4);

        $data = [];
        foreach ($this->db->table('application') as $application) {
            $count = $application->related('application_tag')->count("*");
            $data[] = $application['title'] . ': ' . $count . ' tag(s)';
        }

        $this->assertEquals([
            'Adminer: 2 tag(s)',
            'JUSH: 1 tag(s)',
            'Nette: 1 tag(s)',
            'Dibi: 2 tag(s)',
        ], $data);
    }
}
