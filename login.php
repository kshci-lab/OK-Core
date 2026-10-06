<?php

require_once __DIR__ . '/php/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/php/hcimlab_sso.php';

$baseUrl = rtrim(hcimlab_sso_config()['base_url'], '/');
$canonicalPath = parse_url($baseUrl . '/login.php', PHP_URL_PATH);
$requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
if ($canonicalPath && $requestPath && $requestPath !== $canonicalPath) {
    $queryString = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
    header('Location: ' . $baseUrl . '/login.php' . $queryString);
    exit;
}

$clientUrl = isset($_GET['clientUrl']) ? (string)$_GET['clientUrl'] : '';
if ($clientUrl !== '' && hcimlab_sso_is_safe_return_url($clientUrl)) {
    $_SESSION['HCIMLAB_SSO_CLIENT_URL'] = rtrim($clientUrl, '/');
}

if (empty($_SESSION['HCIMLAB_SSO_CLIENT_URL']) || !hcimlab_sso_is_safe_return_url($_SESSION['HCIMLAB_SSO_CLIENT_URL'])) {
    $_SESSION['HCIMLAB_SSO_CLIENT_URL'] = $baseUrl;
}

$resolvedClientUrl = rtrim((string)$_SESSION['HCIMLAB_SSO_CLIENT_URL'], '/');
$mode = isset($_GET['mode']) ? (string)$_GET['mode'] : '';

if ($mode === 'sso' || $mode === 'dev') {
    header('Location: ' . $baseUrl . '/auth/login.php?mode=' . rawurlencode($mode) . '&clientUrl=' . rawurlencode($resolvedClientUrl));
    exit;
}

$errorMessage = '';
if (!empty($_SESSION['SSO_ERROR'])) {
    $errorMessage = (string)$_SESSION['SSO_ERROR'];
    unset($_SESSION['SSO_ERROR']);
}

if ($errorMessage === 'SSO dependencies are not installed. Run scripts/setup-local.sh or composer install in the project root.'
    && file_exists(__DIR__ . '/vendor/autoload.php')) {
    $errorMessage = '';
}

$ssoLoginUrl = $baseUrl . '/auth/login.php?mode=sso&force=1&clientUrl=' . rawurlencode($resolvedClientUrl);
$devLoginUrl = $baseUrl . '/auth/login.php?mode=dev&clientUrl=' . rawurlencode($resolvedClientUrl);
$devLoginLabel = $errorMessage !== '' ? '開発用ログインに切り替える' : '開発用ログイン';
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OK-Core Login</title>
    <link rel="stylesheet" href="css/ok-core.css">
</head>
<body class="auth-page">
    <main class="auth-box">
        <h1>OK-Core</h1>
        <p>Knowledge Fragment を扱うための入口です。</p>
        <?php if ($errorMessage !== ''): ?>
            <p>SSOログインに失敗しました。</p>
            <pre><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></pre>
            <p style="margin-top: 0.75rem; color: #6b7280; font-size: 0.95rem;">
                ひとまず開発用ログインに切り替えて続けられます。
            </p>
        <?php endif; ?>
        <div class="auth-actions">
            <a class="primary-link" href="<?php echo htmlspecialchars($ssoLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">SSOログイン</a>
            <a class="secondary-button" href="<?php echo htmlspecialchars($devLoginUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($devLoginLabel, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <p style="margin-top: 1rem; color: #6b7280; font-size: 0.95rem;">
            現在の戻り先: <?php echo htmlspecialchars($resolvedClientUrl, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </main>
</body>
</html>
