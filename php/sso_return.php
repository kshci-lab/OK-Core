<?php

// A separate, one-shot destination for OKAgent; never use an IdP redirect URI
// or an arbitrary clientUrl as the post-login destination.
function hcimlab_sso_is_safe_agent_return($url, $baseUrl)
{
    if (!is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
        return false;
    }
    $target = parse_url($url);
    $base = parse_url($baseUrl);
    if (!$target || !$base || isset($target['user']) || isset($target['pass'])) {
        return false;
    }
    $scheme = strtolower($target['scheme'] ?? '');
    $port = $target['port'] ?? ($scheme === 'https' ? 443 : 80);
    $baseScheme = strtolower($base['scheme'] ?? '');
    $basePort = $base['port'] ?? ($baseScheme === 'https' ? 443 : 80);
    return in_array($scheme, ['http', 'https'], true)
        && $scheme === $baseScheme
        && strtolower($target['host'] ?? '') === strtolower($base['host'] ?? '')
        && $port === $basePort
        && in_array($target['path'] ?? '', ['/OKAgent/', '/OKAgent/index.php', '/OKAgent/dist/', '/OKAgent/dist/index.php'], true);
}

function hcimlab_sso_capture_agent_return($baseUrl)
{
    if (array_key_exists('return_to', $_GET)) {
        unset($_SESSION['OKAGENT_LOGIN_RETURN']);
        if (!hcimlab_sso_is_safe_agent_return($_GET['return_to'], $baseUrl)) {
            throw new RuntimeException('Invalid OKAgent return URL.');
        }
        $_SESSION['OKAGENT_LOGIN_RETURN'] = $_GET['return_to'];
    }
}

function hcimlab_sso_consume_agent_return($baseUrl)
{
    $url = $_SESSION['OKAGENT_LOGIN_RETURN'] ?? null;
    unset($_SESSION['OKAGENT_LOGIN_RETURN']);
    return hcimlab_sso_is_safe_agent_return($url, $baseUrl)
        ? $url : rtrim($baseUrl, '/') . '/index.php';
}
