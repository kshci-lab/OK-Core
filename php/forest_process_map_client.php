<?php

declare(strict_types=1);

final class ForestProcessMapException extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 503, public string $errorCode = 'FOREST_UNAVAILABLE')
    {
        parent::__construct($message);
    }
}

function forest_process_map_config(): array
{
    $config = ['base_url' => '', 'token' => '', 'timeout_seconds' => 10];
    $localPath = __DIR__ . '/forest_process_map_local.php';
    if (is_file($localPath)) {
        $local = require $localPath;
        if (is_array($local)) {
            $config = array_replace($config, $local);
        }
    }
    foreach ([
        'base_url' => 'FOREST_CONTEXT_API_BASE_URL',
        'token' => 'FOREST_CONTEXT_API_TOKEN',
        'timeout_seconds' => 'FOREST_CONTEXT_API_TIMEOUT_SECONDS',
    ] as $key => $environmentName) {
        $value = getenv($environmentName);
        if ($value !== false && $value !== '') {
            $config[$key] = $value;
        }
    }
    $config['base_url'] = rtrim((string)$config['base_url'], '/');
    $config['token'] = (string)$config['token'];
    $config['timeout_seconds'] = max(1, (int)$config['timeout_seconds']);
    return $config;
}

function forest_process_map_fetch(int $externalKfId, string $actingSub, int $groupId): array
{
    if ($externalKfId <= 0 || $groupId <= 0 || $actingSub === '' || strlen($actingSub) > 191
        || strpbrk($actingSub, "\r\n") !== false) {
        throw new ForestProcessMapException('参照するKFまたはユーザーが正しくありません。', 400, 'INVALID_REFERENCE');
    }
    $config = forest_process_map_config();
    if ($config['base_url'] === '' || $config['token'] === '') {
        throw new ForestProcessMapException('Forest参照APIのURLまたはトークンが設定されていません。', 503, 'FOREST_NOT_CONFIGURED');
    }
    if (!preg_match('#^https?://[^/]+/#', $config['base_url'] . '/')) {
        throw new ForestProcessMapException('Forest参照APIのURLが正しくありません。', 503, 'FOREST_INVALID_URL');
    }
    if (!function_exists('curl_init')) {
        throw new ForestProcessMapException('PHPのcURL拡張が必要です。', 503, 'HTTP_CLIENT_UNAVAILABLE');
    }
    require_once dirname(__DIR__) . '/api/v1/service.php';
    $signingToken = (string)(ok_core_api_config()['token'] ?? '');
    if ($signingToken === '') {
        throw new ForestProcessMapException('OK-CoreのAPIトークンが設定されていません。', 503, 'OK_CORE_PROOF_NOT_CONFIGURED');
    }
    $proofTime = (string)time();
    $proofPayload = "GET\n{$externalKfId}\n{$groupId}\n{$actingSub}\n{$proofTime}";
    $proof = hash_hmac('sha256', $proofPayload, $signingToken);

    $url = $config['base_url'] . '/knowledge-fragments/' . $externalKfId . '/thinking-process-map';
    $requestId = 'ok-core-process-' . bin2hex(random_bytes(8));
    $handle = curl_init($url);
    if ($handle === false) {
        throw new ForestProcessMapException('Forest参照APIへの接続を開始できません。');
    }
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => $config['timeout_seconds'],
        CURLOPT_CONNECTTIMEOUT => min(5, $config['timeout_seconds']),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $config['token'],
            'X-Acting-User-Sub: ' . $actingSub,
            'X-Knowledge-Group-Id: ' . $groupId,
            'X-OK-Core-Proof-Time: ' . $proofTime,
            'X-OK-Core-Proof: ' . $proof,
            'X-Request-Id: ' . $requestId,
        ],
    ];
    $caPath = dirname(__DIR__) . '/certs/cacert.pem';
    if (str_starts_with($url, 'https://') && is_file($caPath)) {
        $options[CURLOPT_CAINFO] = $caPath;
    }
    curl_setopt_array($handle, $options);
    $body = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);
    if ($body === false) {
        throw new ForestProcessMapException('Forest参照APIへ接続できません: ' . $curlError);
    }
    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) {
        throw new ForestProcessMapException('Forest参照APIからJSONを取得できません。', 502, 'FOREST_INVALID_RESPONSE');
    }
    if ($status < 200 || $status >= 300) {
        $code = (string)($decoded['error']['code'] ?? 'FOREST_API_ERROR');
        $message = (string)($decoded['error']['message'] ?? 'Forest参照APIでエラーが発生しました。');
        throw new ForestProcessMapException($message, in_array($status, [403, 404], true) ? $status : 502, $code);
    }
    if (!isset($decoded['data']) || !is_array($decoded['data'])) {
        throw new ForestProcessMapException('Forest参照APIのデータ形式が正しくありません。', 502, 'FOREST_INVALID_RESPONSE');
    }
    return $decoded['data'];
}
