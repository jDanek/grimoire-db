<?php

namespace Grimoire\Test;

use Grimoire\Database;
use PHPUnit\Framework\TestCase;

class AbstractGrimoireTestCase extends TestCase
{
    /** @var Database */
    protected $db;

    protected $connection;

    protected function setUp(): void
    {
        $this->db = GrimoireConnection::getConnection();
        $this->connection = $this->db->getConnection();

        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollback();
    }
}
