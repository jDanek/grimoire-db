<?php

namespace Grimoire\Test;

use Grimoire\Config;
use Grimoire\ConnectionManager;
use Grimoire\Database;
use Grimoire\Test\Helpers\GrimoireConnection;
use PHPUnit\Framework\TestCase;

class ConnectionManagerTest extends TestCase
{

    /** @var ConnectionManager */
    private $cm = null;
    /** @var Config */
    private $config = null;

    public function setUp(): void
    {
        $this->cm = ConnectionManager::getInstance();
        $this->config = Config::builder(GrimoireConnection::getMysql());
    }

    public function tearDown(): void
    {
        ConnectionManager::getInstance()->removeAllConnections();
    }

    public function testAddConnection(): void
    {
        $this->cm->addConnection('default', $this->config);

        // check if config was added
        $ref = new \ReflectionProperty($this->cm, 'configs');
        $configs = $ref->getValue($this->cm);
        $this->assertArrayHasKey('default', $configs);
    }

    public function testRemoveConnection(): void
    {
        $this->cm->addConnection('default', $this->config);
        $this->cm->removeConnection('default');

        $this->expectException(\RuntimeException::class);
        $connection = $this->cm->getConnection();
    }

    public function testSuccessGetConnection(): void
    {

        $this->cm->addConnection('default', $this->config);
        $connection = $this->cm->getConnection();

        // check if config was added
        $ref = new \ReflectionProperty($this->cm, 'instances');
        $instances = $ref->getValue($this->cm);
        $this->assertArrayHasKey('default', $instances);

        // check if connection is instance of Database
        $this->assertInstanceOf(Database::class, $connection);
    }

    public function testFailGetConnection(): void
    {
        $this->cm->addConnection('default', $this->config);

        $this->expectException(\RuntimeException::class);
        $connection = $this->cm->getConnection('foobar');
    }

}
