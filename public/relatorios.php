<?php
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/auth.php';
require_login();

// =====================
// PARÂMETROS DO PERÍODO
// =====================
$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim    = $_GET['fim']    ?? date('Y-m-d');

// =====================
// EXPORT CSV (antes de qualquer HTML!)
// =====================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

  // Consulta principal: aprovados no período + total recebido no caixa (agregado)
  $sql = $pdo->prepare("
    SELECT 
      o.id,
      o.total,
      o.criado_em,
      o.status,
      c.nome AS cliente,
      IFNULL(cm.total_caixa, 0) AS total_caixa
    FROM orcamentos o
    JOIN clientes c ON c.id = o.cliente_id
    LEFT JOIN (
      SELECT referencia, SUM(valor) AS total_caixa
      FROM caixa_movimentos
      WHERE tipo = 'entrada'
      GROUP BY referencia
    ) cm ON cm.referencia = 'orc:' || o.id
    WHERE DATE(o.criado_em) BETWEEN ? AND ?
      AND o.status = 'aprovado'
    ORDER BY o.id DESC
  ");
  $sql->execute([$inicio, $fim]);
  $rows = $sql->fetchAll(PDO::FETCH_ASSOC);

  // Limpa buffers e envia headers do CSV
  if (ob_get_level()) { ob_end_clean(); }
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="a_receber_'.$inicio.'_a_'.$fim.'.csv"');

  // BOM para Excel (acentos)
  echo "\xEF\xBB\xBF";

  $out = fopen('php://output','w');
  fputcsv($out, ['ID','Cliente','Criado em','Status','Total Aprovado','Recebido (Caixa)','Pendente'], ';');

  foreach ($rows as $r) {
    $recebido = (float)$r['total_caixa'];
    $pend = max(0, (float)$r['total'] - $recebido);
    fputcsv($out, [
      $r['id'],
      $r['cliente'],
      date('d/m/Y H:i', strtotime($r['criado_em'])),
      $r['status'],
      number_format((float)$r['total'], 2, ',', '.'),
      number_format($recebido,        2, ',', '.'),
      number_format($pend,            2, ',', '.')
    ], ';');
  }
  fclose($out);
  exit;
}

// =====================
// RENDERIZAÇÃO DA PÁGINA
// =====================
include __DIR__.'/../app/layout_header.php';
require __DIR__.'/../app/ui.php';

ui_header('Relatórios', 'Financeiro e recebíveis', true, [
  ['label'=>'Exportar CSV (A Receber)','href'=>'?inicio='.$inicio.'&fim='.$fim.'&export=csv','class'=>'btn btn-outline-primary']
]);

/*
  A Receber:
  - Orçamentos com status = 'aprovado' no período (pela data de criação).
  - Recebido: soma no caixa por referencia = 'orc:<id>' e tipo='entrada'.
*/

