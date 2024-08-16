<?php

namespace Grimoire\Test;

class LockTest extends AbstractGrimoireTestCase
{

    public function testLock()
    {
        $this->assertEquals('SELECT * FROM application FOR UPDATE', $this->db->table('application')->lock());
    }
}
