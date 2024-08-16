<?php

namespace Grimoire\Test;

class DetailTest extends AbstractGrimoireTestCase
{

    public function testDetail()
    {
        $data = [];
        $application = $this->db->row('application', 1);
        foreach ($application as $key => $val) {
            $data[] = "$key: $val";
        }

        $this->assertEquals([
            'id: 1',
            'author_id: 11',
            'maintainer_id: 11',
            'title: Adminer',
            'web: http://www.adminer.org/',
            'slogan: Database management in single PHP file',
        ], $data);
    }
}
