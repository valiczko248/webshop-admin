<?php
require_once __DIR__ . '/backend/auth.php';

clear_remember_cookie();
$_SESSION = [];
session_destroy();
header('Location: /login.php');
exit;
