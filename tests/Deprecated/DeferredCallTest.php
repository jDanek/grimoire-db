<?php

namespace Grimoire\Test\Deprecated;

use Grimoire\Database;
use Grimoire\Test\AbstractGrimoireTestCase;

class DeferredCallTest extends AbstractGrimoireTestCase
{

    public function testThen()
    {
        $data = [
            'authors' => [],
            'tags' => [],
        ];

        $db = $this->db;
        Database::then(function () use ($db, &$data) {
            $db->table('author')
                ->order("id")
                ->then(function ($authors) use (&$data) {
                    if (count($authors) > 0) {
                        foreach ($authors as $author) {
                            $data['authors'][] = $author['name'];
                        }
                    }
                });

            $db->table('application_tag')
                ->order("application_id, tag_id")
                ->thenForeach(function ($application_tag) use (&$data) {
                    Database::then(
                        function ($application, $tag) use (&$data) {
                            $data['tags'][] = $application['title'] . ': ' . $tag['name'];
                        },
                        $application_tag->ref('application'),
                        $application_tag->ref('tag')
                    );
                });
        });

        $this->assertEquals([
            'authors' => [
                'Jakub Vrana',
                'David Grudl',
            ],
            'tags' => [
                'Adminer: PHP',
                'Adminer: MySQL',
                'JUSH: JavaScript',
                'Nette: PHP',
                'Dibi: PHP',
                'Dibi: MySQL',
            ],
        ], $data);
    }
}
