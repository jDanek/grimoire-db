<?php

namespace Grimoire\Test;

class PageTest extends AbstractGrimoireTestCase
{

    public function testPageFirstPageOneItem()
    {
        $totals = [
            'total_pages' => 0,
            'total_items' => 0
        ];
        $tags = $this->db->table('tag')->page(1, 1, $totals);

        $this->assertEquals(1, count($tags)); // one item on first page
        $this->assertEquals(4, $totals['total_pages']); // four pages total
        $this->assertEquals(4, $totals['total_items']); // four items total

        // calling the same without the $totals reference
        unset($tags);
        $tags = $this->db->table('tag')->page(1, 1);
        $this->assertEquals(1, count($tags)); // one item on first page
    }

    public function testPageSecondPageThreeItems()
    {
        $totals = [
            'total_pages' => 0,
            'total_items' => 0
        ];
        $tags = $this->db->table('tag')->page(2, 3, $totals);

        $this->assertEquals(1, count($tags)); // one item on second page
        $this->assertEquals(2, $totals['total_pages']); // two pages total
        $this->assertEquals(4, $totals['total_items']); // four items total

        // calling the same without the $totals reference
        unset($tags);
        $tags = $this->db->table('tag')->page(2, 3);
        $this->assertEquals(1, count($tags)); // one item on second page
    }

    public function testPageNoItems()
    {
        // page with no items
        $tags = $this->db->table('tag')->page(10, 4);
        $this->assertEquals(0, count($tags)); // one item on second page
    }

    public function testPageNoItemsPageNotInRange()
    {
        // page with no items (page not in range)
        $tags = $this->db->table('tag')->page(100, 4);
        $this->assertEquals(0, count($tags)); // one item on second page
    }

    public function testPageLessItemsThenItemsPerPage()
    {
        // less items than $itemsPerPage
        $tags = $this->db->table('tag')->page(1, 100);
        $this->assertEquals(4, count($tags)); // all four items from db
    }

}
