<?php
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/config.php';
require __DIR__.'/../app/settings.php';   // <- usa as configurações (nome/cores)
require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;

// Marca (defaults para sua identidade)
$CLINIC_NAME = setting_get('clinic_name', 'Protética Rafael Borsato');
$PINK = setting_get('brand_primary', '#FFC1E3');   // rosa claro
$GOLD = setting_get('brand_accent',  '#D4AF37');   // dourado

// Dados do orçamento
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("
  SELECT o.*, c.nome, c.telefone, c.email
  FROM orcamentos o
  JOIN clientes c ON c.id = o.cliente_id
  WHERE o.id = ?
");
$st->execute([$id]);
$orc = $st->fetch(PDO::FETCH_ASSOC);
if (!$orc) { http_response_code(404); echo 'Orçamento não encontrado'; exit; }

$itens = $pdo->query("SELECT * FROM orcamento_itens WHERE orcamento_id = $id")->fetchAll(PDO::FETCH_ASSOC);

// Linhas da tabela
$rows = '';
foreach ($itens as $i) {
  $rows .= '<tr>'.
             '<td>'.h($i['descricao']).'</td>'.
             '<td class="num">'.h($i['quantidade']).'</td>'.
             '<td class="num">'.brl($i['preco']).'</td>'.
             '<td class="num">'.brl($i['quantidade'] * $i['preco']).'</td>'.
           '</tr>';
}

$cliente = h($orc['nome']);
$contato = h($orc['telefone'] ?: ($orc['email'] ?: '-'));
$data    = date('d/m/Y', strtotime($orc['criado_em']));
$status  = h($orc['status']);
$total   = brl($orc['total']);

// HTML do PDF
$html = <<<HTML
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<style>
  body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color:#222; }
  h1{ font-size: 18px; margin: 0 0 10px; }
  .muted{ color:#555; }
  table{ width:100%; border-collapse: collapse; }
  th,td{ border:1px solid #ccc; padding:6px; }
  th{ background:#f2f2f2; }
  .num{ text-align:right; }
  .topbar{
    display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;
    background: linear-gradient(90deg, {$PINK}, {$GOLD});
    padding:10px 12px; border-radius:8px; color:#2a2a2a;
  }
  .right{text-align:right;}
</style>
</head>
<body>
  <div class="topbar">
    <div>
      <h1>Orçamento #{$id}</h1>
      <div class="muted">Clínica: {$CLINIC_NAME}</div>
      <div class="muted">Status: {$status}</div>
    </div>
    <div class="muted right">
      <div><strong>Cliente:</strong> {$cliente}</div>
      <div><strong>Contato:</strong> {$contato}</div>
      <div><strong>Data:</strong> {$data}</div>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>Descrição</th><th class="num">Qtd</th><th class="num">Preço</th><th class="num">Subtotal</th></tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
    <tfoot>
      <tr><th colspan="3" class="num">Total</th><th class="num">{$total}</th></tr>
    </tfoot>
  </table>
</body>
</html>
HTML;

// Gerar PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('orcamento_'.$id.'.pdf', ['Attachment' => true]);
