<?php
// public/api_whatsapp_send.php
require __DIR__.'/../app/config.php';
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_login();

// --- CONFIG NECESSÁRIA ---
// Adicione no app/config.php (com seus valores reais):
// define('WHATSAPP_TOKEN',           'EAAG...');            // Access Token (Cloud API)
// define('WHATSAPP_PHONE_NUMBER_ID', '123456789012345');    // Phone Number ID (Cloud API)
//
// O $BASE_URL já deve existir no seu config (ex.: '/protetica/public')

function fail($msg){
  $back = $_SERVER['HTTP_REFERER'] ?? ((isset($BASE_URL)?$BASE_URL:'/').'/?');
  header('Location: '.$back.(str_contains($back,'?')?'&':'?').'err='.rawurlencode($msg));
  exit;
}
function ok(){
  $back = $_SERVER['HTTP_REFERER'] ?? ((isset($BASE_URL)?$BASE_URL:'/').'/?');
  header('Location: '.$back.(str_contains($back,'?')?'&':'?').'sent=1');
  exit;
}

try{
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    fail('CSRF inválido.');
  }
  if (empty($_POST['id'])) fail('ID inválido.');
  $orcId = (int)$_POST['id'];

  // normaliza telefone -> E.164
  $toRaw = trim((string)($_POST['to'] ?? ''));
  $digits = preg_replace('/\D+/', '', $toRaw);
  if ($digits === '') fail('Telefone do destinatário vazio.');

  // se não vier com DDI, assume Brasil 55
  // aceita 10/11 dígitos sem DDI -> prefixa 55
  if (strlen($digits) <= 12 && !str_starts_with($digits, '55')) {
    $digits = '55'.$digits;
  }
  // remove zeros à esquerda ocasionais
  $digits = ltrim($digits, '0');
  $toE164 = '+'.$digits;

  // checa dependências
  if (!defined('WHATSAPP_TOKEN') || !WHATSAPP_TOKEN) fail('WHATSAPP_TOKEN não configurado.');
  if (!defined('WHATSAPP_PHONE_NUMBER_ID') || !WHATSAPP_PHONE_NUMBER_ID) fail('WHATSAPP_PHONE_NUMBER_ID não configurado.');
  if (!function_exists('curl_init')) fail('cURL não habilitado no PHP.');

  // 1) BAIXA O PDF DO APP (usando a sessão atual)
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base   = $BASE_URL ?? '';
  // Ajuste se precisar: caminho público para o PDF
  $pdfUrl = $scheme.'://'.$host.$base.'/pdf_orcamento.php?id='.$orcId;

  $ch = curl_init($pdfUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => [
      'Cookie: PHPSESSID='.session_id(),
      'Accept: application/pdf'
    ],
    CURLOPT_TIMEOUT        => 60,
  ]);
  $pdfBinary = curl_exec($ch);
  $httpCode  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $err       = curl_error($ch);
  curl_close($ch);

  if ($pdfBinary===false || $httpCode<200 || $httpCode>=300) {
    fail('Falha ao gerar/baixar PDF (HTTP '.$httpCode.'). '.$err);
  }

  // salva temporariamente
  $tmpFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orc_'.$orcId.'_'.bin2hex(random_bytes(4)).'.pdf';
  file_put_contents($tmpFile, $pdfBinary);

  // 2) FAZ UPLOAD PARA WHATSAPP (MEDIA)
  $mediaUrl = 'https://graph.facebook.com/v20.0/'.WHATSAPP_PHONE_NUMBER_ID.'/media';
  $ch = curl_init($mediaUrl);
  $mime = 'application/pdf';
  $postFields = [
    'messaging_product' => 'whatsapp',
    'file'              => new CURLFile($tmpFile, $mime, 'orcamento_'.$orcId.'.pdf'),
    'type'              => $mime
  ];
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_HTTPHEADER     => [
      'Authorization: Bearer '.WHATSAPP_TOKEN
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
  ]);
  $resp = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  if ($resp===false || $httpCode<200 || $httpCode>=300) {
    @unlink($tmpFile);
    fail('Upload para WhatsApp falhou (HTTP '.$httpCode.'). '.$err.' | Resp: '.$resp);
  }
  $data = json_decode($resp, true);
  $mediaId = $data['id'] ?? null;
  if (!$mediaId) {
    @unlink($tmpFile);
    fail('Resposta sem media id: '.$resp);
  }

  // 3) ENVIA A MENSAGEM COM O DOCUMENTO
  $sendUrl = 'https://graph.facebook.com/v20.0/'.WHATSAPP_PHONE_NUMBER_ID.'/messages';
  $payload = [
    'messaging_product' => 'whatsapp',
    'to'       => $toE164,
    'type'     => 'document',
    'document' => [
      'id'       => $mediaId,
      'filename' => 'orcamento_'.$orcId.'.pdf',
      'caption'  => 'Orçamento #'.$orcId.' - enviado automaticamente'
    ],
  ];

  $ch = curl_init($sendUrl);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
      'Authorization: Bearer '.WHATSAPP_TOKEN,
      'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
  ]);
  $resp = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  @unlink($tmpFile);

  if ($resp===false || $httpCode<200 || $httpCode>=300) {
    fail('Envio do documento falhou (HTTP '.$httpCode.'). '.$err.' | Resp: '.$resp);
  }

  ok();

} catch (Throwable $e){
  fail($e->getMessage());
}
