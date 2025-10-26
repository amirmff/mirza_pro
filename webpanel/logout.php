<?php
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
$auth->logout();

header('Location: /webpanel/login.php');
exit;
