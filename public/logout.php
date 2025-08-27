<?php
require __DIR__.'/../app/auth.php';
logout();
require __DIR__.'/../app/config.php';
header('Location: '.$BASE_URL.'/login.php');