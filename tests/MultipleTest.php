<?php

namespace Grimoire\Test;

class MultipleTest extends AbstractGrimoireTestCase
{

    public function testMultiple()
    {
        $data = [];
        $application = $this->db->row('application', 1);
        foreach (
            $application->related('application_tag')
                ->select('application_id', 'tag_id')
                ->order("application_id DESC", "tag_id DESC")
            as $application_tag
        ) {
            $data[] = $application_tag['application_id'] . ' ' . $application_tag['tag_id'];
        }

        $this->assertEquals([
            '1 22',
            '1 21',
        ], $data);
    }
}
