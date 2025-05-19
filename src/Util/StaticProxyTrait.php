<?php

declare(strict_types=1);

namespace Grimoire\Util;

trait StaticProxyTrait
{
    /** @var array */
    private static $instances = [];

    /**
     * Returns or creates a singleton instance of the model for a static call
     * @return static
     */
    public static function proxy()
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    /**
     * Creates a new, independent instance of the model (for cases when we don't want to use a singleton)
     * @return static
     */
    public static function new()
    {
        return new static();
    }
}
