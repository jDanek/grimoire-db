<?php

namespace Grimoire\Test;

class SubQueryTest extends AbstractGrimoireTestCase
{

    public function testSubQuery()
    {
        $data = [];

        $unknownBorn = $this->db->table('author', ['born', [null]]); // authors with unknown date of born
        foreach ($this->db->table('application', ['author_id', $unknownBorn]) as $application) { // their applications
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
            'JUSH',
            'Nette',
            'Dibi',
        ], $data);
    }
}
