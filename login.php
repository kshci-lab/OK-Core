<?php
require_once __DIR__ . '/php/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/php/hcimlab_sso.php';

if (!empty($_SESSION['USERID'])) {
    header('Location: index.php');
    exit;
}

try {
    $config = hcimlab_sso_config();
    $returnUrl = hcimlab_sso_login_as_dev($config);
    header('Location: ' . rtrim($returnUrl, '/') . '/index.php');
    exit;
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK-Core login failed: " . $e->getMessage();
}
