<?php
require __DIR__.'/../app/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__.'/../app/settings.php';

// Marca / tema
$CLINIC = setting_get('clinic_name', 'Protética Rafael Borsato');
$PINK   = setting_get('brand_primary', '#FFC1E3');
$GOLD   = setting_get('brand_accent',  '#D4AF37');
$LOGO   = setting_get('logo_path', '');

// Ícone da aba (favicon)
$FAVICON = setting_get('favicon_path',"rafa.png");
if (!$FAVICON) { $FAVICON = $LOGO ?: ($BASE_URL.'../assets/favicon.webp'); }

// ==== FUNDO: logo cobrindo a tela toda (cover) ====
$BG_SRC     = setting_get('bg_logo_path', "rafa.png") ?: $LOGO;  // usa imagem específica; se não houver, usa a logo normal
$BG_OPACITY = (float)setting_get('bg_logo_opacity', '0.08'); // 0.00 a 1.00
$BG_BLUR    = (int)setting_get('bg_logo_blur', '24');        // px
$BG_SIZE    = setting_get('bg_logo_size', 'cover');          // 'cover' ou 'contain'
if ($BG_OPACITY < 0) $BG_OPACITY = 0; if ($BG_OPACITY > 1) $BG_OPACITY = 1;
if ($BG_BLUR < 0) $BG_BLUR = 0; if ($BG_BLUR > 120) $BG_BLUR = 120;
if (!in_array($BG_SIZE, ['cover','contain'], true)) $BG_SIZE = 'cover';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=$CLINIC?> • Painel</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?=$BASE_URL?>/assets/style.css">
  <link rel="icon" href="<?=$FAVICON?>">
  <link rel="apple-touch-icon" href="<?=$FAVICON?>">
  <meta name="theme-color" content="<?=$PINK?>">

  <style>
    :root{
      --brand-primary: <?=$PINK?>;
      --brand-accent:  <?=$GOLD?>;
      --brand-grad: linear-gradient(90deg, var(--brand-primary), var(--brand-accent));
    }
    html, body { height: 100%; }
    body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; position: relative; }

    /* Barra */
    .navbar-gradient { background: var(--brand-grad) !important; }
    .btn-primary, .badge.bg-primary { background: var(--brand-grad) !important; border: none; }
    .btn-outline-primary { color:#7a3b5a; border-color:#e9b6cc; }
    .btn-outline-primary:hover { background: var(--brand-grad); color:#fff; border-color: transparent; }
    .brand-logo{ width:36px; height:36px; object-fit:cover; border-radius:50%; background:#fff; }
    .nav-link.active, .nav-link:hover { opacity:.95; }

    /* ===== Fundo com logo cobrindo tudo ===== */
    .bg-logo-full{
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background-position: center center;
      background-repeat: no-repeat;
      will-change: transform;
      transform: translateZ(0);
    }
    main.container { position: relative; z-index: 1; }
    nav.navbar   { position: relative; z-index: 2; }
  </style>
</head>
<body>
<?php if($BG_SRC): ?>
  <div class="bg-logo-full"
       style="
         background-image:url('<?=htmlspecialchars($BG_SRC, ENT_QUOTES, 'UTF-8')?>');
         background-size: <?=$BG_SIZE?>;
         opacity: <?=$BG_OPACITY?>;
         filter: blur(<?=$BG_BLUR?>px);
       "></div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-gradient shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?=$BASE_URL?>/index.php">
      <?php if($LOGO): ?><img src="<?=$LOGO?>" class="brand-logo" alt="Logo"><?php endif; ?>
      <span><?=$CLINIC?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Alternar navegação">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?=$BASE_URL?>/index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?=$BASE_URL?>/clientes.php">Clientes</a></li>
        <li class="nav-item"><a class="nav-link" href="<?=$BASE_URL?>/orcamentos.php">Orçamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="<?=$BASE_URL?>/caixa.php">Caixa</a></li>
        <li class="nav-item"><a class="nav-link" href="<?=$BASE_URL?>/relatorios.php">Relatórios</a></li>
        <li class="nav-item"><a class="nav-link" href="<?=$BASE_URL?>/configuracoes.php">Configurações</a></li>
      </ul>
      <div class="d-flex text-white">
        <?php if(!empty($_SESSION['user'])): ?>
          <span class="me-3">Olá, <strong><?=htmlspecialchars($_SESSION['user']['nome'])?></strong></span>
          <a class="btn btn-sm btn-outline-light" href="<?=$BASE_URL?>/logout.php">Sair</a>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-light" href="<?=$BASE_URL?>/login.php">Entrar</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<main class="container py-4">
