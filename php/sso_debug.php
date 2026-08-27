<?php

require_once __DIR__ . '/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/hcimlab_sso.php';

$config = hcimlab_sso_config();
if (($config['environment'] ?? 'local') === 'production') {
    http_response_code(404);
    exit;
}
$resolvedClientUrl = hcimlab_sso_resolve_client_url();
$tokenAuthMethod = hcimlab_sso_token_auth_method();
$isConfigured = hcimlab_sso_is_oauth_configured();

$payload = array(
    'configured' => $isConfigured,
    'client_id' => $config['client_id'],
    'client_secret_present' => $config['client_secret'] !== '',
    'base_url' => $config['base_url'],
    'redirect_uri' => $config['redirect_uri'],
    'token_auth_method' => $tokenAuthMethod,
    'resolved_client_url' => $resolvedClientUrl,
    'session_client_url' => isset($_SESSION['HCIMLAB_SSO_CLIENT_URL']) ? $_SESSION['HCIMLAB_SSO_CLIENT_URL'] : null,
    'request_client_url' => isset($_GET['clientUrl']) ? $_GET['clientUrl'] : null,
    'request_origin' => isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null,
    'request_referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
);
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OK-Core SSO Debug</title>
    <link rel="stylesheet" href="../css/ok-core.css">
</head>
<body class="auth-page">
    <main class="auth-box">
        <h1>SSO Debug</h1>
        <p>現在 OK-Core が使っている SSO 設定です。</p>
        <pre><?php echo htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
        <div class="auth-actions">
            <a class="primary-link" href="../login.php">ログイン画面へ</a>
            <a class="secondary-button" href="../auth/login.php?mode=sso&clientUrl=<?php echo rawurlencode($resolvedClientUrl); ?>">SSOを試す</a>
        </div>
    </main>
</body>
</html>
