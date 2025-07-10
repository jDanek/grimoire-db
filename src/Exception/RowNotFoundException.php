<?php

namespace Grimoire\Exception;

class RowNotFoundException extends \RuntimeException
{
    public function __construct(string $table, $id = null)
    {
        $message = "Row not found in table '{$table}'";
        if ($id !== null) {
            $message .= " with ID '{$id}'";
        }
        parent::__construct($message);
    }
}
