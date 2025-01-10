<?php

namespace Grimoire\Test;

use Grimoire\Config;
use Grimoire\ConnectionManager;
use Grimoire\Model\RowNotFoundException;
use Grimoire\Test\Helpers\Model\ApplicationModel;

class ModelTest extends AbstractGrimoireTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $config = Config::builder($this->connection);
        ConnectionManager::getInstance()->setDefaultConnection($config);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        ConnectionManager::getInstance()->removeAllConnections();
    }

    public function testAll()
    {
        $apps = ApplicationModel::statical()->all();

        $expected = [
            'Adminer' => [
                'author' => 'Jakub Vrana',
                'tags' => [
                    'PHP',
                    'MySQL',
                ],
            ],
            'JUSH' => [
                'author' => 'Jakub Vrana',
                'tags' => [
                    'JavaScript',
                ],
            ],
            'Nette' => [
                'author' => 'David Grudl',
                'tags' => [
                    'PHP',
                ],
            ],
            'Dibi' => [
                'author' => 'David Grudl',
                'tags' => [
                    'PHP',
                    'MySQL',
                ],
            ],
        ];

        $data1 = [];

        foreach ($apps as $app) {
            $title = $app['title'];
            $data1[$title] = ['author' => $app->ref('author')['name']];
            foreach ($app->related('application_tag') as $application_tag) {
                $data1[$title]['tags'][] = $application_tag->ref('tag')['name'];
            }
        }

        $this->assertEquals($expected, $data1);

        $data2 = [];
        foreach ($this->db->table('application') as $application) {
            $title = $application['title'];
            $data2[$title] = ['author' => $application->ref('author')['name']];
            foreach ($application->related('application_tag') as $application_tag) {
                $data2[$title]['tags'][] = $application_tag->ref('tag')['name'];
            }
        }

        $this->assertEquals($expected, $data2);
    }

    public function testWhere(): void
    {
        $application = ApplicationModel::statical()->where(['author_id' => 11]);

        $data = [];
        foreach ($application as $val) {
            $data[] = $val['title'];
        }

        $this->assertEquals([
            'Adminer',
            'JUSH',
        ], $data);
    }

    public function testFindMany(): void
    {
        $application = ApplicationModel::statical()->findMany([1, 2]);

        $data = [];
        foreach ($application as $val) {
            $data[] = $val['title'];
        }

        $this->assertEquals([
            'Adminer',
            'JUSH',
        ], $data);
    }

    public function testFind(): void
    {
        $application = ApplicationModel::statical()->find(1);

        $data = [];
        foreach ($application as $key => $val) {
            $data[$key] = $val;
        }

        $this->assertEquals([
            'id' => '1',
            'author_id' => '11',
            'maintainer_id' => '11',
            'title' => 'Adminer',
            'web' => 'http://www.adminer.org/',
            'slogan' => 'Database management in single PHP file',
        ], $data);
    }

    public function testFindOrFail(): void
    {
        $application = ApplicationModel::statical()->findOrFail(1);

        $data = [];
        foreach ($application as $key => $val) {
            $data[$key] = $val;
        }

        $this->assertEquals([
            'id' => '1',
            'author_id' => '11',
            'maintainer_id' => '11',
            'title' => 'Adminer',
            'web' => 'http://www.adminer.org/',
            'slogan' => 'Database management in single PHP file',
        ], $data);


        $this->expectException(RowNotFoundException::class);
        $application = ApplicationModel::statical()->findOrFail(999);
    }

    public function testFindOr(): void
    {
        $application = ApplicationModel::statical()->findOr(1);

        $data = [];
        foreach ($application as $key => $val) {
            $data[$key] = $val;
        }

        $this->assertEquals([
            'id' => '1',
            'author_id' => '11',
            'maintainer_id' => '11',
            'title' => 'Adminer',
            'web' => 'http://www.adminer.org/',
            'slogan' => 'Database management in single PHP file',
        ], $data);


        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Record not found, callback called');
        $application = ApplicationModel::statical()->findOr(999, function ($db) {
            throw new \Exception('Record not found, callback called');
        });
    }

    public function testFirst(): void
    {
        $application = ApplicationModel::statical()->first();
        $this->assertEquals('Adminer', $application['title']);
    }

    public function testFirstOrFail(): void
    {
        $this->db->table('application')->delete();

        $this->expectException(RowNotFoundException::class);
        $this->expectExceptionMessage('Row not found in table \'application\'');
        $application = ApplicationModel::statical()->firstOrFail();
    }

    public function testFirstOr(): void
    {
        $this->db->table('application')->delete();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Record not found, callback called');
        $application = ApplicationModel::statical()->firstOr(function ($db) {
            throw new \Exception('Record not found, callback called');
        });
    }

    public function testFirstOrCreate()
    {
        $this->db->table('application')->delete();

        $application = ApplicationModel::statical()->firstOrCreate(['id' => 1], [
            'id' => '1',
            'author_id' => '11',
            'maintainer_id' => '11',
            'title' => 'Adminer',
            'web' => 'http://www.adminer.org/',
            'slogan' => 'Database management in single PHP file',
        ]);

        // test if created
        $row = $this->db->row('application', 1);
        $data = [];
        foreach ($application as $key => $val) {
            $data[$key] = $val;
        }
        $this->assertEquals([
            'id' => '1',
            'author_id' => '11',
            'maintainer_id' => '11',
            'title' => 'Adminer',
            'web' => 'http://www.adminer.org/',
            'slogan' => 'Database management in single PHP file',
        ], $data);
    }

    public function testLast(): void
    {
        $application = ApplicationModel::statical()->last();
        $this->assertEquals('Dibi', $application['title']);
    }

    public function testLastOrFail(): void
    {
        $this->db->table('application')->delete();

        $this->expectException(RowNotFoundException::class);
        $this->expectExceptionMessage('Row not found in table \'application\'');
        $application = ApplicationModel::statical()->lastOrFail();
    }

    public function testUpdateOrCreate()
    {
        // update
        $application = ApplicationModel::statical()->updateOrCreate(
            ['id' => 1], // condition
            ['title' => 'renimdA'], // update
            [
                'id' => '99',
                'author_id' => '11',
                'maintainer_id' => '11',
                'title' => 'Inserter',
                'web' => 'http://www.inserter.org/',
                'slogan' => 'Database management in single PHP file',
            ]); // create

        // test if updated
        $row = $this->db->row('application', 1);
        $this->assertEquals('renimdA', $row['title']);


        // create
        $this->db->table('application')->delete();
        $application = ApplicationModel::statical()->updateOrCreate(
            ['id' => 99], // condition
            ['title' => 'renimdA'], // update
            [
                'id' => 99,
                'author_id' => 11,
                'maintainer_id' => 11,
                'title' => 'Inserter',
                'web' => 'http://www.inserter.org/',
                'slogan' => 'Database management in single PHP file',
            ]); // create

        // test if created
        $row = $this->db->row('application', 99);
        $this->assertEquals('Inserter', $row['title']);
    }

}
