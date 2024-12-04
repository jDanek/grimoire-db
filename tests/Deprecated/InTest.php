<?php

namespace Grimoire\Test\Deprecated;

use Grimoire\Test\AbstractGrimoireTestCase;

class InTest extends AbstractGrimoireTestCase
{

    public function testIn()
    {
        $this->assertEquals(0, $this->db->table('application', ['maintainer_id', []])->count("*"));
        $this->assertEquals(1, $this->db->table('application', ['maintainer_id', [11]])->count("*"));
        $this->assertEquals(2, $this->db->table('application', ["NOT maintainer_id", [11]])->count("*"));
        $this->assertEquals(3, $this->db->table('application', ["NOT maintainer_id", []])->count("*"));
    }

    public function testInMulti()
    {
        $data = [];
        foreach ($this->db->table('author')->order('id') as $author) {
            foreach (
                $this->db->table('application_tag', ['application_id', $author->related('application')])->order(
                    "application_id, tag_id"
                ) as $application_tag
            ) {
                $data[] = $author . ': ' . $application_tag['application_id'] . ': ' . $application_tag['tag_id'];
            }
        }

        $this->assertEquals([
            '11: 1: 21',
            '11: 1: 22',
            '11: 2: 23',
            '12: 3: 21',
            '12: 4: 21',
            '12: 4: 22',
        ], $data);
    }
}
