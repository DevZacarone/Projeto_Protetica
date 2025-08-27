<?php
require __DIR__.'/../app/db.php';
$id = (int)($_GET['id'] ?? 0);
$to = $_GET['to'] ?? '';
if(!in_array($to,['aprovado','recusado','enviado','rascunho'],true)){
http_response_code(400); echo 'Status inválido'; exit;
}
$st=$pdo->prepare("UPDATE orcamentos SET status=? WHERE id=?");
$st->execute([$to,$id]);
header('Location: ver_orcamento.php?id='.$id);