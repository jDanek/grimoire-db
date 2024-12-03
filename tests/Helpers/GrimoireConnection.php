<?php

namespace Grimoire\Test\Helpers;

use Grimoire\Config;
use Grimoire\Database;

class GrimoireConnection
{
    /** @var \Mysqli|null */
    private static $mysqli = null;
    /** @var Database */
    private static $connection = null;

    public static function getMysql(): \Mysqli
    {
        if (self::$mysqli === null) {
            try {
                $mysql_host = 'localhost';
                $mysql_database = 'test_grimoire_db';
                $mysql_user = 'root';
                $mysql_password = '';

                $conn = new \Mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_database);
                if ($conn->connect_error) {
                    throw new \Exception("Connection failed: " . $conn->connect_error);
                }
                self::$mysqli = $conn;
            } catch (\Exception $e) {
                die("Database connection error: " . $e->getMessage());
            }
        }

        return self::$mysqli;
    }

    public static function getConnection(): Database
    {
        if (self::$connection === null) {
            try {
                $conn = self::getMysql();
                $config = Config::builder($conn)
                    ->setDebug(false);

                self::$connection = new Database($config);
            } catch (\Exception $e) {
                die("Database connection error: " . $e->getMessage());
            }
        }

        return self::$connection;
    }
}
