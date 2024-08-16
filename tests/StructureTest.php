<?php

namespace Grimoire\Test;

use Grimoire\Config;
use Grimoire\Database;
use Grimoire\Structure\DiscoveryStructure;
use Grimoire\Test\Helpers\SoftwareConventionStructure;

class StructureTest extends AbstractGrimoireTestCase
{

    public function testDiscovery()
    {
        $config = Config::builder(
            $this->connection,
            DiscoveryStructure::create($this->connection, null, '%s_id')
        );
        $db = new Database($config);

        $data = [];
        foreach ($db->table('application') as $application) {
            $title = $application['title'];
            $data[$title] = ['author' => $application->ref('author')['name']];
            foreach ($application->related('application_tag') as $application_tag) {
                $data[$title]['tags'][] = $application_tag->ref('tag')['name'];
            }
        }

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

        $this->assertEquals($expected, $data);
    }

    public function testStructure()
    {
        $config = Config::builder($this->connection)
            ->setStructure(new SoftwareConventionStructure());

        $db = new Database($config);
        $maintainer = $db->row('application', 1)->ref('maintainer');
        $this->assertEquals('Jakub Vrana', $maintainer['name']);

        $data = [];
        foreach ($maintainer->related('application')->via('maintainer_id') as $application) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
        ], $data);
    }
}
