<?php

use GuzzleHttp\Client;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use League\OAuth2\Client\Provider\GenericProvider;

function hcimlab_sso_config()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/sso_config.php';
    }
    return $config;
}

function hcimlab_sso_require_dependencies()
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException('SSO dependencies are not installed. Run scripts/setup-local.sh or composer install in the project root.');
    }
    require_once $autoload;
}

function hcimlab_sso_http_json($url, array $headers = array())
{
    hcimlab_sso_require_dependencies();
    $response = hcimlab_sso_http_client()->request('GET', $url, array(
        'headers' => array_merge(array('Accept' => 'application/json'), hcimlab_sso_header_pairs($headers)),
    ));
    $json = json_decode((string)$response->getBody(), true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON response from ' . $url);
    }
    return $json;
}

function hcimlab_sso_header_pairs(array $headers)
{
    $result = array();
    foreach ($headers as $header) {
        $parts = explode(':', $header, 2);
        if (count($parts) === 2) {
            $result[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $result;
}

function hcimlab_sso_http_options()
{
    $config = hcimlab_sso_config();
    $options = array(
        'timeout' => 10,
    );

    if (!empty($config['ca_bundle'])) {
        if (!file_exists($config['ca_bundle'])) {
            throw new RuntimeException('SSL CA bundle not found: ' . $config['ca_bundle']);
        }
        $options['verify'] = $config['ca_bundle'];
    }

    return $options;
}

function hcimlab_sso_http_client()
{
    static $client = null;
    if ($client === null) {
        $client = new Client(hcimlab_sso_http_options());
    }
    return $client;
}

function hcimlab_sso_discovery()
{
    static $metadata = null;
    if ($metadata !== null) {
        return $metadata;
    }

    $config = hcimlab_sso_config();
    $metadata = hcimlab_sso_http_json(rtrim($config['idp_url'], '/') . '/.well-known/openid-configuration');
    foreach (array('issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri') as $key) {
        if (empty($metadata[$key])) {
            throw new RuntimeException('OIDC discovery metadata is missing ' . $key);
        }
    }
    return $metadata;
}

function hcimlab_sso_provider()
{
    hcimlab_sso_require_dependencies();
    $config = hcimlab_sso_config();
    if ($config['client_id'] === '') {
        throw new RuntimeException('HCIMLAB_SSO_CLIENT_ID is not configured.');
    }

    $metadata = hcimlab_sso_discovery();
    return new GenericProvider(
        array(
            'clientId' => $config['client_id'],
            'clientSecret' => $config['client_secret'],
            'redirectUri' => $config['redirect_uri'],
            'urlAuthorize' => $metadata['authorization_endpoint'],
            'urlAccessToken' => $metadata['token_endpoint'],
            'urlResourceOwnerDetails' => $metadata['userinfo_endpoint'],
            'scopes' => $config['scope'],
            'pkceMethod' => 'S256',
        ),
        array(
            'httpClient' => hcimlab_sso_http_client(),
        )
    );
}

function hcimlab_sso_verify_id_token($idToken, $expectedNonce)
{
    hcimlab_sso_require_dependencies();
    $config = hcimlab_sso_config();
    $metadata = hcimlab_sso_discovery();
    $jwks = hcimlab_sso_http_json($metadata['jwks_uri']);
    $keys = JWK::parseKeySet($jwks);

    JWT::$leeway = 60;
    $decoded = JWT::decode($idToken, $keys);
    $claims = json_decode(json_encode($decoded), true);
    if (!is_array($claims)) {
        throw new RuntimeException('Invalid id_token claims.');
    }

    if (!isset($claims['iss']) || $claims['iss'] !== $metadata['issuer']) {
        throw new RuntimeException('Invalid id_token issuer.');
    }

    $aud = isset($claims['aud']) ? $claims['aud'] : null;
    $validAudience = is_array($aud)
        ? in_array($config['client_id'], $aud, true)
        : $aud === $config['client_id'];
    if (!$validAudience) {
        throw new RuntimeException('Invalid id_token audience.');
    }

    if (!isset($claims['sub']) || (string)$claims['sub'] === '') {
        throw new RuntimeException('id_token does not contain sub.');
    }

    if (!isset($claims['nonce']) || !hash_equals((string)$expectedNonce, (string)$claims['nonce'])) {
        throw new RuntimeException('Invalid id_token nonce.');
    }

    return $claims;
}

function hcimlab_sso_redirect_to_login($message)
{
    $config = hcimlab_sso_config();
    $_SESSION['SSO_ERROR'] = $message;
    header('Location: ' . rtrim($config['base_url'], '/') . '/login.php');
    exit;
}

function hcimlab_sso_generate_local_user_id(mysqli $mysqli)
{
    for ($i = 0; $i < 30; $i++) {
        if (function_exists('random_int')) {
            $candidate = random_int(10000, 2147483647);
        } else {
            $candidate = mt_rand(10000, 2147483647);
        }

        $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }
        $stmt->bind_param('i', $candidate);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            return $candidate;
        }
    }

    throw new RuntimeException('Failed to generate a unique local user_id.');
}

function hcimlab_sso_claim($claims, $key, $default = null)
{
    return array_key_exists($key, $claims) ? $claims[$key] : $default;
}

function hcimlab_sso_upsert_user(mysqli $mysqli, array $claims)
{
    $sub = (string)hcimlab_sso_claim($claims, 'sub', '');
    if ($sub === '') {
        throw new RuntimeException('SSO claims do not contain sub.');
    }

    if (array_key_exists('is_active', $claims) && !$claims['is_active']) {
        throw new RuntimeException('This SSO user is inactive.');
    }

    $name = hcimlab_sso_claim($claims, 'name')
        ?: hcimlab_sso_claim($claims, 'preferred_username')
        ?: hcimlab_sso_claim($claims, 'username')
        ?: hcimlab_sso_claim($claims, 'email')
        ?: $sub;
    $labUserId = hcimlab_sso_claim($claims, 'user_id');
    $username = hcimlab_sso_claim($claims, 'username') ?: hcimlab_sso_claim($claims, 'preferred_username');
    $email = hcimlab_sso_claim($claims, 'email');
    $role = hcimlab_sso_claim($claims, 'role');
    $isActive = array_key_exists('is_active', $claims) ? ($claims['is_active'] ? 1 : 0) : 1;
    $claimsJson = json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE sso_sub = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }
    $stmt->bind_param('s', $sub);
    $stmt->execute();
    $stmt->bind_result($localUserId);
    $found = $stmt->fetch();
    $stmt->close();

    if ($found) {
        $stmt = $mysqli->prepare(
            'UPDATE users SET name = ?, sso_user_id = ?, sso_username = ?, email = ?, display_name = ?, role = ?, is_active = ?, sso_claims_json = ?, sso_updated_at = NOW(), login_time = NOW() WHERE sso_sub = ?'
        );
        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }
        $stmt->bind_param('ssssssiss', $name, $labUserId, $username, $email, $name, $role, $isActive, $claimsJson, $sub);
        $stmt->execute();
        $stmt->close();
        return array('user_id' => (int)$localUserId, 'name' => $name);
    }

    $localUserId = hcimlab_sso_generate_local_user_id($mysqli);
    $password = 'SSO_LOGIN_DISABLED';
    $stmt = $mysqli->prepare(
        'INSERT INTO users (user_id, sso_sub, name, login_time, password, sso_user_id, sso_username, email, display_name, role, is_active, sso_claims_json, sso_updated_at) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }
    $stmt->bind_param('issssssssis', $localUserId, $sub, $name, $password, $labUserId, $username, $email, $name, $role, $isActive, $claimsJson);
    $stmt->execute();
    $stmt->close();

    return array('user_id' => (int)$localUserId, 'name' => $name);
}
