<?php
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/auth.php';
require_login();
include __DIR__.'/../app/layout_header.php';
require __DIR__.'/../app/ui.php';

ui_header('Dashboard', 'Visão geral do sistema', false);

// KPIs
$kpis = [
  'clientes'   => (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn(),
  'orcamentos' => (int)$pdo->query("SELECT COUNT(*) FROM orcamentos")->fetchColumn(),
  'aprovados'  => (int)$pdo->query("SELECT COUNT(*) FROM orcamentos WHERE status='aprovado'")->fetchColumn(),
  'saldo'      => (float)$pdo->query("
                   SELECT IFNULL(SUM(CASE WHEN tipo='entrada' THEN valor ELSE -valor END),0)
                   FROM caixa_movimentos
                 ")->fetchColumn(),
  // Pendentes (status = enviado)
  'pendentes_qtd' => (int)$pdo->query("SELECT COUNT(*) FROM orcamentos WHERE status='enviado'")->fetchColumn(),
  'pendentes_sum' => (float)$pdo->query("SELECT IFNULL(SUM(total),0) FROM orcamentos WHERE status='enviado'")->fetchColumn(),
];

// últimos 5 orçamentos
$ultimos = $pdo->query("
  SELECT o.id, o.status, o.total, o.criado_em, c.nome AS cliente
  FROM orcamentos o
  JOIN clientes c ON c.id=o.cliente_id
  ORDER BY o.id DESC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="d-flex flex-wrap gap-3 mb-4">
  <div class="card card-kpi">
    <div class="card-body">
      <div class="kpi-label">Clientes</div>
      <div class="kpi-value"><?=h($kpis['clientes'])?></div>
    </div>
  </div>

  <div class="card card-kpi">
    <div class="card-body">
      <div class="kpi-label">Orçamentos</div>
      <div class="kpi-value"><?=h($kpis['orcamentos'])?></div>
    </div>
  </div>

  <div class="card card-kpi">
    <div class="card-body">
      <div class="kpi-label">Aprovados</div>
      <div class="kpi-value"><?=h($kpis['aprovados'])?></div>
    </div>
  </div>

  <div class="card card-kpi">
    <div class="card-body">
      <div class="kpi-label">Saldo do Caixa</div>
      <div class="kpi-value"><?=brl($kpis['saldo'])?></div>
    </div>
  </div>

  <!-- Novo KPI: Pendentes (status = enviado) -->
  <div class="card card-kpi">
    <div class="card-body">
      <div class="kpi-label">Pendentes</div>
      <div class="kpi-value"><?=h($kpis['pendentes_qtd'])?></div>
      <div class="small text-muted mt-1">Total: <strong><?=brl($kpis['pendentes_sum'])?></strong></div>
      <div class="mt-2">
        <a class="btn btn-sm btn-outline-primary" href="orcamentos.php?fstatus=enviado">Ver pendentes</a>
      </div>
    </div>
  </div>
</div>

<div class="card card-body">
  <h2 class="h6 mb-3">Últimos orçamentos</h2>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Cliente</th>
          <th>Criado</th>
          <th>Status</th>
          <th class="text-end">Total</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($ultimos as $o): ?>
        <tr>
          <td>#<?=h($o['id'])?></td>
          <td><?=h($o['cliente'])?></td>
          <td><?=h(date('d/m/Y H:i', strtotime($o['criado_em'])))?></td>
          <td><span class="badge status-<?=h($o['status'])?>"><?=h($o['status'])?></span></td>
          <td class="text-end"><?=brl($o['total'])?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="ver_orcamento.php?id=<?=$o['id']?>">Abrir</a></td>
        </tr>
        <?php endforeach; if(!$ultimos): ?>
        <tr><td colspan="6" class="text-center text-muted">Ainda sem orçamentos.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/../app/layout_footer.php';
