<?php

function hcimlab_session_cookie_path()
{
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $path = rtrim(dirname($scriptPath), '/');

        $parentDir = basename($path);
        if (in_array($parentDir, array('auth', 'php', 'scripts'), true)) {
            $path = rtrim(dirname($path), '/');
        }

        if ($path === '') {
            return '/';
        }
        return $path . '/';
    }

    return '/';
}

function hcimlab_start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (function_exists('session_name')) {
        session_name('OKCORESESSID');
    }

    if (function_exists('session_set_cookie_params')) {
        $params = session_get_cookie_params();
        $cookieParams = array(
            'lifetime' => isset($params['lifetime']) ? (int)$params['lifetime'] : 0,
            'path' => '/',
            'domain' => isset($params['domain']) ? (string)$params['domain'] : '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
        );

        if (PHP_VERSION_ID >= 70300) {
            $cookieParams['samesite'] = 'Lax';
            session_set_cookie_params($cookieParams);
        } else {
            session_set_cookie_params(
                $cookieParams['lifetime'],
                $cookieParams['path'] . '; samesite=Lax',
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }
    }

    if (function_exists('ini_set')) {
        @ini_set('session.use_strict_mode', '1');
    }

    session_start();
}
