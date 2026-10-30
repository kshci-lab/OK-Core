<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/v1/service.php';

$checks = 0;
function check(bool $value, string $message): void {
    global $checks;
    if (!$value) throw new RuntimeException($message);
    $checks++;
}
function fails(callable $callback, string $code, int $status): void {
    try { $callback(); } catch (OkCoreApiFailure $error) {
        check($error->apiCode === $code && $error->httpStatus === $status, 'Unexpected failure: ' . $error->apiCode);
        return;
    }
    throw new RuntimeException('Expected failure: ' . $code);
}
function payload(): array {
    return json_decode(file_get_contents(__DIR__ . '/../api/v1/examples/kf-put.json'), true, 512, JSON_THROW_ON_ERROR);
}

$registry = ['source_systems' => [
    'forest-platform' => ['token' => 'test-forest-token', 'share_policy' => 'replace'],
    'discussion-kf-system' => ['token' => 'test-discussion-token', 'scopes' => ['groups:read', 'fragments:write']],
    'disabled-system' => ['enabled' => false, 'token' => 'test-disabled-token'],
]];
$forest = ok_core_api_authenticate($registry, 'Bearer test-forest-token', 'forest-platform');
$discussion = ok_core_api_authenticate($registry, 'bearer test-discussion-token', 'discussion-kf-system');
check($forest['share_policy'] === 'replace', 'Forest compatibility policy');
check($discussion['share_policy'] === 'merge', 'New producer defaults to merge');
check($discussion['legacy_adopt_numeric_ids'] === false, 'New producer must not adopt unrelated numeric IDs');
ok_core_api_scope($discussion, 'fragments:write');
fails(fn() => ok_core_api_scope($discussion, 'fragments:delete'), 'SCOPE_FORBIDDEN', 403);
fails(fn() => ok_core_api_authenticate($registry, 'Bearer test-forest-token', 'discussion-kf-system'), 'SOURCE_SYSTEM_FORBIDDEN', 403);
fails(fn() => ok_core_api_authenticate($registry, 'Bearer test-disabled-token'), 'INVALID_TOKEN', 401);
fails(fn() => ok_core_api_authenticate($registry, 'Bearer wrong'), 'INVALID_TOKEN', 401);
fails(fn() => ok_core_api_authenticate(['source_systems' => ['a' => ['token' => 'same'], 'b' => ['token' => 'same']]], 'Bearer same'), 'API_AUTH_NOT_CONFIGURED', 503);
$legacy = ok_core_api_authenticate(['token' => 'old-token', 'system_code' => 'forest-platform'], 'Bearer old-token');
check($legacy['share_policy'] === 'replace' && $legacy['legacy_adopt_numeric_ids'], 'Old config still works');
fails(fn() => ok_core_api_authenticate(['token' => 'old-token', 'source_systems' => []], 'Bearer old-token'), 'API_AUTH_NOT_CONFIGURED', 503);

$body = payload();
$stages = ok_core_api_validate_fragment(ok_core_api_decode_fragment(json_encode($body)), 'example-sso-sub');
check($stages['stage1'] === 'Review the context', 'Existing payload accepted');
$body['stages'] = [];
check(ok_core_api_validate_fragment($body, 'example-sso-sub')['stage1'] === '', 'Empty stage array remains compatible');
foreach (['7', 'knowledge_fragment_7', 'discussion:7', '1785133092019493822', 'abc-01'] as $id) check(ok_core_api_external_id($id), 'String ID rejected: ' . $id);
foreach (['', ' a', 'a ', 'a/b', 'a\\b', "a\nb", str_repeat('x', 192)] as $id) check(!ok_core_api_external_id($id), 'Unsafe/long ID accepted');
check(ok_core_api_positive_integer('9223372036854775807'), 'BIGINT maximum');
check(!ok_core_api_positive_integer('9223372036854775808'), 'BIGINT overflow');
check(!ok_core_api_positive_integer(true), 'Boolean revision accepted');
check(ok_core_api_positive_integer(1.0), 'Integral JSON number compatibility');
check(!ok_core_api_positive_integer('01'), 'Noncanonical revision accepted');
check(ok_core_api_is_iso_time('2026-10-30T10:00:00.123+09:00'), 'Valid ISO time');
foreach (['2026-02-30T10:00:00+09:00', '2026-10-30 10:00:00', '2026-10-30T10:00:00+99:99'] as $date) check(!ok_core_api_is_iso_time($date), 'Invalid ISO time accepted');

