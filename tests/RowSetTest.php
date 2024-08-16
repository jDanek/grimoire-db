<?php

namespace Grimoire\Test;

class RowSetTest extends AbstractGrimoireTestCase
{

    public function testRowSet()
    {
        $application = $this->db->row('application', 1);
        $application->author = $this->db->row('author', 12);
        $this->assertEquals(1, $application->update());
        $application->update(['author_id' => 11]);
    }
}
