<?php

declare(strict_types=1);

namespace Grimoire\Util;

use Grimoire\Result\Result;

class ThenForeachHelper
{
    /** @var callable */
    protected $callback;

    function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Call callback for each row
     *
     * @param Result $result
     */
    function __invoke(Result $result): void
    {
        $callback = $this->callback;
        foreach ($result as $id => $row) {
            $callback($row, $id);
        }
    }
}
