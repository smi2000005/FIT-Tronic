<?php
declare(strict_types=1);

/**
 * Settings shared with db.php. Edit ../config.php, not this file.
 */
function appConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config.php';
    }
    return $config;
}

function database(): PDO
{
    $config = appConfig();
    $host = (string)$config['host'];
    $port = (string)$config['port'];
    $name = (string)$config['name'];
    $user = (string)$config['user'];
    $password = (string)$config['pass'];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('The database name in config.php must contain only letters, numbers, and underscores.');
    }

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
