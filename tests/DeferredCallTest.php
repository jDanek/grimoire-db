<?php

namespace Grimoire\Test;

use Grimoire\Database;

class DeferredCallTest extends AbstractGrimoireTestCase
{

    public function testThen()
    {
        $data = [
            'authors' => [],
            'tags' => []
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
                        $application_tag->ref('application'),
                        $application_tag->ref('tag'),
                        function ($application, $tag) use (&$data) {
                            $data['tags'][] = $application['title'] . ': ' . $tag['name'];
                        }
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
