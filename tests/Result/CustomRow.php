<?php

namespace Grimoire\Test\Result;

use Grimoire\Result\Row;

class CustomRow extends Row
{
    function offsetExists($key): bool
    {
        return parent::offsetExists(preg_replace('~^test_~', '', $key));
    }

    #[\ReturnTypeWillChange]
    function offsetGet($key)
    {
        return parent::offsetGet(preg_replace('~^test_~', '', $key));
    }
}
