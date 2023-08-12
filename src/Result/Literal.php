<?php
declare(strict_types=1);

namespace Grimoire\Result;

/**
 * SQL literal value
 */
class Literal
{
    /** @var string */
    protected $value = '';
    /** @var array */
    protected $parameters = [];

    /**
     * @param mixed ...$parameters parameter
     */
    public function __construct(string $value, ...$parameters)
    {
        $this->value = $value;
        $this->parameters = $parameters;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get literal value
     */
    public function __toString(): string
    {
        return $this->value;
    }

}
