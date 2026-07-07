<?php

session_start();
require_once __DIR__ . '/../php/hcimlab_sso.php';

try {
    $provider = hcimlab_sso_provider();
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['HCIMLAB_SSO_NONCE'] = $nonce;

    $config = hcimlab_sso_config();
    $authorizationUrl = $provider->getAuthorizationUrl(array(
        'scope' => $config['scope'],
        'nonce' => $nonce,
        'prompt' => 'login',
    ));

    $_SESSION['HCIMLAB_SSO_STATE'] = $provider->getState();
    $_SESSION['HCIMLAB_SSO_PKCE'] = $provider->getPkceCode();

    header('Location: ' . $authorizationUrl);
    exit;
} catch (Throwable $e) {
    hcimlab_sso_redirect_to_login($e->getMessage());
}
