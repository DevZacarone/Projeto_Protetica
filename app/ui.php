<?php
// UI helpers
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function ui_back_url(): string {
  global $BASE_URL;
  $ref = $_GET['back'] ?? ($_SERVER['HTTP_REFERER'] ?? ($BASE_URL . '/index.php'));
  if (strpos($ref, 'http') === 0 && (!isset($_SERVER['HTTP_HOST']) || !str_contains($ref, $_SERVER['HTTP_HOST']))) {
    return $BASE_URL . '/index.php';
  }
  return $ref;
}

function ui_header(string $title, string $subtitle = '', bool $show_back = true, array $actions = []): void {
  $back = ui_back_url();
  echo '<div class="page-head d-flex justify-content-between align-items-center mb-3">';
  echo '  <div class="d-flex align-items-center gap-2">';
  if ($show_back) {
    echo '    <a class="btn btn-light btn-back" href="' . htmlspecialchars($back) . '">← Voltar</a>';
  }
  echo '    <div>';
  echo '      <h1 class="h5 m-0">' . htmlspecialchars($title) . '</h1>';
  if ($subtitle) echo '  <div class="text-muted small">' . htmlspecialchars($subtitle) . '</div>';
  echo '    </div>';
  echo '  </div>';
  if (!empty($actions)) {
    echo '<div class="d-flex gap-2">';
    foreach ($actions as $a) {
      $label = $a['label'] ?? 'Ação';
      $href  = $a['href']  ?? '#';
      $class = $a['class'] ?? 'btn btn-primary';
      echo '<a class="' . htmlspecialchars($class) . '" href="' . htmlspecialchars($href) . '">' . $label . '</a>';
    }
    echo '</div>';
  }
  echo '</div>';
}
