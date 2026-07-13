<?php

session_start();
require_once __DIR__ . '/../php/hcimlab_sso.php';

try {
    $config = hcimlab_sso_config();
    if (!empty($_SESSION['USERID'])) {
        header('Location: ' . rtrim($config['base_url'], '/') . '/index.php');
        exit;
    }
    if (!empty($config['dev_auth'])) {
        session_regenerate_id(true);

        $userId = isset($config['dev_user_id']) ? (int)$config['dev_user_id'] : 10001;
        $userName = isset($config['dev_user_name']) ? (string)$config['dev_user_name'] : 'Local Dev User';
        $userSub = isset($config['dev_user_sub']) ? (string)$config['dev_user_sub'] : 'local-dev-user';

        $_SESSION['USERNAME'] = $userName;
        $_SESSION['USERID'] = $userId;
        $_SESSION['HCIMLAB_SSO_SUB'] = $userSub;
        $_SESSION['HCIMLAB_SSO_CLAIMS'] = array(
            'sub' => $userSub,
            'name' => $userName,
            'preferred_username' => $userName,
            'email' => '',
            'dev_auth' => true,
        );
        $_SESSION['HCIMLAB_SSO_ACCESS_TOKEN'] = 'dev';
        $_SESSION['HCIMLAB_SSO_REFRESH_TOKEN'] = null;
        $_SESSION['HCIMLAB_SSO_TOKEN_EXPIRES'] = time() + 86400;
        unset($_SESSION['SSO_ERROR']);

        header('Location: ' . rtrim($config['base_url'], '/') . '/index.php');
        exit;
    }

    $provider = hcimlab_sso_provider();
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['HCIMLAB_SSO_NONCE'] = $nonce;

    $authorizationUrl = $provider->getAuthorizationUrl(array(
        'scope' => $config['scope'],
        'nonce' => $nonce,
    ));

    $_SESSION['HCIMLAB_SSO_STATE'] = $provider->getState();
    $_SESSION['HCIMLAB_SSO_PKCE'] = $provider->getPkceCode();

    header('Location: ' . $authorizationUrl);
    exit;
} catch (Throwable $e) {
    hcimlab_sso_redirect_to_login($e->getMessage());
}
