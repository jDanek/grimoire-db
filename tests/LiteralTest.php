<?php

namespace Grimoire\Test;

use Grimoire\Literal;

class LiteralTest extends AbstractGrimoireTestCase
{

    public function testLiteral()
    {
        $data = [];
        foreach (
            $this->db->table('author')
                ->select(new Literal('? + ?', 1, 2)) // or shorthand $db::literal('? + ?', 1, 2)
                ->fetch() as $val
        ) {
            $data[] = $val;
        }

        $this->assertEquals([
            3,
        ], $data);
    }
}
