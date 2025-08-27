<?php
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/auth.php';
require_login();
require __DIR__.'/../app/ui.php';

// Abre <html>/<head>/<body> e a navbar
include __DIR__.'/../app/layout_header.php';

$hoje   = date('Y-m-d');
$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim    = $_GET['fim']    ?? $hoje;

/* ===========================
   POST: create / update / delete
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action  = $_POST['action'] ?? 'create';

  // Normaliza "R$ 1.234,56" -> 1234.56
  $valorRaw = $_POST['valor'] ?? '0';
  $valorNum = (float)str_replace(',', '.', preg_replace('/[R$\.\s]/', '', $valorRaw));

  try {
    if ($action === 'create') {
      $st = $pdo->prepare("INSERT INTO caixa_movimentos (tipo, descricao, valor, data, referencia) VALUES (?,?,?,?,?)");
      $st->execute([post('tipo'), post('descricao'), $valorNum, post('data'), post('referencia')]);
      echo '<div class="alert alert-success">Lançamento adicionado!</div>';

    } elseif ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        $st = $pdo->prepare("UPDATE caixa_movimentos SET tipo=?, descricao=?, valor=?, data=?, referencia=? WHERE id=?");
        $st->execute([post('tipo'), post('descricao'), $valorNum, post('data'), post('referencia'), $id]);
        echo '<div class="alert alert-success">Lançamento atualizado!</div>';
      }

    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        $st = $pdo->prepare("DELETE FROM caixa_movimentos WHERE id=?");
        $st->execute([$id]);
        echo '<div class="alert alert-success">Lançamento excluído!</div>';
      }
    }
  } catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: '.h($e->getMessage()).'</div>';
  }
}

ui_header('Caixa', 'Entradas e saídas com saldo', true);

/* ===========================
   RESUMO E LISTAGEM
   =========================== */
