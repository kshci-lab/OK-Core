<?php

// CLI-only inspection of the effective SSO settings. Never prints the secret.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/hcimlab_sso.php';

$config = hcimlab_sso_config();
$environmentNames = array(
    'HCIMLAB_SSO_CLIENT_ID',
    'HCIMLAB_SSO_CLIENT_SECRET',
    'HCIMLAB_SSO_BASE_URL',
    'HCIMLAB_SSO_REDIRECT_URI',
    'HCIMLAB_SSO_IDP_URL',
);

echo 'APP_ENV: ' . $config['environment'] . PHP_EOL;
echo 'SSO configured: ' . (hcimlab_sso_is_oauth_configured() ? 'yes' : 'no') . PHP_EOL;
echo 'Client ID: ' . $config['client_id'] . PHP_EOL;
echo 'Client secret present: ' . ($config['client_secret'] !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'Base URL: ' . $config['base_url'] . PHP_EOL;
echo 'Redirect URI: ' . $config['redirect_uri'] . PHP_EOL;
echo 'IdP URL: ' . $config['idp_url'] . PHP_EOL;
echo 'Token auth method: ' . $config['token_auth_method'] . PHP_EOL;
echo 'Local config file present: ' . (is_file(__DIR__ . '/../php/sso_local.php') ? 'yes' : 'no') . PHP_EOL;
foreach ($environmentNames as $environmentName) {
    echo $environmentName . ' set: ' . (getenv($environmentName) !== false ? 'yes' : 'no') . PHP_EOL;
}

if ($config['environment'] === 'production') {
    if (stripos($config['base_url'], 'localhost') !== false || stripos($config['redirect_uri'], 'localhost') !== false) {
        fwrite(STDERR, 'Production SSO URL still points to localhost.' . PHP_EOL);
        exit(1);
    }
    if (!hcimlab_sso_is_oauth_configured()) {
        fwrite(STDERR, 'Production SSO client credentials are missing or placeholders.' . PHP_EOL);
        exit(1);
    }
}
