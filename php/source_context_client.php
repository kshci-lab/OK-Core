<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/v1/service.php';
require_once __DIR__ . '/deployment_urls.php';

final class SourceContextException extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 503, public string $errorCode = 'SOURCE_UNAVAILABLE') { parent::__construct($message); }
}

function source_context_settings(string $system): array
{
    try { $sources = ok_core_api_sources(ok_core_api_config()); }
    catch (OkCoreApiFailure $error) { throw new SourceContextException('産出システムのAPI設定を確認してください。', 503, $error->apiCode); }
    $source = $sources[$system] ?? null;
    if (!$source) throw new SourceContextException('産出システムの参照設定がありません。', 503, 'SOURCE_NOT_CONFIGURED');
    $base = (string)($source['context_base_url'] ?? '');
    $readToken = (string)($source['context_token'] ?? '');
    $timeout = (int)($source['context_timeout_seconds'] ?? 10);
    $automaticUrls = (bool)($source['auto_detect_urls'] ?? true);
    if ($system === 'forest-platform' && !array_key_exists('context_base_url', $source)) {
        require_once __DIR__ . '/forest_process_map_client.php';
        $legacy = forest_process_map_config(array_key_exists('auto_detect_urls', $source) ? $automaticUrls : null);
        $base = $legacy['base_url']; $readToken = $legacy['token']; $timeout = $legacy['timeout_seconds'];
        $automaticUrls = (bool)($legacy['auto_detect_urls'] ?? $automaticUrls);
    }
    if ($system === 'forest-platform') $base = kf_deployment_url($base, 'forest-platform', '/api/v1', null, null, $automaticUrls);
    $url = parse_url($base);
    if ($base === '' || $readToken === '') throw new SourceContextException('産出元参照APIのURLまたはトークンが設定されていません。', 503, 'SOURCE_NOT_CONFIGURED');
    if (!$url || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host']) ||
        isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
        throw new SourceContextException('産出元参照APIのURLが正しくありません。', 503, 'SOURCE_INVALID_URL');
    }
    return ['base_url' => rtrim($base, '/'), 'read_token' => $readToken, 'proof_key' => $source['token'],
        'timeout' => max(1, min(60, $timeout))];
}

function source_context_grant_payload(string $system, string $resource, string $format, string $id,
    int $group, string $actor, string $time, string $reference = '', string $referenceType = ''): string
{
    return implode("\n", ['OK_CORE_CONTEXT_V1', $system, 'GET', $resource, $format,
        $id, (string)$group, $actor, $time, $reference, $referenceType]);
}

function source_context_fetch(string $system, string $id, string $actor, int $group, string $format,
    string $packageId = '', string $reference = '', string $referenceType = ''): array
{
    if (!ok_core_api_external_id($id) || $actor === '' || strlen($actor) > 191 || $group <= 0 ||
        strlen($reference) > 191 || strlen($referenceType) > 64 || preg_match('/[\r\n]/', $actor . $reference . $referenceType)) {
        throw new SourceContextException('文脈の参照情報が正しくありません。', 400, 'INVALID_REFERENCE');
    }
    if (!in_array($format, ['context-package.v1', 'forest.thinking-process-map.v1'], true)) {
        throw new SourceContextException('対応していない文脈形式です。', 422, 'UNSUPPORTED_CONTEXT_FORMAT');
    }
    $config = source_context_settings($system);
    if (!function_exists('curl_init')) throw new SourceContextException('PHPのcURL拡張が必要です。', 503, 'HTTP_CLIENT_UNAVAILABLE');
    $resource = $packageId !== '' ? '/kf-context-packages/' . rawurlencode($packageId)
        : '/knowledge-fragments/' . rawurlencode($id) . '/context';
    $time = (string)time();
    $proof = hash_hmac('sha256', source_context_grant_payload($system, $resource, $format, $id, $group,
        $actor, $time, $reference, $referenceType), $config['proof_key']);
    $requestId = 'ok-core-context-' . bin2hex(random_bytes(8));
    $handle = curl_init($config['base_url'] . $resource . '?format=' . rawurlencode($format));
    if ($handle === false) throw new SourceContextException('産出元参照APIを開始できません。');
    $body = ''; $tooLarge = false;
    $options = [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => $config['timeout'], CURLOPT_CONNECTTIMEOUT => min(5, $config['timeout']),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $config['read_token'],
            'X-Source-System-Code: ' . $system, 'X-External-KF-Id: ' . $id,
            'X-Acting-User-Sub: ' . $actor, 'X-Knowledge-Group-Id: ' . $group,
            'X-OK-Core-Proof-Version: context-v1', 'X-OK-Core-Proof-Time: ' . $time, 'X-OK-Core-Proof: ' . $proof,
            'X-KF-Source-Reference: ' . $reference, 'X-KF-Source-Type: ' . $referenceType, 'X-Request-Id: ' . $requestId],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 8388608) { $tooLarge = true; return 0; }
            $body .= $chunk; return strlen($chunk);
        },
    ];
    $ca = dirname(__DIR__) . '/certs/cacert.pem';
    if (str_starts_with($config['base_url'], 'https://') && is_file($ca)) $options[CURLOPT_CAINFO] = $ca;
    curl_setopt_array($handle, $options);
    $success = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if ($success === false) throw new SourceContextException($tooLarge ? '産出元の応答が大きすぎます。' : '産出元参照APIへ接続できません。',
        $tooLarge ? 502 : 503, $tooLarge ? 'SOURCE_RESPONSE_TOO_LARGE' : 'SOURCE_UNAVAILABLE');
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new SourceContextException('産出元からJSONを取得できません。', 502, 'SOURCE_INVALID_RESPONSE');
    if ($status < 200 || $status >= 300) throw new SourceContextException(
        (string)($decoded['error']['message'] ?? '産出元参照APIでエラーが発生しました。'),
        in_array($status, [403, 404], true) ? $status : 502, (string)($decoded['error']['code'] ?? 'SOURCE_API_ERROR'));
    $data = $decoded['data'] ?? null;
    if (!is_array($data) || ($data['format'] ?? '') !== $format || (string)($data['external_kf_id'] ?? '') !== $id ||
        ($format === 'context-package.v1' && (!is_array($data['context_package']['items'] ?? null) ||
            ($packageId !== '' && (string)($data['context_package']['external_context_package_id'] ?? '') !== $packageId))) ||
        ($format === 'forest.thinking-process-map.v1' && (!is_array($data['map'] ?? null) ||
            (string)($data['map']['external_kf_id'] ?? '') !== $id ||
            ($reference !== '' && (string)($data['map']['source_reference'] ?? '') !== $reference)))) {
        throw new SourceContextException('産出元の応答と参照対象が一致しません。', 502, 'SOURCE_IDENTITY_MISMATCH');
    }
    return $data;
}
