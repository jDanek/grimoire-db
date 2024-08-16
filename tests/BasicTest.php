<?php

namespace Grimoire\Test;

class BasicTest extends AbstractGrimoireTestCase
{

    public function testBasic()
    {
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
        foreach ($this->db->table('application') as $app) {
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
}
