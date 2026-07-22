<?php
require_once __DIR__ . '/php/session_bootstrap.php';
hcimlab_start_session();
require_once __DIR__ . '/php/hcimlab_sso.php';

$baseUrl = rtrim(hcimlab_sso_config()['base_url'], '/');
$canonicalPath = parse_url($baseUrl . '/logout.php', PHP_URL_PATH);
$requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
if ($canonicalPath && $requestPath && $requestPath !== $canonicalPath) {
    $queryString = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
    header('Location: ' . $baseUrl . '/logout.php' . $queryString);
    exit;
}

$message = isset($_SESSION['USERID'])
    ? 'ログアウトしました。'
    : 'セッションが終了しています。';

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>OK-Core Logout</title>
    <link rel="stylesheet" href="css/ok-core.css">
</head>
<body class="auth-page">
    <main class="auth-box">
        <h1>OK-Core</h1>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="primary-link" href="<?php echo htmlspecialchars($baseUrl . '/login.php', ENT_QUOTES, 'UTF-8'); ?>">ログインへ戻る</a>
    </main>
</body>
</html>
