<?php

declare(strict_types=1);

namespace Grimoire\Util;

use Grimoire\Literal;
use Grimoire\Result\Row;

class StringFormatter
{
    /** @var \Mysqli */
    private $connection;

    public function __construct(\Mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param mixed $val
     */
    public function quote($val): string
    {
        if (is_string($val) && empty($val)) {
            return "''";
        }

        if (!isset($val) || $val === null) {
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

        $val = $this->formatValue($val);
        if (is_float($val)) {
            return sprintf('%.14F', $val); // otherwise depends on set_locale()
        }

        if (is_numeric($val)) {
            $val = (0 + $val);

            if (is_int($val)) {
                return sprintf('%d', $val);
            }

            return sprintf('%.14F', $val);
        }

        if ($val instanceof Literal) { // number or SQL code - for example 'NOW()'
            return (string)$val;
        }

        if ($val instanceof Row) {
            $val = (string)$val;
        }

        return '\'' . $this->connection->real_escape_string($val) . '\'';
    }

    /**
     * @param mixed $val
     * @return float|int|string
     */
    public function formatValue($val)
    {
        if ($val instanceof \DateTime) {
            return $val->format('Y-m-d H:i:s');
        }

        if (is_array($val)) {
            return implode(',', $val);
        }

        return $val;
    }
}
