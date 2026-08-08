<?php
function getConexion(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=127.0.0.1;dbname=emdb_academica;charset=utf8mb4';
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '-05:00'");
    }
    return $pdo;
}
