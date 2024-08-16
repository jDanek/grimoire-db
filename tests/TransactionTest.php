<?php

namespace Grimoire\Test;

class TransactionTest extends AbstractGrimoireTestCase
{

    public function testTransaction()
    {
        $data = [];
        $this->db->beginTransaction();
        $this->db->table('tag')->insert(['id' => 99, 'name' => 'Test']);
        $data[] = (string)$this->db->row('tag', 99);
        $this->db->rollback();
        $data[] = (string)$this->db->row('tag', 99);

        $this->assertEquals([
            99,
            null,
        ], $data);
    }
}
