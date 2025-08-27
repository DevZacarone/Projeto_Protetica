<?php
require __DIR__.'/../app/auth.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/config.php';


$erro = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
if(login($_POST['username'] ?? '', $_POST['password'] ?? '')){
$ret = $_GET['return'] ?? ($BASE_URL.'/');
header('Location: '.$ret); exit;
} else { $erro = 'Usuário ou senha inválidos.'; }
}
include __DIR__.'/../app/layout_header.php';
?>
<div class="row justify-content-center">
<div class="col-md-4">
<h1 class="h5 mb-3">Entrar</h1>
<?php if($erro): ?><div class="alert alert-danger"><?=$erro?></div><?php endif; ?>
<form method="post" class="card card-body">
<div class="mb-2"><label class="form-label">Usuário</label><input name="username" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Senha</label><input type="password" name="password" class="form-control" required></div>
<button class="btn btn-primary">Entrar</button>
</form>
<div class="mt-3 small text-muted">Primeiro acesso: <code>admin / admin123</code>. Depois altere a senha.</div>
</div>
</div>
<?php include __DIR__.'/../app/layout_footer.php';