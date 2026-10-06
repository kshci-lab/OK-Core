<?php

require_once __DIR__ . '/../php/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/../php/hcimlab_sso.php';
require_once __DIR__ . '/../php/connect_db.php';

try {
    $baseUrl = rtrim(hcimlab_sso_config()['base_url'], '/');
    if (isset($_GET['error'])) {
        $description = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
        throw new RuntimeException('SSO authorization failed: ' . $description);
    }

    if (empty($_GET['state']) || empty($_SESSION['HCIMLAB_SSO_STATE']) || !hash_equals($_SESSION['HCIMLAB_SSO_STATE'], $_GET['state'])) {
        throw new RuntimeException('Invalid SSO state.');
    }

    $startedAt = isset($_SESSION['HCIMLAB_SSO_STARTED_AT']) ? (int)$_SESSION['HCIMLAB_SSO_STARTED_AT'] : 0;
    if ($startedAt <= 0 || (time() - $startedAt) > 600) {
        throw new RuntimeException('SSO login attempt expired. Please try again.');
    }

    if (empty($_SESSION['HCIMLAB_SSO_PKCE']) || empty($_SESSION['HCIMLAB_SSO_NONCE'])) {
        throw new RuntimeException('SSO login session is incomplete. Please try again.');
    }

    if (empty($_GET['code'])) {
        throw new RuntimeException('SSO callback does not contain an authorization code.');
    }

    $tokenAuthMethod = hcimlab_sso_token_auth_method();
    $authMethodsToTry = ($tokenAuthMethod === 'auto')
        ? array('post', 'basic')
        : array($tokenAuthMethod);

    $token = null;
    $lastTokenError = null;
    foreach ($authMethodsToTry as $authMethod) {
        try {
            $provider = hcimlab_sso_create_provider($authMethod);
            $provider->setPkceCode($_SESSION['HCIMLAB_SSO_PKCE']);
            $token = $provider->getAccessToken('authorization_code', array(
                'code' => $_GET['code'],
            ));
            break;
        } catch (Throwable $tokenError) {
            $lastTokenError = $tokenError;
            $message = strtolower($tokenError->getMessage());
            if (strpos($message, 'invalid_client') === false && strpos($message, 'unauthorized_client') === false) {
                throw $tokenError;
            }
        }
    }

    if ($token === null) {
        throw $lastTokenError ?: new RuntimeException('SSO token exchange failed.');
    }

    $values = $token->getValues();
    if (empty($values['id_token'])) {
        throw new RuntimeException('SSO token response does not contain id_token.');
    }

    $claims = hcimlab_sso_verify_id_token($values['id_token'], $_SESSION['HCIMLAB_SSO_NONCE']);
    $userinfo = $provider->getResourceOwner($token)->toArray();
    if (isset($userinfo['sub']) && (string)$userinfo['sub'] !== (string)$claims['sub']) {
        throw new RuntimeException('UserInfo sub does not match id_token sub.');
    }
    $claims = array_merge($claims, $userinfo);

    $user = hcimlab_sso_upsert_user($mysqli, $claims);

    session_regenerate_id(true);
    $_SESSION['USERNAME'] = $user['name'];
    $_SESSION['USERID'] = $user['user_id'];
    $_SESSION['HCIMLAB_SSO_SUB'] = (string)$claims['sub'];
    $_SESSION['HCIMLAB_SSO_CLAIMS'] = $claims;
    $_SESSION['HCIMLAB_SSO_ACCESS_TOKEN'] = $token->getToken();
    $_SESSION['HCIMLAB_SSO_REFRESH_TOKEN'] = $token->getRefreshToken();
    $_SESSION['HCIMLAB_SSO_TOKEN_EXPIRES'] = $token->getExpires();

    unset($_SESSION['HCIMLAB_SSO_STATE'], $_SESSION['HCIMLAB_SSO_PKCE'], $_SESSION['HCIMLAB_SSO_NONCE'], $_SESSION['HCIMLAB_SSO_STARTED_AT'], $_SESSION['SSO_ERROR'], $_SESSION['HCIMLAB_SSO_FORCE_LOGIN']);

    header('Location: ' . $baseUrl . '/index.php');
    exit;
} catch (Throwable $e) {
    unset($_SESSION['HCIMLAB_SSO_STATE'], $_SESSION['HCIMLAB_SSO_PKCE'], $_SESSION['HCIMLAB_SSO_NONCE'], $_SESSION['HCIMLAB_SSO_STARTED_AT'], $_SESSION['HCIMLAB_SSO_FORCE_LOGIN']);
    hcimlab_sso_redirect_to_login($e->getMessage());
}
