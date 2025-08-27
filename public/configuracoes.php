<?php
require __DIR__.'/../app/config.php'; // $BASE_URL
require __DIR__.'/../app/auth.php'; require_login();
require __DIR__.'/../app/settings.php';
require __DIR__.'/../app/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

include __DIR__.'/../app/layout_header.php';

$ok=''; $erro='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $form = $_POST['__form'] ?? 'settings';

  try {
    if ($form === 'settings') {
      // ===== SALVAR CONFIGS BÁSICAS =====
      setting_set('clinic_name',   $_POST['clinic_name']   ?? 'Protética Rafael Borsato');
      setting_set('brand_primary', $_POST['brand_primary'] ?? '#FFC1E3');
      setting_set('brand_accent',  $_POST['brand_accent']  ?? '#D4AF37');
      setting_set('whatsapp_ddi',  $_POST['whatsapp_ddi']  ?? '55');
      setting_set('whatsapp_ddd',  $_POST['whatsapp_ddd']  ?? '');
      setting_set('whatsapp_num',  $_POST['whatsapp_num']  ?? '');

      // Garante pasta /public/assets
      $assetsDirFs = __DIR__.'/assets';
      if (!is_dir($assetsDirFs)) { @mkdir($assetsDirFs, 0775, true); }

      // ===== LOGO =====
      if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','webp','ico'], true)) {
          throw new Exception('Logo inválida (png/jpg/webp/ico).');
        }
        $filename = 'logo.'.$ext;
        $destFs   = $assetsDirFs.'/'.$filename;
        $destUrl  = rtrim($BASE_URL,'/').'/assets/'.$filename;
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $destFs)) {
          throw new Exception('Falha ao salvar logo do topo.');
        }
        setting_set('logo_path', $destUrl);
      }

      // ===== FAVICON =====
      if (!empty($_FILES['favicon']['name']) && is_uploaded_file($_FILES['favicon']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['ico','png','jpg','jpeg','webp'], true)) {
          throw new Exception('Favicon inválido (ico/png/jpg/webp).');
        }
        $filename = ($ext==='ico') ? 'favicon.ico' : ('favicon.'.$ext);
        $destFs   = $assetsDirFs.'/'.$filename;
        $destUrl  = rtrim($BASE_URL,'/').'/assets/'.$filename;
        if (!move_uploaded_file($_FILES['favicon']['tmp_name'], $destFs)) {
          throw new Exception('Falha ao salvar favicon.');
        }
        setting_set('favicon_path', $destUrl);
      }

      $ok = 'Configurações salvas!';

    } elseif ($form === 'password') {
      // ===== ALTERAR SENHA (users.senha_hash) =====
      if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        throw new Exception('CSRF inválido. Recarregue a página.');
      }

      $uid = (int)($_SESSION['user']['id'] ?? 0);
      if ($uid <= 0) throw new Exception('Usuário não autenticado.');

      $senha_atual = $_POST['senha_atual'] ?? '';
      $senha_nova  = $_POST['senha_nova']  ?? '';
      $senha_conf  = $_POST['senha_conf']  ?? '';

      if (strlen($senha_nova) < 6) throw new Exception('A nova senha deve ter pelo menos 6 caracteres.');
      if ($senha_nova !== $senha_conf) throw new Exception('Confirmação de senha não confere.');

      // Busca hash atual em users.senha_hash (SQLite)
      $st = $pdo->prepare('SELECT id, senha_hash FROM users WHERE id=? LIMIT 1');
      $st->execute([$uid]);
      $u = $st->fetch(PDO::FETCH_ASSOC);
      if (!$u) throw new Exception('Usuário não encontrado.');

      if (!password_verify($senha_atual, (string)$u['senha_hash'])) {
        throw new Exception('Senha atual incorreta.');
      }

      $novo_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
      $up = $pdo->prepare('UPDATE users SET senha_hash=? WHERE id=?');
      $up->execute([$novo_hash, $uid]);

      $ok = 'Senha alterada com sucesso!';
    }
  } catch (Throwable $e) {
    $erro = $e->getMessage();
  }
}

// Carrega configs para exibir
$clinic = setting_get('clinic_name','Protética Rafael Borsato');
$pink   = setting_get('brand_primary','#FFC1E3');
$gold   = setting_get('brand_accent','#D4AF37');
$ddi    = setting_get('whatsapp_ddi','55');
$ddd    = setting_get('whatsapp_ddd','');
$num    = setting_get('whatsapp_num','');
$logo   = setting_get('logo_path','');
$favi   = setting_get('favicon_path','');
?>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <h1 class="h5 mb-3">Configurações</h1>
    <?php if($ok): ?><div class="alert alert-success"><?=$ok?></div><?php endif; ?>
    <?php if($erro): ?><div class="alert alert-danger"><?=$erro?></div><?php endif; ?>

    <!-- ===== FORM CONFIGURAÇÕES ===== -->
    <form method="post" enctype="multipart/form-data" class="card card-body mb-4">
      <input type="hidden" name="__form" value="settings">

      <div class="mb-3">
        <label class="form-label">Nome da clínica</label>
        <input class="form-control" name="clinic_name" value="<?=h($clinic)?>" required>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6"><label class="form-label">Cor primária</label><input class="form-control" name="brand_primary" type="color" value="<?=h($pink)?>"></div>
        <div class="col-md-6"><label class="form-label">Cor destaque</label><input class="form-control" name="brand_accent" type="color" value="<?=h($gold)?>"></div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-3"><label class="form-label">WhatsApp DDI</label><input class="form-control" name="whatsapp_ddi" value="<?=h($ddi)?>"></div>
        <div class="col-3"><label class="form-label">DDD</label><input class="form-control" name="whatsapp_ddd" value="<?=h($ddd)?>"></div>
        <div class="col-6"><label class="form-label">Número</label><input class="form-control" name="whatsapp_num" value="<?=h($num)?>"></div>
      </div>

      <div class="mb-3">
        <label class="form-label">Logo do topo (png/jpg/webp/ico)</label>
        <input class="form-control" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.ico">
        <?php if($logo): ?><div class="mt-2"><img src="<?=$logo?>" style="height:48px;border-radius:8px;"></div><?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">Favicon / Ícone da aba (ico/png/jpg/webp)</label>
        <input class="form-control" type="file" name="favicon" accept=".ico,.png,.jpg,.jpeg,.webp">
        <?php if($favi): ?><div class="mt-2"><img src="<?=$favi?>" style="height:32px;border-radius:6px;"> <span class="text-muted small">Usado na aba do navegador.</span></div><?php endif; ?>
      </div>

      <button class="btn btn-primary">Salvar</button>
    </form>

    <!-- ===== FORM ALTERAR SENHA ===== -->
    <form method="post" class="card card-body">
      <input type="hidden" name="__form" value="password">
      <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
      <h2 class="h6 mb-3">Alterar senha</h2>

      <div class="mb-2">
        <label class="form-label">Senha atual</label>
        <input type="password" class="form-control" name="senha_atual" required>
      </div>
      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label">Nova senha</label>
          <input type="password" class="form-control" name="senha_nova" minlength="6" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirmar nova senha</label>
          <input type="password" class="form-control" name="senha_conf" minlength="6" required>
        </div>
      </div>

      <div class="mt-3">
        <button class="btn btn-outline-primary">Atualizar senha</button>
      </div>
      <p class="small text-muted mt-2">A senha é armazenada com hash seguro (password_hash).</p>
    </form>
  </div>
</div>

<?php include __DIR__.'/../app/layout_footer.php';
