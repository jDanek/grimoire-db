<?php

declare(strict_types=1);

namespace Grimoire\Util\QueryParam;

class NamedParams implements QueryParamsInterface
{
    /** @var string */
    protected $query;
    /** @var array */
    protected $params;

    public function __construct(string $query, array $params = [])
    {
        // Zkontrolujeme, zda dotaz neobsahuje otazníky nebo modifikátory
        if (strpos($query, '?') !== false || strpos($query, '%') !== false) {
            throw new \Exception('Query contains question marks or modifiers. Use named parameters only.');
        }

        $this->query = $query;
        $this->params = $params;
    }

    /**
     * @throws \Exception
     */
    public function resolve(): array
    {
        $converted = $this->convertNamedParams();

        return [
            'query' => $converted['query'],
            'types' => str_repeat('s', count($converted['params'])),
            'params' => $converted['params'],
        ];
    }

    protected function convertNamedParams(): array
    {
        $convertedQuery = $this->query;
        $convertedParams = [];

        // Regex to match named parameters like :param_name
        $pattern = '/:(\w+)/';

        // Callback function to replace named params with ?
        $convertedQuery = preg_replace_callback($pattern, function ($matches) use (&$convertedParams) {
            $paramName = $matches[1];

            if (!array_key_exists($paramName, $this->params)) {
                throw new \Exception('Missing parameter: ' . $paramName);
            }

            // Add the corresponding parameter to the convertedParams array
            $convertedParams[] = $this->params[$paramName];

            // Replace the named parameter with a ?
            return '?';
        }, $convertedQuery);

        return [
            'query' => $convertedQuery,
            'params' => $convertedParams
        ];
    }

}
