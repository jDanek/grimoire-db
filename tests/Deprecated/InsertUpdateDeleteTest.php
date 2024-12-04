<?php

namespace Grimoire\Test\Deprecated;

use Grimoire\Literal;
use Grimoire\Test\AbstractGrimoireTestCase;

class InsertUpdateDeleteTest extends AbstractGrimoireTestCase
{

    public function testUpdatePrimary()
    {
        $application = $this->db->table('tag')->insert(['id' => 25, 'name' => 'HTML']);
        $this->assertEquals(25, $application['id']);
        $application['id'] = 26;
        $this->assertEquals(26, $application['id']);
        $this->assertEquals(1, $application->update());
        $this->assertEquals(1, $application->delete());
    }

    public function testInsertUpdate()
    {
        $data = [];
        for ($i = 0; $i < 2; $i++) {
            $data[] = $this->db->table('application')->insertUpdate(
                ['id' => 5],
                ['author_id' => 12, 'title' => 'Texy', 'web' => "", 'slogan' => "$i"]
            );
        }
        $application = $this->db->row('application', 5);
        $data[] = $application->related('application_tag')->insertUpdate(
            ['tag_id' => 21],
            []
        );
        $this->db->table('application', ['id', 5])->delete();

        $this->assertEquals([
            1,
            2,
            1,
        ], $data);
    }

    public function testUpdate()
    {
        $id = 5; // auto_increment is disabled in demo
        $application = $this->db->table('application')->insert([
            'id' => $id,
            'author_id' => $this->db->row('author', 12),
            'title' => new Literal("'Texy'"), // or shorthand $db::literal("'Texy'")
            'web' => '',
            'slogan' => 'The best humane Web text generator',
        ]);
        $application_tag = $application->related('application_tag')->insert(['tag_id' => 21]);

        // retrieve the really stored value
        $application = $this->db->row('application', $id);
        $this->assertEquals('Texy', $application['title']);

        $application['web'] = "http://texy.info/";
        $this->assertEquals('1 row updated.', $application->update() . ' row updated.');
        $this->assertEquals('http://texy.info/', $this->db->row('application', $id)['web']);

        $this->db->table('application_tag', ['application_id', [5]])->delete(); // foreign keys may be disabled
        $this->assertEquals('1 row deleted.', $application->delete() . ' row deleted.');
        $this->assertEquals('0 rows found.', count($this->db->table('application', ['id', [$id]])) . ' rows found.');
    }

    public function testExtended()
    {
        $data = [];
        $application = $this->db->row('application', 3);
        $application->related('application_tag')->insert(['tag_id' => 22], ['tag_id' => 23]);
        foreach ($application->related('application_tag')->order("tag_id DESC") as $application_tag) {
            $data[] = $application_tag['application_id'] . ' ' . $application_tag['tag_id'];
        }
        $application->related('application_tag', ['tag_id', [22, 23]])->delete();

        $this->assertEquals([
            '3 23',
            '3 22',
            '3 21',
        ], $data);
    }
}
