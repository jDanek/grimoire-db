<?php

namespace Grimoire\Test;

use Grimoire\Result\Row;
use Grimoire\Test\Helpers\CustomRow;

class RowClassTest extends AbstractGrimoireTestCase
{

    public function testRowClass()
    {
        // update config
        $cfg = $this->db->getConfig();
        $cfg->setRowClass(CustomRow::class);

        $application = $this->db->row('application', 1);
        $this->assertEquals('Adminer', $application['test_title']);
        $this->assertEquals('Jakub Vrana', $application->ref('author')['test_name']);

        $cfg->setRowClass(Row::class);
    }
}
