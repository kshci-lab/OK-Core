<?php
session_start();

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
        <a class="primary-link" href="login.php">ログインへ戻る</a>
    </main>
</body>
</html>
