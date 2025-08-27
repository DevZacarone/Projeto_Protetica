<?php
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/auth.php';

// --- sessão e CSRF ANTES de imprimir qualquer HTML ---
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_login();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

// ----------------------------------------------
// Helpers
// ----------------------------------------------
/**
 * Converte texto BRL/decimal para float de forma robusta.
 * Ex.: "R$ 2.345,67" -> 2345.67
 */
function brl_to_float($s){
  $s = (string)$s;
  // remove tudo que não for dígito, vírgula, ponto ou sinal
  $s = preg_replace('/[^\d,.\-]+/u', '', $s);
  // remove separadores de milhar (ponto) e converte vírgula para ponto
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  if ($s === '' || $s === '-' || $s === '.' ) return 0.0;
  return (float)$s;
}

// ----------------------------------------------
// HANDLERS (antes de qualquer saída HTML)
// ----------------------------------------------

$flash_html = '';

// --- CRIAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_orc'])) {
  try {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    if ($cliente_id <= 0) { throw new Exception('Selecione um cliente.'); }

    $desc = $_POST['descricao']  ?? [];
    $qtd  = $_POST['quantidade'] ?? [];
    $pre  = $_POST['preco']      ?? [];

    $desconto  = isset($_POST['desconto'])  ? brl_to_float($_POST['desconto'])  : 0.0;
    $acrescimo = isset($_POST['acrescimo']) ? brl_to_float($_POST['acrescimo']) : 0.0;

    $pdo->beginTransaction();
    $st = $pdo->prepare("INSERT INTO orcamentos(cliente_id,status,total) VALUES(?, 'enviado', 0)");
    $st->execute([$cliente_id]);
    $orc_id = (int)$pdo->lastInsertId();

    $sti = $pdo->prepare("INSERT INTO orcamento_itens(orcamento_id,descricao,quantidade,preco) VALUES(?,?,?,?)");

    $total_itens = 0.0;
    $n = max(count($desc), count($qtd), count($pre));
    for ($i = 0; $i < $n; $i++) {
      $d = trim((string)($desc[$i] ?? ''));
      if ($d === '') continue;

      $q = brl_to_float($qtd[$i] ?? '1');
      $p = brl_to_float($pre[$i] ?? '0');

      if ($q <= 0) $q = 1;
      if ($p < 0)  $p = 0;

      $total_itens += $q * $p;
      $sti->execute([$orc_id, $d, $q, $p]);
    }

    $total_final = max(0, $total_itens - $desconto + $acrescimo);
    $up = $pdo->prepare("UPDATE orcamentos SET total=? WHERE id=?");
    $up->execute([$total_final, $orc_id]);

    $pdo->commit();
    $flash_html .= '<div class="alert alert-success">Orçamento criado! <a href="ver_orcamento.php?id='.$orc_id.'" class="alert-link">Abrir</a></div>';
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $flash_html .= '<div class="alert alert-danger">Erro ao criar orçamento: '.h($e->getMessage()).'</div>';
  }
}

