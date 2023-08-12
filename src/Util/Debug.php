<?php

declare(strict_types=1);

namespace Grimoire\Util;

class Debug
{
    /**
     * Composes the final form of the query for debugging,
     * replacing question marks (?) with values from the array.
     * Mysqli doesn't support named parameters
     */
    public static function composeQuery(string $query, array $params = []): string
    {
        if (count($params) > 0) {
            // convert params
            $params = array_map(function ($value) {
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }
                return $value;
            }, $params);

            foreach ($params as $val) {
                $query = preg_replace('/\?/', $val, $query, 1);
            }
        }
        return $query;
    }
}
