<?php

require_once __DIR__ . '/../php/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/../php/hcimlab_sso.php';
require_once __DIR__ . '/../php/sso_return.php';

try {
    $config = hcimlab_sso_config();
    $baseUrl = rtrim($config['base_url'], '/');
    hcimlab_sso_capture_agent_return($baseUrl);
    $clientUrl = isset($_GET['clientUrl']) ? (string)$_GET['clientUrl'] : '';
    $forceLogin = isset($_GET['force']) && (string)$_GET['force'] === '1';
    if ($clientUrl !== '' && hcimlab_sso_is_safe_return_url($clientUrl)) {
        $_SESSION['HCIMLAB_SSO_CLIENT_URL'] = rtrim($clientUrl, '/');
    }

    $returnUrl = hcimlab_sso_resolve_client_url();
    if ($forceLogin) {
        $_SESSION['HCIMLAB_SSO_FORCE_LOGIN'] = 1;
    }

    if (!$forceLogin && !empty($_SESSION['USERID'])) {
        header('Location: ' . hcimlab_sso_consume_agent_return($baseUrl));
        exit;
    }
    $mode = isset($_GET['mode']) ? (string)$_GET['mode'] : '';

    if ($mode === 'dev') {
        if (empty($config['dev_auth'])) {
            throw new RuntimeException('Development login is disabled.');
        }

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
        unset($_SESSION['HCIMLAB_SSO_FORCE_LOGIN']);

        // Keep an OKAgent return_to destination through local development login too.
        // The destination was validated and captured before authentication.
        header('Location: ' . hcimlab_sso_consume_agent_return($baseUrl));
        exit;
    }

    if ($mode !== 'sso') {
        header('Location: ' . $baseUrl . '/login.php');
        exit;
    }

    if (!hcimlab_sso_is_oauth_configured()) {
        throw new RuntimeException('SSO client_id / client_secret are not configured. Use 開発用ログイン or set real SSO credentials.');
    }

    // Rotate the pre-authentication session before storing OAuth state.
    session_regenerate_id(true);
    $provider = hcimlab_sso_provider();
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['HCIMLAB_SSO_NONCE'] = $nonce;

    $authorizationUrl = $provider->getAuthorizationUrl(array(
        'scope' => $config['scope'],
        'nonce' => $nonce,
    ));

    $_SESSION['HCIMLAB_SSO_STATE'] = $provider->getState();
    $_SESSION['HCIMLAB_SSO_PKCE'] = $provider->getPkceCode();
    $_SESSION['HCIMLAB_SSO_STARTED_AT'] = time();

    header('Location: ' . $authorizationUrl);
    exit;
} catch (Throwable $e) {
    hcimlab_sso_redirect_to_login($e->getMessage());
}
