<?php

namespace Grimoire\Test;

class ViaTest extends AbstractGrimoireTestCase
{

    public function testVia()
    {
        $data = [];
        foreach ($this->db->table('author') as $author) {
            $applications = $author->related('application')->via('maintainer_id');
            foreach ($applications as $application) {
                $data[] = $author['name'] . ': ' . $application['title'];
            }
        }

        $this->assertEquals([
            'Jakub Vrana: Adminer',
            'David Grudl: Nette',
            'David Grudl: Dibi',
        ], $data);

        $this->assertEquals('SELECT * FROM application WHERE (application.maintainer_id IN (11, 12))', $applications);
    }

    public function testRefViaColumn()
    {
        $data = [];
        foreach ($this->db->table('application') as $app) {
            $author = $app->ref('author', 'author_id');
            $maintainer = $app->ref('author', 'maintainer_id');
            $data[] = $app['title'] . ': ' . $author['name'] . ' - ' . ($maintainer['name'] ?? 'N/A');
        }

        $this->assertEquals([
            'Adminer: Jakub Vrana - Jakub Vrana',
            'JUSH: Jakub Vrana - N/A',
            'Nette: David Grudl - David Grudl',
            'Dibi: David Grudl - David Grudl',
        ], $data);
    }

}
