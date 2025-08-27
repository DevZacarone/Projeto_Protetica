<?php
require __DIR__.'/../app/auth.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/db.php';
require_login();

$ok=''; $erro='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $atual = $_POST['atual'] ?? '';
  $nova  = $_POST['nova'] ?? '';
  $conf  = $_POST['conf'] ?? '';
  if($nova !== $conf){ $erro='Confirmação não confere.'; }
  else{
    $uid = current_user()['id'];
    $st = $pdo->prepare("SELECT senha_hash FROM users WHERE id=?");
    $st->execute([$uid]);
    $hash = $st->fetchColumn();
    if(!$hash || !password_verify($atual,$hash)){ $erro='Senha atual incorreta.'; }
    else{
      $novo = password_hash($nova, PASSWORD_DEFAULT);
      $up = $pdo->prepare("UPDATE users SET senha_hash=? WHERE id=?");
      $up->execute([$novo,$uid]);
      $ok='Senha alterada com sucesso!';
    }
  }
}
include __DIR__.'/../app/layout_header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h1 class="h5 mb-3">Alterar senha</h1>
    <?php if($ok): ?><div class="alert alert-success"><?=$ok?></div><?php endif; ?>
    <?php if($erro): ?><div class="alert alert-danger"><?=$erro?></div><?php endif; ?>
    <form method="post" class="card card-body">
      <div class="mb-2"><label class="form-label">Senha atual</label><input type="password" name="atual" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">Nova senha</label><input type="password" name="nova" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">Confirmar nova senha</label><input type="password" name="conf" class="form-control" required></div>
      <button class="btn btn-primary">Salvar</button>
    </form>
  </div>
</div>
<?php include __DIR__.'/../app/layout_footer.php';
