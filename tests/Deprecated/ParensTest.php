<?php

namespace Grimoire\Test\Deprecated;

use Grimoire\Test\AbstractGrimoireTestCase;

class ParensTest extends AbstractGrimoireTestCase
{

    public function testParens()
    {
        $applications = $this->db->table('application')
            ->where('(author_id', 11)->and('maintainer_id', 11)->where(')')
            ->or('(author_id', 12)->and('maintainer_id', 12)->where(')');

        $data = [];
        foreach ($applications->order('title') as $application) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
            'Dibi',
            'Nette',
        ], $data);
    }
}
