<?php
declare(strict_types=1);

// This deployment's known destinations only. Never build an API destination from an arbitrary Host.
if (!function_exists((__NAMESPACE__ === '' ? '' : __NAMESPACE__ . '\\') . 'kf_deployment_url')) {
    function kf_deployment_url(string $configured, string $application, string $suffix,
        ?array $server = null, ?array $environment = null, bool $automatic = true): string
    {
        $configured = rtrim(trim($configured), '/');
        if (!$automatic || !in_array($application, ['forest-platform', 'OK-Core'], true)) return $configured;
        $server ??= $_SERVER;
        $environment ??= getenv();
        $url = parse_url($configured);
        $known = $configured === '' || $configured === 'auto';
        if (is_array($url) && in_array(strtolower($url['host'] ?? ''), ['localhost', '127.0.0.1', '[::1]', '::1', 'archive.kshci-lab.net'], true)
            && !isset($url['user']) && !isset($url['pass']) && !isset($url['query']) && !isset($url['fragment'])
            && in_array($url['scheme'] ?? '', ['http', 'https'], true)) {
            $known = in_array(rtrim($url['path'] ?? '', '/'), ['/' . $application . $suffix, '/software/' . $application . $suffix], true);
        }
        // Custom hosts and paths remain explicit configuration (e.g. an independent source system).
        if (!$known) return $configured;
        $mode = strtolower(trim((string)($environment['APP_ENV'] ?? '')));
        $authority = strtolower(trim((string)($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '')));
        $local = preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::([0-9]{1,5}))?$/D', $authority, $parts) === 1;
        if ($local && isset($parts[2]) && ((int)$parts[2] < 1 || (int)$parts[2] > 65535)) $local = false;
        $production = in_array($authority, ['archive.kshci-lab.net', 'archive.kshci-lab.net:443'], true);
        if (in_array($mode, ['production', 'prod'], true)) $profile = 'production';
        elseif (in_array($mode, ['local', 'development', 'dev', 'test', 'testing'], true)) $profile = 'local';
        elseif ($production) $profile = 'production';
        elseif ($local) $profile = 'local';
        else return $configured === 'auto' ? '' : $configured; // CLI needs APP_ENV or its existing explicit URL.

        if ($profile === 'production') return 'https://archive.kshci-lab.net/software/' . $application . $suffix;
        $origin = 'http://localhost:8888';
        if ($local) {
            $secure = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') || (int)($server['SERVER_PORT'] ?? 0) === 443;
            $origin = ($secure ? 'https://' : 'http://') . $authority;
        }
        $prefix = '';
        $script = str_replace('\\', '/', (string)($server['SCRIPT_NAME'] ?? ''));
        if (preg_match('#^((?:/[A-Za-z0-9._~-]+)*)/(?:forest-platform|OK-Core)(?:/|$)#D', $script, $match)
            && !preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $match[1])) $prefix = $match[1];
        elseif ($script === '' && is_array($url) && str_starts_with($url['path'] ?? '', '/software/')) $prefix = '/software';
        return $origin . $prefix . '/' . $application . $suffix;
    }
}
