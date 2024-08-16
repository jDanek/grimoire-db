<?php

declare(strict_types=1);

namespace Grimoire\Util\QueryParam;

class SimpleParams implements QueryParamsInterface
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

    public function resolve(): array
    {
        return [
            'query' => $this->query,
            'types' => str_repeat('s', count($this->params)),
            'params' => $this->params
        ];
    }
}
