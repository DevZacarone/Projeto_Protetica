<?php
// Conexão única PDO (SQLite)
$DB_PATH = __DIR__ . '/data.sqlite';
$dsn = 'sqlite:' . $DB_PATH;
$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
?>