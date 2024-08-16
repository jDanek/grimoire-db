<?php

namespace Grimoire\Test;

class ToStringTest extends AbstractGrimoireTestCase
{

    public function testToString()
    {
        $expected = [
            '1',
            '2',
            '3',
            '4',
        ];

        $data1 = [];
        foreach ($this->db->table('application') as $application) {
            $data1[] = "$application";
        }
        $this->assertEquals($expected, $data1);

        $data2 = [];
        foreach ($this->db->table('application') as $application) {
            $data2[] = "$application";
        }

        $this->assertEquals($expected, $data2);
    }
}
