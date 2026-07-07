<?php
session_start();

$errorMessage = '';
if (!empty($_SESSION['SSO_ERROR'])) {
    $errorMessage = $_SESSION['SSO_ERROR'];
    unset($_SESSION['SSO_ERROR']);
}

if ($errorMessage === 'SSO dependencies are not installed. Run scripts/setup-local.sh or composer install in the project root.'
    && file_exists(__DIR__ . '/vendor/autoload.php')) {
    $errorMessage = '';
}

if ($errorMessage === '') {
    header('Location: auth/login.php');
    exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>OK-Core Login</title>
    <link rel="stylesheet" href="css/ok-core.css">
</head>
<body class="auth-page">
    <main class="auth-box">
        <h1>OK-Core</h1>
        <p>SSOログインに失敗しました。</p>
        <pre><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></pre>
        <a class="primary-link" href="auth/login.php">SSOログインを再試行</a>
    </main>
</body>
</html>
