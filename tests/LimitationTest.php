<?php

namespace Grimoire\Test;

class LimitationTest extends AbstractGrimoireTestCase
{

    public function testOffset()
    {
        $data = [];

        $application = $this->db->row('application', 1);
        foreach ($application->related('application_tag')->order('tag_id')->limit(1, 1) as $application_tag) {
            $data[] = $application_tag->ref('tag')['name'];
        }

        foreach ($this->db->table('application') as $application) {
            foreach (
                $application->related('application_tag')
                    ->order('tag_id')
                    ->limit(1, 1) as $application_tag
            ) {
                $data[] = $application_tag->ref('tag')['name'];
            }
        }

        $this->assertEquals([
            'MySQL',
            'MySQL',
            'MySQL',
        ], $data);
    }
}
