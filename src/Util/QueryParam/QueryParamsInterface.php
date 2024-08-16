<?php

namespace Grimoire\Util\QueryParam;

interface QueryParamsInterface
{

    public function __construct(string $query, array $params = []);

    /**
     * @return array{
     *     query: string,
     *     types: string,
     *     params: array
     * }
     */
    public function resolve(): array;
}
