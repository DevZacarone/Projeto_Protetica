<?php
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';   // ok manter, mas não dependemos do getv()
require __DIR__.'/../app/config.php';
require __DIR__.'/../app/settings.php';


/* Marca e WhatsApp */
$CLINIC_NAME = setting_get('clinic_name', 'Protética Rafael Borsato');
$PINK  = setting_get('brand_primary', '#FFC1E3');   // rosa claro
$GOLD  = setting_get('brand_accent',  '#D4AF37');   // dourado
$LOGO  = setting_get('logo_path', '');
$ddi   = setting_get('whatsapp_ddi','55');
$ddd   = setting_get('whatsapp_ddd','');
$num   = setting_get('whatsapp_num','');
$phone = preg_replace('/\D/','', $ddi.$ddd.$num);

/* Dados do orçamento (sem getv) */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$st = $pdo->prepare("
  SELECT o.*, c.nome, c.telefone, c.email
  FROM orcamentos o
  JOIN clientes c ON c.id = o.cliente_id
  WHERE o.id = ?
");
$st->execute([$id]);
$orc = $st->fetch(PDO::FETCH_ASSOC);
if(!$orc){ http_response_code(404); echo 'Orçamento não encontrado'; exit; }

$itens = $pdo->query("SELECT * FROM orcamento_itens WHERE orcamento_id = $id")->fetchAll(PDO::FETCH_ASSOC);
$baseUrl = $BASE_URL;
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Orçamento #<?=$id?> • <?=h($CLINIC_NAME)?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?=$baseUrl?>/assets/style.css">
<style>
  .brand-band{
    background: linear-gradient(90deg, <?=$PINK?>, <?=$GOLD?>);
    color:#2a2a2a; border-radius:16px; padding:16px;
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:14px;
  }
  .brand-band img{ width:48px; height:48px; border-radius:10px; background:#fff; object-fit:cover; }
  .brand-title{ font-weight:800; font-size:1.05rem; }
</style>
</head>
<body class="bg-light">
<div class="printable p-4 bg-white shadow-sm">
    <a class="btn btn-light btn-back d-print-none mb-2" href="<?=htmlspecialchars($_SERVER['HTTP_REFERER'] ?? ($BASE_URL.'/orcamentos.php'))?>">← Voltar</a>


  <!-- Cabeçalho com a sua marca -->
  <div class="brand-band">
    <div class="d-flex align-items-center gap-2">
      <?php if($LOGO): ?><img src="<?=h($LOGO)?>" alt="Logo"><?php endif; ?>
      <div class="brand-title"><?=h($CLINIC_NAME)?></div>
    </div>
    <div>Orçamento <strong>#<?=h($id)?></strong></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="h4 m-0">Detalhes</div>
    <span class="badge bg-secondary text-uppercase"><?=h($orc['status'])?></span>
  </div>

  <p class="mb-1"><strong>Clínica:</strong> <?=h($CLINIC_NAME)?></p>
  <p class="mb-1"><strong>Cliente:</strong> <?=h($orc['nome'])?></p>
  <p class="mb-3"><strong>Contato:</strong> <?=h($orc['telefone'] ?: ($orc['email'] ?: '-'))?></p>

  <table class="table table-bordered">
    <thead>
      <tr>
        <th>Descrição</th>
        <th class="text-end">Qtd</th>
        <th class="text-end">Preço</th>
        <th class="text-end">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($itens as $i): ?>
      <tr>
        <td><?=h($i['descricao'])?></td>
        <td class="text-end"><?=h($i['quantidade'])?></td>
        <td class="text-end"><?=brl($i['preco'])?></td>
        <td class="text-end"><?=brl($i['quantidade']*$i['preco'])?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="3" class="text-end">Total</th>
        <th class="text-end"><?=brl($orc['total'])?></th>
      </tr>
    </tfoot>
  </table>

  <div class="d-print-none d-flex flex-wrap gap-2">
    <?php
      $linkPublico = (isset($_SERVER['HTTP_HOST'])
        ? ('http://'.$_SERVER['HTTP_HOST'].$baseUrl.'/ver_orcamento.php?id='.$id)
        : '');
      $msg   = rawurlencode("Olá, segue o orçamento #$id da $CLINIC_NAME: ".$linkPublico);
      $waLink = $phone ? "https://wa.me/{$phone}?text={$msg}" : "https://wa.me/?text={$msg}";
    ?>
    <a class="btn btn-success" target="_blank" href="<?=$waLink?>">Enviar via WhatsApp</a>
    <a class="btn btn-outline-secondary" href="#" onclick="window.print()">Imprimir</a>
    <a class="btn btn-outline-primary" href="<?=$baseUrl?>/api_status.php?id=<?=$id?>&to=aprovado">Marcar Aprovado</a>
    <a class="btn btn-outline-danger" href="<?=$baseUrl?>/api_status.php?id=<?=$id?>&to=recusado">Marcar Recusado</a>
    <a class="btn btn-dark" href="<?=$baseUrl?>/pdf_orcamento.php?id=<?=$id?>">Baixar PDF</a>
  </div>

</div>
</body>
</html>
