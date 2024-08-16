<?php

namespace Grimoire\Test;

class PageTest extends AbstractGrimoireTestCase
{

    public function testPageFirstPageOneItem()
    {
        $numOfPages = 0;
        $tags = $this->db->table('tag')->page(1, 1, $numOfPages);

        $this->assertEquals(1, count($tags)); // one item on first page
        $this->assertEquals(4, $numOfPages); // four pages total

        // calling the same without the $numOfPages reference
        unset($tags);
        $tags = $this->db->table('tag')->page(1, 1);
        $this->assertEquals(1, count($tags)); // one item on first page
    }

    public function testPageSecondPageThreeItems()
    {
        $numOfPages = 0;
        $tags = $this->db->table('tag')->page(2, 3, $numOfPages);

        $this->assertEquals(1, count($tags)); // one item on second page
        $this->assertEquals(2, $numOfPages); // two pages total

        // calling the same without the $numOfPages reference
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
