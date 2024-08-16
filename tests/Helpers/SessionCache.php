<?php

declare(strict_types=1);

namespace Grimoire\Test\Helpers;

use Psr\SimpleCache\CacheInterface;

class SessionCache implements CacheInterface
{
    /** @var string */
    private $sessionKey = '';

    public function __construct(string $key, bool $autostart = false)
    {
        $this->sessionKey = $key;
        if ($autostart === true && (session_status() === PHP_SESSION_NONE)) {
            session_start();
        }
    }

    public function get($key, $default = null)
    {
        return $_SESSION[$this->sessionKey][$key] ?? $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $_SESSION[$this->sessionKey][$key] = $value;
        return ($_SESSION[$this->sessionKey][$key] === $value);
    }

    public function delete($key): bool
    {
        unset($_SESSION[$this->sessionKey][$key]);
        return !isset($_SESSION[$this->sessionKey][$key]);
    }

    public function clear(): bool
    {
        unset($_SESSION[$this->sessionKey]);
        return !isset($_SESSION[$this->sessionKey]);
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return $_SESSION[$this->sessionKey] ?? [];
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $_SESSION[$this->sessionKey] = $values;

        return empty(
        array_diff(
            (array)$values,
            (array)$_SESSION[$this->sessionKey]
        )
        );
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            unset($_SESSION[$this->sessionKey][$key]);
        }
        $diff = array_intersect_key(array_flip((array)$keys), $_SESSION[$this->sessionKey]);
        return count($diff) !== count((array)$keys);
    }

    public function has($key): bool
    {
        return isset($_SESSION[$this->sessionKey][$key]);
    }
}
