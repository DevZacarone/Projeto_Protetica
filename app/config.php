<?php
// URL base (ajuste se necessário)
$BASE_URL = '/protetica/public';
// Nome da clínica para cabeçalhos/relatórios
$CLINIC_NAME = 'Prótese Exemplo';

// WhatsApp Cloud API (preencha com seus dados reais)
if (!defined('WHATSAPP_TOKEN')) {
  define('WHATSAPP_TOKEN', 'COLE_SEU_ACCESS_TOKEN_AQUI');
}
if (!defined('WHATSAPP_PHONE_NUMBER_ID')) {
  define('WHATSAPP_PHONE_NUMBER_ID', 'SEU_PHONE_NUMBER_ID');
}
?>