<?php
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/config.php';
require __DIR__.'/../app/settings.php';
require __DIR__.'/../vendor/autoload.php';
use Dompdf\Dompdf;

$CLINIC = setting_get('clinic_name','Protética Rafael Borsato');
$PINK   = setting_get('brand_primary','#FFC1E3');
$GOLD   = setting_get('brand_accent','#D4AF37');

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim    = $_GET['fim']    ?? date('Y-m-d');

// Resumos
$sum = $pdo->prepare("
  SELECT
    IFNULL(SUM(CASE WHEN tipo='entrada' THEN valor ELSE 0 END),0) AS entradas,
    IFNULL(SUM(CASE WHEN tipo='saida'   THEN valor ELSE 0 END),0) AS saidas
  FROM caixa_movimentos
  WHERE date(data) BETWEEN ? AND ?
");
$sum->execute([$inicio,$fim]);
$S = $sum->fetch(PDO::FETCH_ASSOC);
$saldo = (float)$S['entradas'] - (float)$S['saidas'];

// Movimentos
$movs = $pdo->prepare("
  SELECT * FROM caixa_movimentos
  WHERE date(data) BETWEEN ? AND ?
  ORDER BY data ASC, id ASC
");
$movs->execute([$inicio,$fim]);
$movs = $movs->fetchAll(PDO::FETCH_ASSOC);

// Tabela
$rows = '';
foreach($movs as $m){
  $val = ($m['tipo']==='saida' ? -1 : 1) * (float)$m['valor'];
  $rows .= '<tr>'.
    '<td>'.h(date('d/m/Y', strtotime($m['data']))).'</td>'.
    '<td>'.h(ucfirst($m['tipo'])).'</td>'.
    '<td>'.h($m['descricao']).' <small class="muted">'.h($m['referencia']).'</small></td>'.
    '<td class="num">'.brl($val).'</td>'.
  '</tr>';
}

// >>> Calcule antes e use variáveis simples no HEREDOC <<<
$PERIODO   = h(date('d/m/Y',strtotime($inicio)) . ' a ' . date('d/m/Y',strtotime($fim)));
$ENTRADAS  = h(brl($S['entradas']));
$SAIDAS    = h(brl($S['saidas']));
$SALDO     = h(brl($saldo));
$CLINIC_H  = h($CLINIC);

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
      <h1>Relatório do Caixa</h1>
      <div class="muted">Clínica: {$CLINIC_H}</div>
      <div class="muted">Período: {$PERIODO}</div>
    </div>
    <div class="muted right">
      <div><strong>Entradas:</strong> {$ENTRADAS}</div>
      <div><strong>Saídas:</strong> {$SAIDAS}</div>
      <div><strong>Saldo:</strong> {$SALDO}</div>
    </div>
  </div>
  <table>
    <thead>
      <tr><th>Data</th><th>Tipo</th><th>Descrição</th><th class="num">Valor (+/−)</th></tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
  </table>
</body>
</html>
HTML;

$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('caixa_'.$inicio.'_a_'.$fim.'.pdf', ['Attachment' => true]);
