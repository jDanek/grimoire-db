<?php

namespace Grimoire\Test;

use Grimoire\Config;
use Grimoire\ConnectionManager;
use Grimoire\Exception\RowNotFoundException;
use Grimoire\Model\NativeModel;
use Grimoire\Test\Helpers\Model\ApplicationModel;

class ModelTest extends AbstractGrimoireTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // setup connection
        $connectionManager = ConnectionManager::getInstance();
        $connectionManager->setDefaultConnection(Config::builder($this->connection));

        // setup models
        NativeModel::setConnectionResolver($connectionManager);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        ConnectionManager::getInstance()->removeAllConnections();
    }

    public function testAll()
    {
        $apps = ApplicationModel::all();

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

    public function testAllWithArgs()
    {
        $apps = ApplicationModel::all('title', 'author_id', 'slogan');

        $expected = [
            'Adminer' => [
                'author' => 'Jakub Vrana',
                'slogan' => 'Database management in single PHP file',
            ],
            'JUSH' => [
                'author' => 'Jakub Vrana',
                'slogan' => 'JavaScript Syntax Highlighter',
            ],
            'Nette' => [
                'author' => 'David Grudl',
                'slogan' => 'Nette Framework for PHP 5',
            ],
            'Dibi' => [
                'author' => 'David Grudl',
                'slogan' => 'Database Abstraction Library for PHP 5',
            ],
        ];

        $data1 = [];

        foreach ($apps as $app) {
            $title = $app['title'];
            $data1[$title] = [
                'author' => $app->ref('author')['name'],
                'slogan' => $app['slogan'],
            ];
        }

        $this->assertEquals($expected, $data1);

        $data2 = [];
        foreach ($this->db->table('application') as $application) {
            $title = $application['title'];
            $data2[$title] = [
                'author' => $application->ref('author')['name'],
                'slogan' => $application['slogan'],
            ];
        }

        $this->assertEquals($expected, $data2);
    }


    public function testWhere(): void
    {
        $application = ApplicationModel::where(['author_id' => 11]);

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
        $application = ApplicationModel::findMany([1, 2]);

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
        $application = ApplicationModel::find(1);

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
        $application = ApplicationModel::findOrFail(1);

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
        $application = ApplicationModel::findOrFail(999);
    }

    public function testFindOr(): void
    {
        $application = ApplicationModel::findOr(1);

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
        $application = ApplicationModel::findOr(999, function ($db) {
            throw new \Exception('Record not found, callback called');
        });
    }

    public function testFirst(): void
    {
        $application = ApplicationModel::first();
        $this->assertEquals('Adminer', $application['title']);
    }

    public function testFirstOrFail(): void
    {
        $this->db->table('application')->delete();

        $this->expectException(RowNotFoundException::class);
        $this->expectExceptionMessage('Row not found in table \'application\'');
        $application = ApplicationModel::firstOrFail();
    }

    public function testFirstOr(): void
    {
        $this->db->table('application')->delete();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Record not found, callback called');
        $application = ApplicationModel::firstOr(function ($db) {
            throw new \Exception('Record not found, callback called');
        });
    }

    public function testFirstOrCreate()
    {
        $this->db->table('application')->delete();

        $application = ApplicationModel::firstOrCreate(['id' => 1], [
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
        $application = ApplicationModel::last();
        $this->assertEquals('Dibi', $application['title']);
    }

    public function testLastOrFail(): void
    {
        $this->db->table('application')->delete();

        $this->expectException(RowNotFoundException::class);
        $this->expectExceptionMessage('Row not found in table \'application\'');
        $application = ApplicationModel::lastOrFail();
    }

    public function testLastOr(): void
    {
        $this->db->table('application')->delete();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Record not found, callback called');
        $application = ApplicationModel::lastOr(function ($db) {
            throw new \Exception('Record not found, callback called');
        });
    }

    public function testUpdateOrCreate()
    {
        // update
        $application = ApplicationModel::updateOrCreate(
            ['id' => 1], // condition
            ['title' => 'renimdA'], // update
            [
                'id' => '99',
                'author_id' => '11',
                'maintainer_id' => '11',
                'title' => 'Inserter',
                'web' => 'http://www.inserter.org/',
                'slogan' => 'Database management in single PHP file',
            ]
        ); // create

        // test if updated
        $row = $this->db->row('application', 1);
        $this->assertEquals('renimdA', $row['title']);


        // create
        $this->db->table('application')->delete();
        $application = ApplicationModel::updateOrCreate(
            ['id' => 99], // condition
            ['title' => 'renimdA'], // update
            [
                'id' => 99,
                'author_id' => 11,
                'maintainer_id' => 11,
                'title' => 'Inserter',
                'web' => 'http://www.inserter.org/',
                'slogan' => 'Database management in single PHP file',
            ]
        ); // create

        // test if created
        $row = $this->db->row('application', 99);
        $this->assertEquals('Inserter', $row['title']);
    }

}