// Consulta principal (p/ tela) com total do caixa agregado
$sql = $pdo->prepare("
  SELECT 
    o.id,
    o.total,
    o.criado_em,
    o.status,
    c.nome AS cliente,
    IFNULL(cm.total_caixa, 0) AS total_caixa
  FROM orcamentos o
  JOIN clientes c ON c.id = o.cliente_id
  LEFT JOIN (
    SELECT referencia, SUM(valor) AS total_caixa
    FROM caixa_movimentos
    WHERE tipo = 'entrada'
    GROUP BY referencia
  ) cm ON cm.referencia = 'orc:' || o.id
  WHERE DATE(o.criado_em) BETWEEN ? AND ?
    AND o.status = 'aprovado'
  ORDER BY o.id DESC
");
$sql->execute([$inicio, $fim]);
$rows = $sql->fetchAll(PDO::FETCH_ASSOC);

// Sumarização
$tot_orc = 0.0;
$tot_rec = 0.0;
foreach ($rows as $r) {
  $tot_orc += (float)$r['total'];
  $tot_rec += (float)$r['total_caixa'];
}
$tot_pend = max(0, $tot_orc - $tot_rec);

// Caixa resumo do período
$sum = $pdo->prepare("
  SELECT
    IFNULL(SUM(CASE WHEN tipo='entrada' THEN valor ELSE 0 END),0) AS entradas,
    IFNULL(SUM(CASE WHEN tipo='saida'   THEN valor ELSE 0 END),0) AS saidas
  FROM caixa_movimentos 
  WHERE DATE(data) BETWEEN ? AND ?
");
$sum->execute([$inicio,$fim]);
$S = $sum->fetch(PDO::FETCH_ASSOC);
$saldo_periodo = (float)$S['entradas'] - (float)$S['saidas'];
?>
<div class="card card-body mb-4">
  <form class="row g-2 align-items-end">
    <div class="col"><label class="form-label">Início</label><input type="date" class="form-control" name="inicio" value="<?=$inicio?>"></div>
    <div class="col"><label class="form-label">Fim</label><input type="date" class="form-control" name="fim" value="<?=$fim?>"></div>
    <div class="col-auto"><button class="btn btn-outline-primary">Aplicar</button></div>
  </form>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card card-body">
      <h2 class="h6 mb-3">A Receber (orçamentos aprovados)</h2>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Cliente</th>
              <th>Criado</th>
              <th>Status</th>
              <th class="text-end">Total Aprovado</th>
              <th class="text-end">Recebido (Caixa)</th>
              <th class="text-end">Pendente</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$rows): ?>
              <tr><td colspan="7" class="text-center text-muted">Sem orçamentos aprovados no período.</td></tr>
            <?php else: foreach($rows as $r):
              $recebido = (float)$r['total_caixa'];
              $pend = max(0, (float)$r['total'] - $recebido);
              $badge = $recebido > 0
                ? '<span class="badge bg-success">Recebido</span>'
                : '<span class="badge bg-warning text-dark">Pendente</span>';
            ?>
              <tr class="<?=$recebido<=0?'table-warning':''?>">
                <td><?=h($r['id'])?></td>
                <td><?=h($r['cliente'])?></td>
                <td><?=h(date('d/m/Y H:i', strtotime($r['criado_em'])))?></td>
                <td><?=$badge?></td>
                <td class="text-end"><?=brl($r['total'])?></td>
                <td class="text-end"><?=brl($recebido)?></td>
                <td class="text-end"><?=brl($pend)?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="4" class="text-end">Totais</th>
              <th class="text-end"><?=brl($tot_orc)?></th>
              <th class="text-end"><?=brl($tot_rec)?></th>
              <th class="text-end"><?=brl($tot_pend)?></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="small text-muted">
        Vinculação com o Caixa: <code>referencia = "orc:&lt;ID&gt;"</code> (ex.: <code>orc:12</code>).
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-body">
      <h2 class="h6 mb-3">Resumo do Caixa (período)</h2>
      <ul class="list-group">
        <li class="list-group-item d-flex justify-content-between"><span>Entradas</span><strong><?=brl($S['entradas'])?></strong></li>
        <li class="list-group-item d-flex justify-content-between"><span>Saídas</span><strong><?=brl($S['saidas'])?></strong></li>
        <li class="list-group-item d-flex justify-content-between"><span>Saldo</span><strong><?=brl($saldo_periodo)?></strong></li>
      </ul>
      <a class="btn btn-outline-primary mt-3" href="<?=$BASE_URL?>/caixa.php?inicio=<?=$inicio?>&fim=<?=$fim?>">Ver movimentos</a>
      <a class="btn btn-dark mt-2" target="_blank" href="<?=$BASE_URL?>/pdf_caixa.php?inicio=<?=$inicio?>&fim=<?=$fim?>">PDF do Caixa</a>
    </div>
  </div>
</div>
<?php include __DIR__.'/../app/layout_footer.php';
