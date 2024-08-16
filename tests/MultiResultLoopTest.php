<?php

namespace Grimoire\Test;

class MultiResultLoopTest extends AbstractGrimoireTestCase
{

    public function testMultiResultLoop()
    {
        $data = [];
        $application = $this->db->row('application', 1);
        for ($i = 0; $i < 4; $i++) {
            $data[] = count($application->related('application_tag'));
        }

        $this->assertEquals([
            2,
            2,
            2,
            2,
        ], $data);
    }
}
