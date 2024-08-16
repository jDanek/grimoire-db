<?php

namespace Grimoire\Test\Model;

use Grimoire\Test\AbstractGrimoireTestCase;
use Grimoire\Test\Helpers\Model\Application;
use Grimoire\Test\Helpers\Model\ApplicationTag;
use Grimoire\Test\Helpers\Model\Author;
use Grimoire\Test\Helpers\Model\Tag;

class SimpleModelTest extends AbstractGrimoireTestCase
{

    public function testTableNameFromClass()
    {
        $applicationModel = new Application($this->db);
        $this->assertEquals('application', $applicationModel->getTableName());

        $applicationModelTag = new ApplicationTag($this->db);
        $this->assertEquals('application_tag', $applicationModelTag->getTableName());

        $authorModel = new Author($this->db);
        $this->assertEquals('author', $authorModel->getTableName());

        $tagModel = new Tag($this->db);
        $this->assertEquals('tag', $tagModel->getTableName());
    }

}
