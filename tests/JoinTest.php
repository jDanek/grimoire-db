<?php

namespace Grimoire\Test;

class JoinTest extends AbstractGrimoireTestCase
{

    public function testJoin()
    {
        $data = [];
        foreach ($this->db->table('application')->order('author.name, title') as $application) {
            $data[] = $application->ref('author')['name'] . ': ' . $application['title'];
        }

        foreach (
            $this->db->table('application_tag', ['application.author.name', ['Jakub Vrana']])
                ->group('application_tag.tag_id') as $application_tag
        ) {
            $data[] = $application_tag->ref('tag')['name'];
        }

        $this->assertEquals([
            'David Grudl: Dibi',
            'David Grudl: Nette',
            'Jakub Vrana: Adminer',
            'Jakub Vrana: JUSH',
            'PHP',
            'MySQL',
            'JavaScript',
        ], $data);
    }

    public function testBackJoin()
    {
        $data = [];
        foreach (
            $this->db->table('author')
                ->select('author.*, COUNT(DISTINCT application:application_tag:tag_id) AS tags')
                ->group('author.id')
                ->order('tags DESC') as $autor
        ) {
            $data[] = $autor['name'] . ': ' . $autor['tags'];
        }

        $this->assertEquals([
            'Jakub Vrana: 3',
            'David Grudl: 2',
        ], $data);
    }
}
