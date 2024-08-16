<?php

namespace Grimoire\Test;

use Grimoire\Config;
use Grimoire\Database;
use Grimoire\Test\Helpers\SessionCache;

class CacheTest extends AbstractGrimoireTestCase
{

    public function testCache()
    {
        $_SESSION = []; // not session_start() - headers already sent

        $config = Config::builder(
            $this->connection,
            null,
            new SessionCache('Grimoire')
        );
        $cache = new Database($config);

        $applications = $cache->table('application');
        $application = $applications->fetch();
        $title = $application['title'];
        $name = $application->ref('author')['name'];
        // get all columns with no cache
        $this->assertEquals('SELECT * FROM application', $applications);
        $applications->__destruct();

        $applications = $cache->table('application');
        $application = $applications->fetch();
        // get only title and author_id
        $this->assertEquals('SELECT id, title, author_id FROM application', $applications);
        $application['slogan']; // script changed and now we want also slogan
        // all columns must have been retrieved to get slogan
        $this->assertEquals('SELECT * FROM application', $applications);
        $applications->__destruct();


        $applications = $cache->table('application');
        $applications->fetch();
        // next time, get only title, author_id and slogan
        $this->assertEquals('SELECT id, title, author_id, slogan FROM application', $applications);
    }
}
