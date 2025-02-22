<?php

namespace Grimoire\Test;

class ArrayOffsetTest extends AbstractGrimoireTestCase
{

    public function testArrayOffset()
    {
        $where = [
            'author_id' => '11',
            'maintainer_id' => null,
        ];

        $this->assertEquals(2, $this->db->table('application', $where)->fetch('id'));

        $applications = $this->db->table('application')->orderBy('id');
        $this->assertEquals(2, $applications[$where]['id']);
    }
}