$sum = $pdo->prepare("
  SELECT
    IFNULL(SUM(CASE WHEN tipo='entrada' THEN valor ELSE 0 END),0) AS entradas,
    IFNULL(SUM(CASE WHEN tipo='saida'   THEN valor ELSE 0 END),0) AS saidas
  FROM caixa_movimentos
  WHERE date(data) BETWEEN ? AND ?
");
$sum->execute([$inicio, $fim]);
$S = $sum->fetch(PDO::FETCH_ASSOC);
$saldo_periodo = (float)$S['entradas'] - (float)$S['saidas'];

$movs = $pdo->prepare("
  SELECT * FROM caixa_movimentos
  WHERE date(data) BETWEEN ? AND ?
  ORDER BY data DESC, id DESC
");
$movs->execute([$inicio, $fim]);
$movs = $movs->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card card-body">
      <h2 class="h6 mb-3">Novo Lançamento</h2>
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="create">
        <div class="mb-2">
          <label class="form-label">Data</label>
          <input type="date" name="data" class="form-control" value="<?=$hoje?>" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Tipo</label>
          <select class="form-select" name="tipo" required>
            <option value="entrada">Entrada</option>
            <option value="saida">Saída</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Descrição</label>
          <input name="descricao" class="form-control" placeholder="Ex.: Pagamento orçamento #12" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Valor</label>
          <input name="valor" class="form-control" placeholder="R$ 0,00" inputmode="decimal">
        </div>
        <div class="mb-2">
          <label class="form-label">Referência (opcional)</label>
          <input name="referencia" class="form-control" placeholder="#id do orçamento ou observação">
        </div>
        <button class="btn btn-primary" type="submit">Lançar</button>
      </form>
    </div>

    <div class="alert alert-info mt-3">
      <div><strong>Entradas:</strong> <?=brl($S['entradas'])?></div>
      <div><strong>Saídas:</strong> <?=brl($S['saidas'])?></div>
      <div><strong>Saldo do período:</strong> <?=brl($saldo_periodo)?></div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card card-body">
      <form class="row g-2 align-items-end mb-3" method="get">
        <div class="col">
          <label class="form-label">Início</label>
          <input type="date" class="form-control" name="inicio" value="<?=$inicio?>">
        </div>
        <div class="col">
          <label class="form-label">Fim</label>
          <input type="date" class="form-control" name="fim" value="<?=$fim?>">
        </div>
        <div class="col-auto">
          <button class="btn btn-outline-primary" type="submit">Filtrar</button>
        </div>
      </form>

      <div class="mb-3">
        <a class="btn btn-dark" target="_blank" href="<?=$BASE_URL?>/pdf_caixa.php?inicio=<?=$inicio?>&fim=<?=$fim?>">Baixar PDF do Caixa (período)</a>
      </div>

      <div class="table-responsive">
        <table class="table table-striped align-middle table-hover">
          <thead>
            <tr>
              <th>Data</th>
              <th>Tipo</th>
              <th>Descrição</th>
              <th class="text-end">Valor</th>
              <th class="text-center" style="width:160px">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($movs as $m): ?>
            <tr>
              <td><?=h(date('d/m/Y', strtotime($m['data'])))?></td>
              <td>
                <?php if($m['tipo']==='entrada'): ?>
                  <span class="badge bg-success">Entrada</span>
                <?php else: ?>
                  <span class="badge bg-danger">Saída</span>
                <?php endif; ?>
              </td>
              <td>
                <?=h($m['descricao'])?>
                <?php if(!empty($m['referencia'])): ?>
                  <small class="text-muted d-block"><?=h($m['referencia'])?></small>
                <?php endif; ?>
              </td>
              <td class="text-end"><?=($m['tipo']==='saida'?'−':'')?><?=brl($m['valor'])?></td>
              <td class="text-center">
                <!-- Editar (data formatada YYYY-MM-DD) -->
                <button
                  type="button"
                  class="btn btn-sm btn-primary me-1"
                  data-bs-toggle="modal"
                  data-bs-target="#editModal"
                  data-id="<?=$m['id']?>"
                  data-data="<?=h(date('Y-m-d', strtotime($m['data'])))?>"
                  data-tipo="<?=h($m['tipo'])?>"
                  data-descricao="<?=h($m['descricao'])?>"
                  data-valor="<?=h(number_format((float)$m['valor'], 2, ',', '.'))?>"
                  data-referencia="<?=h($m['referencia'])?>"
                >Editar</button>

                <!-- Excluir -->
                <form method="post" class="d-inline" onsubmit="return confirm('Excluir este lançamento?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?=$m['id']?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; if(!$movs): ?>
            <tr><td colspan="5" class="text-center text-muted">Sem movimentos no período.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Editar Lançamento (inicialmente dentro do main; JS vai mover para <body>) -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content" autocomplete="off">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title" id="editLabel">Editar Lançamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Data</label>
          <input type="date" class="form-control" name="data" id="edit-data" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Tipo</label>
          <select class="form-select" name="tipo" id="edit-tipo" required>
            <option value="entrada">Entrada</option>
            <option value="saida">Saída</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Descrição</label>
          <input class="form-control" name="descricao" id="edit-descricao" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Valor</label>
          <input class="form-control" name="valor" id="edit-valor" inputmode="decimal">
        </div>
        <div class="mb-2">
          <label class="form-label">Referência (opcional)</label>
          <input class="form-control" name="referencia" id="edit-referencia">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" type="submit">Salvar alterações</button>
      </div>
    </form>
  </div>
</div>

<script>
// 1) Reparenta o modal para <body> (evita ficar atrás do backdrop por causa do z-index do <main>)
document.addEventListener('DOMContentLoaded', function(){
  var modalEl = document.getElementById('editModal');
  if (modalEl && modalEl.parentElement && modalEl.parentElement.tagName.toLowerCase() !== 'body') {
    document.body.appendChild(modalEl);
  }

  // 2) Preenche campos ao abrir
  modalEl.addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    if (!btn) return;

    document.getElementById('edit-id').value         = btn.getAttribute('data-id') || '';
    document.getElementById('edit-data').value       = (btn.getAttribute('data-data') || '').slice(0,10);
    document.getElementById('edit-tipo').value       = btn.getAttribute('data-tipo') || '';
    document.getElementById('edit-descricao').value  = btn.getAttribute('data-descricao') || '';
    document.getElementById('edit-valor').value      = btn.getAttribute('data-valor') || '';
    document.getElementById('edit-referencia').value = btn.getAttribute('data-referencia') || '';
  });

  // 3) Define o favicon usando sua imagem em /assets/
  (function(){
    var link = document.createElement('link');
    link.rel = 'icon';
    link.href = '<?=$BASE_URL?>/assets/meuicone.png'; // troque pelo nome do seu arquivo em /assets/
    link.type = 'image/png';
    document.head.appendChild(link);
  })();
});
</script>

<?php include __DIR__.'/../app/layout_footer.php'; ?>
