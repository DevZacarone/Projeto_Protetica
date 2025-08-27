<?php
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/auth.php';
require_login();

// Session + CSRF
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$flash = ''; $err = '';

// ------------ Handlers ------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // CSRF obrigatório para todas as ações
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
      throw new Exception('CSRF inválido. Recarregue a página.');
    }

    // Excluir
    if (isset($_POST['delete_id'])) {
      $id = (int)$_POST['delete_id'];
      if ($id <= 0) throw new Exception('ID inválido para exclusão.');
      $st = $pdo->prepare("DELETE FROM clientes WHERE id=?");
      $st->execute([$id]);
      $flash = 'Cliente excluído com sucesso.';
    }
    // Editar
    elseif (isset($_POST['editar_id'])) {
      $id = (int)$_POST['editar_id'];
      $nome = trim($_POST['nome'] ?? '');
      $telefone = trim($_POST['telefone'] ?? '');
      $email = trim($_POST['email'] ?? '');
      if ($id <= 0 || $nome==='') throw new Exception('Preencha os dados corretamente.');
      $st = $pdo->prepare("UPDATE clientes SET nome=?, telefone=?, email=? WHERE id=?");
      $st->execute([$nome, $telefone, $email, $id]);
      $flash = 'Cliente atualizado!';
    }
    // Criar
    else {
      $nome = trim($_POST['nome'] ?? '');
      $telefone = trim($_POST['telefone'] ?? '');
      $email = trim($_POST['email'] ?? '');
      if ($nome==='') throw new Exception('Informe o nome.');
      $st = $pdo->prepare("INSERT INTO clientes(nome,telefone,email) VALUES(?,?,?)");
      $st->execute([$nome, $telefone, $email]);
      $flash = 'Cliente cadastrado!';
    }

  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

// ------------ Filtros / Lista ------------
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
  $st = $pdo->prepare("SELECT * FROM clientes WHERE nome LIKE ? OR telefone LIKE ? OR email LIKE ? ORDER BY id DESC");
  $like = "%$q%"; $st->execute([$like, $like, $like]);
  $lista = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
  $lista = $pdo->query("SELECT * FROM clientes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__.'/../app/layout_header.php';
require __DIR__.'/../app/ui.php';

// ====== FIX do modal atrás do backdrop (z-index do <main>) ======
echo '<style>main.container{z-index:auto !important;}</style>';

// botão “Novo Cliente” do topo REMOVIDO (somente título/subtítulo)
ui_header('Clientes', 'Cadastre e pesquise clientes', true);
?>
<div class="row g-4">
  <div class="col-lg-5" id="novo">
    <div class="card card-body">
      <h2 class="h6 mb-3">Novo Cliente</h2>
      <?php if($flash): ?><div class="alert alert-success"><?=$flash?></div><?php endif; ?>
      <?php if($err): ?><div class="alert alert-danger"><?=$err?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
        <div class="mb-2">
          <label class="form-label">Nome</label>
          <input required name="nome" class="form-control" placeholder="Nome completo">
        </div>
        <div class="mb-2">
          <label class="form-label">Telefone (WhatsApp)</label>
          <input name="telefone" class="form-control" placeholder="(DDD) 9 9999-9999">
        </div>
        <div class="mb-2">
          <label class="form-label">E-mail</label>
          <input type="email" name="email" class="form-control" placeholder="exemplo@dominio.com">
        </div>
        <button class="btn btn-primary">Salvar</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card card-body">
      <form class="row g-2 align-items-center mb-3" method="get">
        <div class="col">
          <input class="form-control" name="q" value="<?=h($q)?>" placeholder="Buscar por nome, telefone ou e-mail">
        </div>
        <div class="col-auto">
          <button class="btn btn-outline-primary">Buscar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th style="width:80px">#</th>
              <th>Nome</th>
              <th>Contato</th>
              <th class="text-nowrap" style="width:170px">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$lista): ?>
            <tr><td colspan="4" class="text-center text-muted">Nenhum cliente encontrado.</td></tr>
          <?php else: foreach($lista as $c): ?>
            <tr>
              <td class="text-muted">#<?=h($c['id'])?></td>
              <td class="fw-semibold"><?=h($c['nome'])?></td>
              <td>
                <?php if($c['telefone']): ?>📱 <?=h($c['telefone'])?><br><?php endif; ?>
                <?php if($c['email']): ?>✉️ <?=h($c['email'])?><?php endif; ?>
              </td>
              <td class="text-nowrap">
                <!-- Editar: abre modal e preenche -->
                <button 
                  class="btn btn-sm btn-outline-primary me-1"
                  data-bs-toggle="modal"
                  data-bs-target="#modalEditar"
                  data-id="<?=h($c['id'])?>"
                  data-nome="<?=h($c['nome'])?>"
                  data-telefone="<?=h($c['telefone'])?>"
                  data-email="<?=h($c['email'])?>"
                >Editar</button>

                <!-- Excluir: POST com CSRF -->
                <form method="post" class="d-inline" onsubmit="return confirm('Excluir este cliente? Esta ação não pode ser desfeita.');">
                  <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
                  <input type="hidden" name="delete_id" value="<?=h($c['id'])?>">
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

<!-- Modal Editar Cliente (deixa dentro do body; com o fix acima funciona) -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
      <input type="hidden" name="editar_id" id="editar_id">
      <div class="modal-header">
        <h5 class="modal-title">Editar cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" id="editar_nome" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Telefone</label>
          <input class="form-control" name="telefone" id="editar_telefone">
        </div>
        <div class="mb-2">
          <label class="form-label">E-mail</label>
          <input type="email" class="form-control" name="email" id="editar_email">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Salvar alterações</button>
      </div>
    </form>
  </div>
</div>

<script>
// Preenche o modal de edição com os data-* do botão
document.addEventListener('DOMContentLoaded', function(){
  const modal = document.getElementById('modalEditar');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;
    if (!btn) return;
    document.getElementById('editar_id').value        = btn.getAttribute('data-id') || '';
    document.getElementById('editar_nome').value      = btn.getAttribute('data-nome') || '';
    document.getElementById('editar_telefone').value  = btn.getAttribute('data-telefone') || '';
    document.getElementById('editar_email').value     = btn.getAttribute('data-email') || '';
  });
});
</script>

<?php include __DIR__.'/../app/layout_footer.php';