foreach ([
    ['summary', str_repeat('あ', 256), 'INVALID_SUMMARY', 422],
    ['summary', ['bad'], 'INVALID_SUMMARY', 422],
    ['source_revision', '9223372036854775808', 'INVALID_REVISION', 400],
    ['expresser_sso_sub', 'another-user', 'EXPRESSER_MISMATCH', 403],
    ['share_group_ids', ['2147483648'], 'GROUP_FORBIDDEN', 403],
    ['activity', ['type_code' => 'map'], 'INVALID_ACTIVITY', 400],
] as [$field, $value, $code, $status]) {
    $body = payload(); $body[$field] = $value;
    fails(fn() => ok_core_api_validate_fragment($body, 'example-sso-sub'), $code, $status);
}
$body = payload(); $body['stages'][] = $body['stages'][0];
fails(fn() => ok_core_api_validate_fragment($body, 'example-sso-sub'), 'INVALID_STAGES', 400);
$body = payload(); $body['stages'][0]['slots'][] = $body['stages'][0]['slots'][0];
fails(fn() => ok_core_api_validate_fragment($body, 'example-sso-sub'), 'INVALID_SLOTS', 400);
$body = payload(); $body['stages'][0]['rendered_content'] = null;
fails(fn() => ok_core_api_validate_fragment($body, 'example-sso-sub'), 'STAGE_TOO_LONG', 422);
$body = payload(); $body['context_package']['created_at'] = null;
fails(fn() => ok_core_api_validate_fragment($body, 'example-sso-sub'), 'INVALID_CONTEXT', 400);
fails(fn() => ok_core_api_decode_fragment('[]'), 'INVALID_JSON', 400);
fails(fn() => ok_core_api_decode_fragment('{broken'), 'INVALID_JSON', 400);
fails(fn() => ok_core_api_decode_fragment('{"value":1e400}'), 'INVALID_JSON', 400);
fails(fn() => ok_core_api_decode_fragment(str_repeat(' ', 1048577)), 'PAYLOAD_TOO_LARGE', 413);
$body = payload(); $body['stages'] = (object)[];
fails(fn() => ok_core_api_decode_fragment(json_encode($body)), 'INVALID_STAGES', 400);
$body = payload(); $body['context_package']['items'][0]['snapshot'] = ['not-an-object'];
fails(fn() => ok_core_api_decode_fragment(json_encode($body)), 'INVALID_CONTEXT', 400);

check(ok_core_api_same_snapshot('{"a":1,"b":{"x":2,"y":3}}', '{"b":{"y":3,"x":2},"a":1}'), 'JSON key order affects retry');
check(!ok_core_api_same_snapshot('{"a":[1,2]}', '{"a":[2,1]}'), 'Array order ignored');
check(!ok_core_api_same_snapshot('{"a":1}', '{"a":"1"}'), 'Scalar types ignored');
check(!ok_core_api_same_snapshot('{"a":{}}', '{"a":[]}'), 'Object/array distinction ignored');
$caps = ok_core_api_capability_data();
check($caps['supported_protocols'][0]['protocol_code'] === 'OK_CORE_KF' && $caps['max_payload_bytes'] === 1048576, 'Legacy capabilities removed');
check($caps['storage']['normalized_stages_and_slots'] === false, 'Advertised unsupported normalization');
check(!str_contains(json_encode($caps), 'test-forest-token'), 'Credentials in public capabilities');
$schema = json_decode(file_get_contents(__DIR__ . '/../api/v1/schemas/kf-put.schema.json'), true, 512, JSON_THROW_ON_ERROR);
check($schema['properties']['summary']['maxLength'] === $caps['max_summary_characters'], 'Schema/capability summary drift');
check($schema['properties']['stages']['items']['properties']['rendered_content']['maxLength'] === 255, 'Schema/stage limit drift');
$spec = json_decode(file_get_contents(__DIR__ . '/../api/v1/openapi.yaml'), true, 512, JSON_THROW_ON_ERROR);
check($spec['openapi'] === '3.1.0' && count($spec['paths']) === 6, 'OpenAPI routes');
echo "PASS: {$checks} API contract checks (no DB writes).\n";
