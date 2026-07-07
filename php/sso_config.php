<?php

function hcimlab_sso_env_or_default($name, $default = null)
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function hcimlab_sso_default_ca_bundle()
{
    $defaultPath = __DIR__ . '/../certs/cacert.pem';
    return file_exists($defaultPath) ? $defaultPath : '';
}

function hcimlab_sso_detect_base_url()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8888';
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';

    $authPos = strpos($script, '/auth/');
    if ($authPos !== false) {
        $basePath = substr($script, 0, $authPos);
    } else {
        $basePath = rtrim(dirname($script), '/\\');
    }

    if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath;
}

$baseUrl = hcimlab_sso_env_or_default('HCIMLAB_SSO_BASE_URL', hcimlab_sso_detect_base_url());

$config = array(
    'idp_url' => hcimlab_sso_env_or_default('HCIMLAB_SSO_IDP_URL', 'https://kshci-lab.net/software/hcimlab_auth'),
    'client_id' => hcimlab_sso_env_or_default('HCIMLAB_SSO_CLIENT_ID', ''),
    'client_secret' => hcimlab_sso_env_or_default('HCIMLAB_SSO_CLIENT_SECRET', ''),
    'base_url' => rtrim($baseUrl, '/'),
    'redirect_uri' => hcimlab_sso_env_or_default('HCIMLAB_SSO_REDIRECT_URI', rtrim($baseUrl, '/') . '/auth/callback'),
    'scope' => hcimlab_sso_env_or_default('HCIMLAB_SSO_SCOPE', 'openid profile email lab'),
    'ca_bundle' => hcimlab_sso_env_or_default('HCIMLAB_SSO_CA_BUNDLE', hcimlab_sso_default_ca_bundle()),
);

$localConfigPath = __DIR__ . '/sso_local.php';
if (file_exists($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

return $config;