// --- STATUS (com integração ao Caixa ao aprovar) ---
if (isset($_GET['status'], $_GET['id'])) {
  $valid = ['enviado','aprovado','recusado','rascunho'];
  $to = $_GET['status'];
  $id = (int)$_GET['id'];

  if (in_array($to, $valid, true) && $id > 0) {
    try {
      $pdo->beginTransaction();

      // Atualiza status do orçamento
      $st = $pdo->prepare("UPDATE orcamentos SET status=? WHERE id=?");
      $st->execute([$to, $id]);

      // Se aprovou, lança no Caixa (evita duplicar por referencia 'orc:<ID>')
      if ($to === 'aprovado') {
        // Busca total e cliente
        $info = $pdo->prepare("SELECT o.total, c.nome AS cliente 
                               FROM orcamentos o 
                               JOIN clientes c ON c.id = o.cliente_id
                               WHERE o.id=?");
        $info->execute([$id]);
        $I = $info->fetch(PDO::FETCH_ASSOC);

        $valor = isset($I['total']) ? (float)$I['total'] : 0.0;
        if ($valor > 0) {
          $ref = 'orc:'.$id;

          // Já existe lançamento para este orçamento?
          $chk = $pdo->prepare("SELECT id FROM caixa_movimentos WHERE referencia = ? LIMIT 1");
          $chk->execute([$ref]);

          if (!$chk->fetch()) {
            $descricao = "Orçamento #$id aprovado — ".($I['cliente'] ?? 'Cliente');
            $ins = $pdo->prepare("INSERT INTO caixa_movimentos (tipo, descricao, valor, data, referencia) 
                                  VALUES ('entrada', ?, ?, ?, ?)");
            $ins->execute([$descricao, $valor, date('Y-m-d'), $ref]);
          }
        }

        // Redireciona para a tela do Caixa já no período do mês atual
        $inicioMes = date('Y-m-01');
        $hoje      = date('Y-m-d');
        // $BASE_URL deve estar definido pelo seu config; se não, ajuste para o caminho correto:
        $dest = (isset($BASE_URL) ? $BASE_URL : '..') . "/caixa.php?inicio={$inicioMes}&fim={$hoje}";
        $pdo->commit();
        header("Location: {$dest}");
        exit;
      }

      $pdo->commit();
      // Para outras trocas de status, apenas recarrega a própria página (evita reenvio)
      header("Location: ".$_SERVER['PHP_SELF']."?msg=status_ok");
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $flash_html .= '<div class="alert alert-danger">Erro ao atualizar status: '.h($e->getMessage()).'</div>';
    }
  }
}

// ----------------------------------------------
// VIEW
// ----------------------------------------------
include __DIR__ . '/../app/layout_header.php';
require __DIR__ . '/../app/ui.php';

ui_header('Orçamentos', 'Crie e acompanhe orçamentos', true);

// alerta de exclusão
if (($_GET['deleted'] ?? '') === '1') {
  $flash_html .= '<div class="alert alert-success">Orçamento excluído com sucesso.</div>';
}
echo $flash_html;

// --- LISTAS ---
$clientes = $pdo->query("SELECT id,nome FROM clientes ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$busca = trim($_GET['q'] ?? '');
$stf   = trim($_GET['fstatus'] ?? '');

$sql = "
  SELECT o.*, c.nome AS cliente
  FROM orcamentos o
  JOIN clientes c ON c.id = o.cliente_id
";
$where = []; $params = [];
if ($busca !== '') {
  $where[] = "(c.nome LIKE ? OR o.id = ?)";
  $params[] = "%$busca%";
  $params[] = ctype_digit($busca) ? (int)$busca : 0;
}
if ($stf !== '' && in_array($stf, ['enviado','aprovado','recusado','rascunho'], true)) {
  $where[] = "o.status = ?";
  $params[] = $stf;
}
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY o.id DESC";

$orc_list = $pdo->prepare($sql);
$orc_list->execute($params);
$orc_list = $orc_list->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="row g-4">
  <div class="col-lg-6" id="novo">
    <div class="card card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 m-0">Novo Orçamento</h2>
        <a class="btn btn-light btn-sm" href="#lista">Ir para lista</a>
      </div>
      <?php if (!$clientes): ?>
        <div class="alert alert-warning">Você ainda não tem clientes. <a class="alert-link" href="clientes.php">Cadastre um cliente</a>.</div>
      <?php else: ?>
      <form method="post" id="form-orc">
        <input type="hidden" name="criar_orc" value="1">
        <div class="mb-2">
          <label class="form-label">Cliente</label>
          <select name="cliente_id" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach ($clientes as $cl): ?>
              <option value="<?=$cl['id']?>"><?=h($cl['nome'])?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Presets de serviços: puxa automático ao selecionar -->
        <div class="d-flex gap-2 align-items-center mb-2">
          <select id="preset" class="form-select" onchange="applyPresetToRow()">
            <option value="">Serviços rápidos…</option>
            <option value="Moldeira individual|20">Moldeira individual — 20</option>
            <option value="Montagem de protocolo|250">Montagem de protocolo — 250</option>
            <option value="Montagem de PT|150">Montagem de PT — 150</option>
            <option value="Acrilização PT|100">Acrilização PT — 100</option>
            <option value="Acrilização protocolo|150">Acrilização protocolo — 150</option>
            <option value="Protocolo carga imediata|180">Protocolo carga imediata — 180</option>
            <option value="Protocolo carga tardia|150">Protocolo carga tardia — 150</option>
            <option value="Protocolo carga imediata provisório|100">Protocolo carga imediata provisório — 100</option>
            <option value="PT imediata|80">PT imediata — 80</option>
            <option value="PT e PPR definitiva|80">PT e PPR definitiva — 80</option>
            <option value="Protocolo provisório tardio|70">Protocolo provisório tardio — 70</option>
            <option value="PT prov. tardia|50">PT prov. tardia — 50</option>
            <option value="Conserto|30">Conserto — 30</option>
          </select>
          <!-- opcional: ainda permite adicionar como nova linha diretamente -->
          <button type="button" class="btn btn-outline-secondary" onclick="addPreset()">Adicionar</button>
        </div>

        <div id="itens">
          <div class="row g-2 align-items-end mb-2 item">
            <div class="col-6">
              <label class="form-label">Descrição</label>
              <input name="descricao[]" class="form-control" placeholder="Ex.: Coroa metalocerâmica">
            </div>
            <div class="col-3">
              <label class="form-label">Qtd</label>
              <input name="quantidade[]" class="form-control qtd" value="1" inputmode="decimal">
            </div>
            <div class="col-3">
              <label class="form-label">Preço</label>
              <div class="input-group">
                <input name="preco[]" class="form-control preco" placeholder="0,00" inputmode="decimal">
                <button type="button" class="btn btn-outline-danger remove" title="Remover item">✕</button>
              </div>
            </div>
          </div>
        </div>

        <button type="button" class="btn btn-outline-secondary mb-2" onclick="addItem()">+ Item</button>

        <div class="row g-2 mt-2">
          <div class="col-md-6">
            <label class="form-label">Desconto</label>
            <input name="desconto" id="desconto" class="form-control preco" placeholder="R$ 0,00">
          </div>
          <div class="col-md-6">
            <label class="form-label">Acréscimo</label>
            <input name="acrescimo" id="acrescimo" class="form-control preco" placeholder="R$ 0,00">
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="small text-muted">
            <div>Itens: <span id="totItens">R$ 0,00</span></div>
            <div>Final: <strong id="totFinal">R$ 0,00</strong></div>
          </div>
          <button class="btn btn-primary">Criar & Enviar</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6" id="lista">
    <div class="card card-body">
      <div class="row g-2 align-items-center mb-3">
        <div class="col">
          <form class="row g-2">
            <div class="col-7">
              <input class="form-control" name="q" value="<?=h($busca)?>" placeholder="Buscar por cliente ou #id">
            </div>
            <div class="col-3">
              <select name="fstatus" class="form-select">
                <option value="">Todos</option>
                <?php
                  $opts = ['enviado'=>'Enviado','aprovado'=>'Aprovado','recusado'=>'Recusado','rascunho'=>'Rascunho'];
                  foreach ($opts as $k=>$v){
                    $sel = $stf===$k ? 'selected' : '';
                    echo "<option value=\"$k\" $sel>$v</option>";
                  }
                ?>
              </select>
            </div>
            <div class="col-2">
              <button class="btn btn-outline-primary w-100">Filtrar</button>
            </div>
          </form>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr><th>#</th><th>Cliente</th><th>Criado em</th><th>Status</th><th class="text-end">Total</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <?php if(!$orc_list): ?>
              <tr><td colspan="6" class="text-center text-muted">Nenhum orçamento encontrado.</td></tr>
            <?php else: foreach($orc_list as $o): ?>
              <tr>
                <td><?=h($o['id'])?></td>
                <td><?=h($o['cliente'])?></td>
                <td><?=h(date('d/m/Y H:i', strtotime($o['criado_em'])))?></td>
                <td><span class="badge status-<?=h($o['status'])?>"><?=h($o['status'])?></span></td>
                <td class="text-end"><?=brl($o['total'])?></td>
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="ver_orcamento.php?id=<?=$o['id']?>&back=<?=urlencode($_SERVER['REQUEST_URI'] ?? '')?>">Abrir</a>
                  <a class="btn btn-sm btn-outline-success status-link" href="?status=aprovado&id=<?=$o['id']?>">Aprovar</a>
                  <a class="btn btn-sm btn-outline-danger status-link" href="?status=recusado&id=<?=$o['id']?>">Recusar</a>

                  <!-- Excluir (com CSRF) -->
                  <form method="post" action="api_orcamento_delete.php" class="d-inline"
                        onsubmit="return confirm('Tem certeza que deseja excluir este orçamento? Esta ação não pode ser desfeita.');">
                    <input type="hidden" name="id" value="<?=$o['id']?>">
                    <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
                    <button class="btn btn-sm btn-outline-danger">Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function maskBRL(v){
  if (typeof v!=='string') v=String(v??'');
  let n=v.replace(/[^\d,.-]/g,'').replace(/\./g,'').replace(',', '.');
  if(n===''||isNaN(n)) n='0';
  const f=parseFloat(n);
  return f.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
}
function parseBRL(s){
  s=(s||'').toString();
  // remove tudo que não for dígito, vírgula, ponto ou sinal
  s=s.replace(/[^\d,.\-]/g,'');
  // remove milhar e normaliza decimal
  s=s.replace(/\./g,'').replace(',', '.');
  const v=parseFloat(s);
  return isNaN(v)?0:v;
}
function computeTotals(){
  const rows=document.querySelectorAll('#itens .item');
  let totItens=0;
  rows.forEach(r=>{
    const q=parseFloat((r.querySelector('.qtd')?.value||'1').toString().replace(',','.'))||0;
    const p=parseBRL(r.querySelector('.preco')?.value||'0');
    totItens+=q*p;
  });
  const desconto=parseBRL(document.getElementById('desconto')?.value||'0');
  const acrescimo=parseBRL(document.getElementById('acrescimo')?.value||'0');
  let totFinal=totItens-desconto+acrescimo;
  if(totFinal<0) totFinal=0;
  document.getElementById('totItens').textContent=totItens.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
  document.getElementById('totFinal').textContent=totFinal.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
}
function bindMasks(){
  document.querySelectorAll('.preco').forEach(inp=>{
    inp.addEventListener('blur', e=>{ e.target.value=maskBRL(e.target.value); computeTotals(); });
    inp.addEventListener('input', computeTotals);
  });
  document.querySelectorAll('.qtd').forEach(inp=>{
    inp.addEventListener('input', computeTotals);
  });
  document.querySelectorAll('#itens .remove').forEach(btn=>{
    btn.addEventListener('click', (e)=>{
      e.preventDefault();
      btn.closest('.item')?.remove();
      computeTotals();
    });
  });
}
function addItem(desc='', qtd='1', preco=''){
  const row=document.createElement('div');
  row.className='row g-2 align-items-end mb-2 item';
  row.innerHTML =
    `<div class="col-6"><input name="descricao[]" class="form-control" placeholder="Descrição" value="${desc}"></div>
     <div class="col-3"><input name="quantidade[]" class="form-control qtd" value="${qtd}" inputmode="decimal"></div>
     <div class="col-3">
       <div class="input-group">
         <input name="preco[]" class="form-control preco" placeholder="0,00" value="${preco}" inputmode="decimal">
         <button type="button" class="btn btn-outline-danger remove" title="Remover item">✕</button>
       </div>
     </div>`;
  document.getElementById('itens').appendChild(row);
  bindMasks();
  computeTotals();
}
// Seleciona a "linha alvo" para preencher: a focada; senão, a última vazia; senão, cria nova.
function getTargetRow() {
  const active = document.activeElement && document.activeElement.closest('.item');
  if (active) return active;

  const rows = Array.from(document.querySelectorAll('#itens .item'));
  if (!rows.length) { addItem(); return document.querySelector('#itens .item:last-child'); }

  const last = rows[rows.length - 1];
  const desc = (last.querySelector('input[name="descricao[]"]')?.value || '').trim();
  const preco= (last.querySelector('input[name="preco[]"]')?.value || '').trim();

  if (!desc && !preco) return last; // última vazia -> usa ela
  addItem(); // tudo preenchido -> cria nova
  return document.querySelector('#itens .item:last-child');
}
// Ao mudar o select, preenche automaticamente descrição e preço na linha alvo
function applyPresetToRow() {
  const sel = document.getElementById('preset');
  const val = sel.value;
  if (!val) return;
  const [d, p] = val.split('|');

  const row = getTargetRow();
  const iDesc = row.querySelector('input[name="descricao[]"]');
  const iQtd  = row.querySelector('input[name="quantidade[]"]');
  const iPre  = row.querySelector('input[name="preco[]"]');

  if (iDesc) iDesc.value = d || '';
  if (iQtd)  iQtd.value  = '1';
  if (iPre)  iPre.value  = maskBRL(p || '0');

  computeTotals();
  if (iPre) iPre.focus();
}
// Mantém o botão "Adicionar" funcionando: cria nova linha já preenchida
function addPreset(){
  const sel = document.getElementById('preset');
  const val = sel.value;
  if(!val) return;
  const [d,p]=val.split('|');
  addItem(d,'1',maskBRL(p));
}

document.addEventListener('DOMContentLoaded', ()=>{
  bindMasks();
  computeTotals();
  document.getElementById('form-orc')?.addEventListener('input', computeTotals);
  document.querySelectorAll('.status-link').forEach(a=>{
    a.addEventListener('click',(e)=>{
      if(!confirm('Confirmar mudança de status?')) e.preventDefault();
    });
  });
});
</script>

<?php include __DIR__ . '/../app/layout_footer.php';
