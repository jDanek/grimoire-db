<?php

namespace Grimoire\Test;

class ConditionTest extends AbstractGrimoireTestCase
{

    public function testAnd()
    {
        $data = [];
        foreach ($this->db->table('application', ['author_id', 11])->and('maintainer_id', 11) as $application) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
        ], $data);
    }

    public function testOr()
    {
        $data = [];
        foreach (
            $this->db->table('application', ['author_id', 12])
                ->or('maintainer_id', 11)
                ->order('title') as $application
        ) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
            'Dibi',
            'Nette',
        ], $data);
    }

    public function testInNull()
    {
        $data = [];
        foreach ($this->db->table('application', ['maintainer_id', [11, null]]) as $application) {
            $data[] = $application['id'];
        }
        $this->assertEquals([
            1,
            2,
        ], $data);
    }
}
