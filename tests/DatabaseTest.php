<?php

declare(strict_types=1);

namespace Grimoire\Test;

use Grimoire\Database;

class DatabaseTest extends AbstractGrimoireTestCase
{

    public function testInstance()
    {
        $this->assertInstanceOf(Database::class, $this->db);
    }

}
