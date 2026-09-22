<?php
/* Koneksi PDO tunggal + helper query pendek. */

function cfg(?string $path = null, $default = null) {
  static $cfg = null;
  if ($cfg === null) {
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
      http_response_code(500);
      exit('config.php belum dibuat. Salin app/config.sample.php jadi app/config.php lalu isi datanya.');
    }
    $cfg = require $file;
  }
  if ($path === null) return $cfg;
  $cur = $cfg;
  foreach (explode('.', $path) as $key) {
    if (!is_array($cur) || !array_key_exists($key, $cur)) return $default;
    $cur = $cur[$key];
  }
  return $cur;
}

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  $d = cfg('db');
  $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";
  try {
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
  } catch (PDOException $e) {
    error_log('[lulus] koneksi DB gagal: ' . $e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Cek app/config.php.');
  }
  return $pdo;
}

function q(string $sql, array $args = []): PDOStatement {
  $st = db()->prepare($sql);
  $st->execute($args);
  return $st;
}

function one(string $sql, array $args = []) {
  $row = q($sql, $args)->fetch();
  return $row === false ? null : $row;
}

function all(string $sql, array $args = []): array {
  return q($sql, $args)->fetchAll();
}
