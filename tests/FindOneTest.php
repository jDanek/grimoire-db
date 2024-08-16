<?php

namespace Grimoire\Test;

class FindOneTest extends AbstractGrimoireTestCase
{

    public function testFindOne()
    {
        $data1 = [];
        $application = $this->db->table('application')->where('title', 'Adminer')->fetch();
        foreach ($application->related('application_tag', ['tag_id', 21]) as $application_tag) {
            $data1[] = $application_tag->ref('tag')['name'];
        }

        $this->assertEquals([
            'PHP',
        ], $data1);


        $data2 = [];
        foreach ($application->related('application_tag', ['tag_id', 21]) as $application_tag) {
            $data2[] = $application_tag->ref('tag')['name'];
        }

        $this->assertEquals([
            'PHP',
        ], $data2);

        $slogan = $this->db->table('application', ['title', ['Adminer']])->fetch('slogan');
        $this->assertEquals('Database management in single PHP file', $slogan);
    }
}
