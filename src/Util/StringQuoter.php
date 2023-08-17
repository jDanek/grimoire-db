<?php

declare(strict_types=1);

namespace Grimoire\Util;

use Grimoire\Literal;

class StringQuoter
{
    /** @var \Mysqli */
    private $connection;

    public function __construct(\Mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param mixed $val
     * @return string
     */
    public function quote($val): string
    {
        if (!isset($val) || $val == null) {
            return 'NULL';
        }

        if (is_bool($val)) {
            return $val ? '1' : '0';
        }

        if (is_array($val)) { // (a, b) IN ((1, 2), (3, 4))
            return '(' . implode(', ', array_map([$this, 'quote'], $val)) . ')';
        }

        if ($val instanceof \DateTime) {
            $val = $val->format('Y-m-d H:i:s'); //! may be driver specific
        }

        if (is_int($val)) {
            return sprintf('%d', $val);
        }

        if (is_float($val)) {
            return sprintf('%.14F', $val); // otherwise depends on set_locale()
        }

        if ($val instanceof Literal) { // number or SQL code - for example 'NOW()'
            return (string)$val;
        }

        return '\'' . $this->connection->real_escape_string($val) . '\'';
    }
}
