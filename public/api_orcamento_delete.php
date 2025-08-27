<?php
require __DIR__.'/../app/auth.php'; require_login();
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$csrf = $_POST['csrf'] ?? '';

if (!$id || !$csrf || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  http_response_code(400);
  echo "Requisição inválida.";
  exit;
}

// apaga (itens são deletados via ON DELETE CASCADE)
$st = $pdo->prepare("DELETE FROM orcamentos WHERE id = ?");
$st->execute([$id]);

$back = $_SERVER['HTTP_REFERER'] ?? ('./orcamentos.php?deleted=1');
header('Location: '.$back.(str_contains($back,'?')?'&':'?').'deleted=1');
exit;
