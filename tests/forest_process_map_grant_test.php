<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../php/forest_process_map_client.php';
require_once __DIR__ . '/../api/v1/service.php';

function grant_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

// Tests the Forest side of delegation. Production grants are issued only after
// get_thinking_process_map.php checks the viewer and the OK-Core shared_nodes row.
$id = (int)($argv[1] ?? 11169);
$group = 100;
$actor = 'ok-core-grant-test';
$map = forest_process_map_fetch($id, $actor, $group);
grant_check((string)$map['external_kf_id'] === (string)$id, 'A valid signed grant must work without a matching Forest group share.');
$reference = (string)$map['source_reference'];
$type = (string)$map['source_type'];
$legacy = forest_process_map_fetch(2147483000, $actor, $group, $reference, $type);
grant_check($legacy['source_reference'] === $reference, 'Legacy lookup must use the signed source node, not the unrelated numeric KF ID.');
grant_check($legacy['process_nodes'] === $map['process_nodes'] && $legacy['node_versions'] === $map['node_versions'], 'Legacy node lookup must return the same map.');

$config = forest_process_map_config();
$key = (string)ok_core_api_config()['token'];
$stamp = (string)time();
$proof = hash_hmac('sha256', "GET\n$id\n$group\n$actor\n$stamp\n$reference\n$type", $key);
foreach (['unsigned', 'tampered_reference', 'tampered_type', 'expired'] as $case) {
    $headers = ['Accept: application/json', 'Authorization: Bearer ' . $config['token'],
        'X-Acting-User-Sub: ' . $actor, 'X-Knowledge-Group-Id: ' . $group,
        'X-OK-Core-Proof-Time: ' . ($case === 'expired' ? (string)(time() - 300) : $stamp),
        'X-KF-Source-Reference: ' . ($case === 'tampered_reference' ? $reference . '-changed' : $reference),
        'X-KF-Source-Type: ' . ($case === 'tampered_type' ? $type . '-changed' : $type)];
    if ($case !== 'unsigned') $headers[] = 'X-OK-Core-Proof: ' . $proof;
    $handle = curl_init($config['base_url'] . '/knowledge-fragments/' . $id . '/thinking-process-map');
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_HTTPHEADER=>$headers]);
    $body = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    $result = json_decode((string)$body, true);
    grant_check($status === 403 && ($result['error']['code'] ?? '') === 'VIEWER_PROOF_INVALID',
        'Forest must reject ' . $case . ' grants.');
}
echo "PASS: signed delegation, legacy node reference, unsigned/tampered/expired grant rejection.\n";
