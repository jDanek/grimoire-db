<?php

declare(strict_types=1);

namespace Grimoire\Util\QueryParam;

use Grimoire\Literal;

class ModifierParams implements QueryParamsInterface
{
    /** @var string */
    protected $query;
    /** @var array */
    protected $params;

    public function __construct(string $query, array $params = [])
    {
        $this->query = $query;
        $this->params = $params;
    }

    /**
     * @throws \Exception
     */
    public function resolve(): array
    {
        if ($this->hasModifiers()) {
            return $this->processModifiers();
        } else {
            return (new SimpleParams($this->query, ...$this->params))->resolve();
        }
    }

    protected function hasModifiers(): bool
    {
        return strpos($this->query, '%') !== false;
    }

    /**
     * @return array{query: string, types: string, params: array}
     * @throws \Exception
     */
    protected function processModifiers(): array
    {
        $finalQuery = '';
        $paramTypes = '';
        $finalParams = [];

        while (($pos = strpos($this->query, '%')) !== false) {
            $finalQuery .= substr($this->query, 0, $pos);
            $this->query = substr($this->query, $pos);

            if (preg_match('/^%[a-zA-Z]+/', $this->query, $matches)) {
                $modifier = $matches[0];
                $this->query = substr($this->query, strlen($modifier));

                if (empty($this->params)) {
                    throw new \Exception("Not enough parameters provided for query");
                }

                $value = array_shift($this->params);
                $finalQuery .= '?';

                // Determine the parameter type based on the modifier
                $paramTypes .= $this->getParamType($modifier);
                $finalParams[] = $this->applyModifier($modifier, $value);
            } else {
                throw new \Exception("Invalid modifier in query");
            }
        }

        $finalQuery .= $this->query;

        return [
            'query' => $finalQuery,
            'types' => $paramTypes,
            'params' => $finalParams,
        ];
    }

    protected function getParamType(string $modifier): string
    {
        switch ($modifier) {
            case '%s': // string
            case '%sN': // string, but '' is null
            case '%d': // date (DateTime|string|timestamp)
            case '%dt': // datetime (DateTime|string|timestamp)
            case '%n': // identifier (tablename or columnname)
            case '%N': // identifier, dot is escaped
            case '%SQL': // insert SQL string (Literal)
                return 's';
            case '%bin': // binary
                return 'b'; // blob (binary data)
            case '%iN': // integer, but 0 is null
            case '%i': // integer
            case '%b': // boolean
                return 'i'; // integer (for boolean, 0 or 1)
            case '%f': // float
                return 'd'; // double (float)
            default:
                return 's';
        }
    }

    /**
     * @param mixed $value
     * @return false|float|int|string|null
     */
    protected function applyModifier(string $modifier, $value)
    {
        switch ($modifier) {
            case '%s':
                return (string)$value;
            case '%sN':
                return $value === '' ? null : (string)$value;
            case '%bin':
                return $value;
            case '%b':
                return $value ? 1 : 0;
            case '%i':
                return (int)$value;
            case '%iN':
                return $value === 0 ? null : (int)$value;
            case '%f':
                return (float)$value;
            case '%d':
                return date('Y-m-d', strtotime($value));
            case '%dt':
                return date('Y-m-d H:i:s', strtotime($value));
            case '%n':
                return '`' . str_replace('`', '``', $value) . '`';
            case '%N':
                return '`' . str_replace('.', '`.`', str_replace('`', '``', $value)) . '`';
            case '%SQL':
                return new Literal($value);
            default:
                return (string)$value;
            //throw new \Exception("Unknown modifier: $modifier");
        }
    }

}
