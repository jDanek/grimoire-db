<?php

namespace Grimoire\Test\Deprecated;

use Grimoire\Test\AbstractGrimoireTestCase;

;

class WhereTest extends AbstractGrimoireTestCase
{

    public function testWhere()
    {
        $data = [];
        foreach (
            [
                $this->db->table('application', ['id', [4]]),
                $this->db->table('application', ['id < ?', [4]]),
                $this->db->table('application', ['id < ?', [4]]),
                $this->db->table('application', ['id', [1, 2]]),
                $this->db->table('application', ['id', [null]]),
                $this->db->table('application', ['id', $this->db->table('application')]),
                $this->db->table('application', ['id < ?', [4]])->where('maintainer_id IS NOT NULL'),
                $this->db->table('application', ['id < ?' => 4, 'author_id' => 12]),
            ] as $result
        ) {
            $data[] = implode(
                ', ',
                array_keys(iterator_to_array($result->order('id')))
            ); // aggregation("GROUP_CONCAT(id)") is not available in all drivers
        }

        $this->assertEquals([
            '4',
            '1, 2, 3',
            '1, 2, 3',
            '1, 2',
            '',
            '1, 2, 3, 4',
            '1, 3',
            '3',
        ], $data);
    }
}
