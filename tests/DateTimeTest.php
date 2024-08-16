<?php

namespace Grimoire\Test;

use Grimoire\Literal;

class DateTimeTest extends AbstractGrimoireTestCase
{

    public function testDateTime()
    {
        $date = new \DateTime("2011-08-30");

        $this->db->table('application')->insert([
            'id' => 5,
            'author_id' => 11,
            'title' => $date,
            'slogan' => new Literal('?', $date), // or shorthand $db::literal('?', $date)
        ]);

        $application = $this->db->table('application')->where("title = ?", $date)->fetch();
        $this->assertEquals('2011-08-30 00:00:00', $application['slogan']);
        $application->delete();
    }
}
