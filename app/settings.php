<?php
require_once __DIR__ . '/db.php';

function settings_ensure(): void {
  global $pdo;
  $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
  )");
}

function setting_get(string $key, string $default = ''): string {
  global $pdo;
  settings_ensure();
  $st = $pdo->prepare("SELECT value FROM settings WHERE key=?");
  $st->execute([$key]);
  $v = $st->fetchColumn();
  return ($v === false) ? $default : $v;
}

function setting_set(string $key, string $value): void {
  global $pdo;
  settings_ensure();
  // Compatível com qualquer SQLite do XAMPP
  $st = $pdo->prepare("INSERT OR REPLACE INTO settings(key,value) VALUES(?,?)");
  $st->execute([$key,$value]);
}
