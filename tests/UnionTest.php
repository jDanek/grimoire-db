<?php

namespace Grimoire\Test;

class UnionTest extends AbstractGrimoireTestCase
{

    public function testUnion()
    {
        $data = [];
        $applications = $this->db->table('application')->select('id')->order('id DESC')->limit(2);
        $tags = $this->db->table('tag')->select('id')->order('id')->limit(2);
        foreach ($applications->union($tags)->order("id DESC") as $row) {
            $data[] = $row['id'];
        }

        $this->assertEquals([
            22,
            21,
            4,
            3,
        ], $data);
    }

    public function testSimpleUnion()
    {
        $data = [];
        $applications = $this->db->table('application')->select('id');
        $tags = $this->db->table('tag')->select('id');
        foreach ($applications->union($tags)->order("id DESC") as $row) {
            $data[] = $row['id'];
        }

        $this->assertEquals([
            24,
            23,
            22,
            21,
            4,
            3,
            2,
            1,
        ], $data);
    }
}
