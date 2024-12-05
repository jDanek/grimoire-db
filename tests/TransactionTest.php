<?php

namespace Grimoire\Test;

use Grimoire\Transaction\TransactionException;

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

    public function testTransactionalCallback()
    {
        $data = [];

        $this->db->transactional(function ($db) {
            $this->db->table('tag')->insert(['id' => 99, 'name' => 'Test']);
        });

        $tag = $this->db->row('tag', 99);

        $data[] = $tag['id'];
        $data[] = $tag['name'];

        // remove for next test
        $this->db->row('tag', 99)->delete();

        // test removing
        $data[] = (string)$this->db->row('tag', 99);

        $this->assertEquals([
            99,
            'Test',
            null,
        ], $data);
    }

    public function testTransactionalCallbackFailed()
    {
        $attempts = 3;

        $this->expectExceptionMessage('Transaction failed after ' . $attempts . ' attempts. Last error: Something went wrong!');
        $this->expectException(TransactionException::class);

        $this->db->transactional(function ($db) {
            throw new \Exception('Something went wrong!');
        }, $attempts);
    }
}
