<?php
function brl($v){return 'R$ ' . number_format((float)$v, 2, ',', '.');}
function h($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}
function post($k,$d=''){return $_POST[$k] ?? $d;}
function get($k,$d=''){return $_GET[$k] ?? $d;}
?>