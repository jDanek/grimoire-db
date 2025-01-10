<?php

namespace Grimoire\Test\Helpers\Model;

use Grimoire\Model\NativeModel;

class AuthorModel extends NativeModel
{
    protected $table = 'author';

    public function test()
    {
        return 'asd';
    }
}
