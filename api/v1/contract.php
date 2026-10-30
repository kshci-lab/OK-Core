<?php
declare(strict_types=1);

// API contract and validation. No database or secret configuration is loaded here.
final class OkCoreApiFailure extends RuntimeException
{
    public int $httpStatus;
    public string $apiCode;
    public array $details;

    public function __construct(int $status, string $code, string $message, array $details = [])
    {
        parent::__construct($message);
        $this->httpStatus = $status;
        $this->apiCode = $code;
        $this->details = $details;
    }
}

function ok_core_api_require(bool $condition, int $status, string $code, string $message): void
{
    if (!$condition) throw new OkCoreApiFailure($status, $code, $message);
}

function ok_core_api_field(bool $condition, int $status, string $code, string $field, string $message): void
{
    if (!$condition) throw new OkCoreApiFailure($status, $code, $message, [['field' => $field, 'reason' => $code]]);
}

function ok_core_api_text($value, int $min, int $max): bool
{
    if (!is_string($value) || !preg_match('//u', $value)) return false;
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
    return $length >= $min && $length <= $max;
}

function ok_core_api_positive_integer($value, string $maximum = '9223372036854775807'): bool
{
    // JSON Schema treats mathematically integral JSON numbers (e.g. 1.0) as integers.
    if (is_float($value) && is_finite($value) && floor($value) === $value) $value = sprintf('%.0f', $value);
    if (!is_int($value) && !is_string($value)) return false;
    $raw = (string)$value;
    return preg_match('/^[1-9][0-9]*$/D', $raw) === 1 &&
        (strlen($raw) < strlen($maximum) || (strlen($raw) === strlen($maximum) && strcmp($raw, $maximum) <= 0));
}

function ok_core_api_external_id(string $id): bool
{
    return ok_core_api_text($id, 1, 191) && trim($id) === $id && !preg_match('/[\x00-\x20\x7f\/\\\\]/u', $id);
}

function ok_core_api_is_iso_time(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value)) return false;
    try {
        new DateTimeImmutable($value);
        $errors = DateTimeImmutable::getLastErrors();
        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    } catch (Throwable $error) { return false; }
}

/** An explicit source registry takes precedence over the legacy single-token setting. */
function ok_core_api_sources(array $config): array
{
    $legacy = !array_key_exists('source_systems', $config);
    $raw = $legacy ? [(string)($config['system_code'] ?? 'forest-platform') => [
        'token' => (string)($config['token'] ?? ''), 'share_policy' => 'replace',
        'legacy_adopt_numeric_ids' => true,
    ]] : $config['source_systems'];
    ok_core_api_require(is_array($raw), 503, 'API_AUTH_NOT_CONFIGURED', 'Invalid source system configuration.');
    $sources = [];
    $tokens = [];
    $allScopes = ['groups:read', 'fragments:write', 'fragments:delete'];
    foreach ($raw as $code => $settings) {
        ok_core_api_require(is_string($code) && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $code) === 1 && is_array($settings),
            503, 'API_AUTH_NOT_CONFIGURED', 'Invalid source system configuration.');
        ok_core_api_require(!isset($settings['enabled']) || is_bool($settings['enabled']),
            503, 'API_AUTH_NOT_CONFIGURED', 'Invalid source system enabled flag.');
        if (($settings['enabled'] ?? true) === false) continue;
        $token = $settings['token'] ?? '';
        ok_core_api_require(is_string($token) && $token !== '' && !preg_match('/[\x00-\x20\x7f]/', $token),
            503, 'API_AUTH_NOT_CONFIGURED', 'A source system token is not configured.');
        $hash = hash('sha256', $token);
        ok_core_api_require(!isset($tokens[$hash]), 503, 'API_AUTH_NOT_CONFIGURED', 'Source systems must use distinct tokens.');
        $tokens[$hash] = true;
        $scopes = $settings['scopes'] ?? $allScopes;
        ok_core_api_require(is_array($scopes) && array_is_list($scopes) &&
            count(array_filter($scopes, static fn($scope) => !is_string($scope) || !in_array($scope, $allScopes, true))) === 0,
            503, 'API_AUTH_NOT_CONFIGURED', 'Invalid source system scopes.');
        $policy = $settings['share_policy'] ?? 'merge';
        ok_core_api_require(in_array($policy, ['merge', 'replace'], true), 503, 'API_AUTH_NOT_CONFIGURED', 'Invalid sharing policy.');
        $sources[$code] = array_replace($settings, ['token' => $token, 'enabled' => true, 'scopes' => $scopes,
            'share_policy' => $policy, 'legacy_adopt_numeric_ids' => ($settings['legacy_adopt_numeric_ids'] ?? false) === true]);
        $sources[$code]['system_code'] = $code;
    }
    ok_core_api_require($sources !== [], 503, 'API_AUTH_NOT_CONFIGURED', 'No active source systems are configured.');
    return $sources;
}

function ok_core_api_authenticate(array $config, string $authorization, ?string $requestedSystem = null): array
{
    $sources = ok_core_api_sources($config);
    ok_core_api_require(preg_match('/^Bearer (\S+)$/iD', $authorization, $match) === 1,
        401, 'INVALID_TOKEN', 'API token is missing or invalid.');
    $selected = null;
    foreach ($sources as $source) {
        if (hash_equals($source['token'], $match[1])) $selected = $source;
    }
    ok_core_api_require($selected !== null, 401, 'INVALID_TOKEN', 'API token is missing or invalid.');
    ok_core_api_require($requestedSystem === null || $selected['system_code'] === $requestedSystem,
        403, 'SOURCE_SYSTEM_FORBIDDEN', 'Source system is not allowed.');
    return $selected;
}

function ok_core_api_scope(array $source, string $scope): void
{
    ok_core_api_require(in_array($scope, $source['scopes'] ?? [], true), 403, 'SCOPE_FORBIDDEN', 'This operation is not permitted.');
}

function ok_core_api_capability_data(): array
{
    return [
        'supported_protocols' => [[
            'protocol_code' => 'OK_CORE_KF', 'protocol_version' => '1.0',
            'stages' => array_map(static fn($code) => [
                'stage_code' => $code, 'required' => false, 'max_rendered_content_characters' => 255,
                'slots' => ['required' => false, 'codes' => 'producer_defined', 'max_value_characters' => 65535],
            ], ['stage1', 'stage2', 'stage3']),
        ]],
        'max_payload_bytes' => 1048576,
        'max_summary_characters' => 255,
        'max_external_id_characters' => 191,
        'external_id_type' => 'string',
        'max_source_revision' => '9223372036854775807',
        'authentication' => ['scheme' => 'Bearer', 'acting_user_header' => 'X-Acting-User-Sub',
            'actor_registration' => 'active_ok_core_user_required', 'tokens' => 'per_source_system'],
        'idempotency' => ['identity' => ['system_code', 'external_kf_id'], 'version' => 'source_revision',
            'comparison' => 'json_object_key_order_insensitive_array_order_sensitive', 'idempotency_key_header' => false],
        'sharing' => ['policy_selection' => 'server_source_configuration', 'supported_policies' => ['merge', 'replace'],
            'new_source_default' => 'merge', 'legacy_single_source_default' => 'replace', 'delete' => 'remove_all_shares'],
        'storage' => ['adapter' => 'legacy_experience', 'slots' => 'payload_snapshot', 'normalized_stages_and_slots' => false],
        'context' => ['snapshot' => 'context_package.items', 'generic_live_context_proxy' => true,
            'formats' => ['context-package.v1', 'forest.thinking-process-map.v1'], 'grant_version' => 'context-v1'],
        'schema' => 'schemas/kf-put.schema.json',
    ];
}

function ok_core_api_stages(array $body): array
{
    $stages = ['stage1' => '', 'stage2' => '', 'stage3' => ''];
    ok_core_api_field(isset($body['stages']) && is_array($body['stages']) && array_is_list($body['stages']),
        400, 'INVALID_STAGES', 'stages', 'Stages must be an array.');
    $seen = [];
    foreach ($body['stages'] as $i => $stage) {
        $field = 'stages[' . $i . ']';
        ok_core_api_field(is_array($stage), 400, 'INVALID_STAGES', $field, 'Invalid stage.');
        $code = $stage['stage_code'] ?? null;
        ok_core_api_field(is_string($code) && array_key_exists($code, $stages), 400, 'INVALID_STAGES', $field . '.stage_code', 'Unknown stage code.');
        ok_core_api_field(!isset($seen[$code]), 400, 'INVALID_STAGES', $field . '.stage_code', 'Duplicate stage code.');
        $seen[$code] = true;
        $content = array_key_exists('rendered_content', $stage) ? $stage['rendered_content'] : '';
        ok_core_api_field(ok_core_api_text($content, 0, 255), 422, 'STAGE_TOO_LONG', $field . '.rendered_content', 'Stage must be a string of at most 255 characters.');
        $slots = $stage['slots'] ?? [];
        ok_core_api_field(is_array($slots) && array_is_list($slots), 400, 'INVALID_SLOTS', $field . '.slots', 'Slots must be an array.');
        $slotCodes = [];
        foreach ($slots as $j => $slot) {
            $slotField = $field . '.slots[' . $j . ']';
            ok_core_api_field(is_array($slot) && ok_core_api_text($slot['slot_code'] ?? null, 1, 64) &&
                trim($slot['slot_code']) === $slot['slot_code'], 400, 'INVALID_SLOTS', $slotField . '.slot_code', 'Invalid slot code.');
            ok_core_api_field(!isset($slotCodes[$slot['slot_code']]), 400, 'INVALID_SLOTS', $slotField . '.slot_code', 'Duplicate slot code.');
            $slotCodes[$slot['slot_code']] = true;
            ok_core_api_field(ok_core_api_text($slot['value'] ?? null, 0, 65535), 422, 'INVALID_SLOTS', $slotField . '.value', 'Invalid slot value.');
        }
        $stages[$code] = $content;
    }
    return $stages;
}

function ok_core_api_decode_fragment(string $raw): array
{
    ok_core_api_require(strlen($raw) <= 1048576, 413, 'PAYLOAD_TOO_LARGE', 'Payload exceeds 1 MB.');
    $wire = json_decode($raw);
    ok_core_api_require($wire instanceof stdClass, 400, 'INVALID_JSON', 'Expected a JSON object.');
    try { json_encode($wire, JSON_THROW_ON_ERROR); }
    catch (JsonException $error) { throw new OkCoreApiFailure(400, 'INVALID_JSON', 'JSON contains an unsupported numeric value.'); }
    foreach (['protocol', 'activity', 'context_package'] as $field) {
        ok_core_api_field(isset($wire->$field) && $wire->$field instanceof stdClass,
            400, 'INVALID_JSON', $field, 'Expected a JSON object.');
    }
    ok_core_api_field(isset($wire->context_package->items) && is_array($wire->context_package->items),
        400, 'INVALID_CONTEXT', 'context_package.items', 'Expected an array.');
    foreach ($wire->context_package->items as $i => $item) {
        ok_core_api_field($item instanceof stdClass && isset($item->snapshot) &&
            ($item->snapshot instanceof stdClass || $item->snapshot === []),
            400, 'INVALID_CONTEXT', 'context_package.items[' . $i . '].snapshot', 'Expected an object (legacy empty array is also accepted).');
    }
    foreach (['stages', 'share_group_ids'] as $field) {
        ok_core_api_field(isset($wire->$field) && is_array($wire->$field),
            400, $field === 'stages' ? 'INVALID_STAGES' : 'INVALID_GROUPS', $field, 'Expected an array.');
    }
    foreach ($wire->stages as $i => $stage) {
        ok_core_api_field($stage instanceof stdClass, 400, 'INVALID_STAGES', 'stages[' . $i . ']', 'Expected an object.');
        if (property_exists($stage, 'slots')) {
            ok_core_api_field(is_array($stage->slots), 400, 'INVALID_SLOTS', 'stages[' . $i . '].slots', 'Expected an array.');
            foreach ($stage->slots as $slot) {
                ok_core_api_field($slot instanceof stdClass, 400, 'INVALID_SLOTS', 'stages[' . $i . '].slots', 'Expected objects.');
            }
        }
    }
    return json_decode($raw, true);
}

function ok_core_api_validate_fragment(array $body, string $actingSub): array
{
    ok_core_api_field(is_array($body['protocol'] ?? null) && ($body['protocol']['code'] ?? '') === 'OK_CORE_KF' &&
        ($body['protocol']['version'] ?? '') === '1.0', 422, 'UNSUPPORTED_PROTOCOL', 'protocol', 'Unsupported KF protocol.');
    ok_core_api_field(is_string($body['source_updated_at'] ?? null) && ok_core_api_is_iso_time($body['source_updated_at']),
        400, 'INVALID_UPDATED_AT', 'source_updated_at', 'source_updated_at must be an ISO 8601 timestamp with timezone.');
    ok_core_api_field(ok_core_api_positive_integer($body['source_revision'] ?? null), 400, 'INVALID_REVISION', 'source_revision', 'Invalid source revision.');
    ok_core_api_field(ok_core_api_text($body['expresser_sso_sub'] ?? null, 1, 191), 400, 'INVALID_EXPRESSER', 'expresser_sso_sub', 'Invalid expresser.');
    ok_core_api_field($body['expresser_sso_sub'] === $actingSub, 403, 'EXPRESSER_MISMATCH', 'expresser_sso_sub', 'Expresser differs from acting user.');
    ok_core_api_field(ok_core_api_text($body['summary'] ?? null, 1, 255) && trim($body['summary']) !== '',
        422, 'INVALID_SUMMARY', 'summary', 'Summary must contain 1 to 255 characters.');
    $activity = $body['activity'] ?? null;
    ok_core_api_field(is_array($activity) && ok_core_api_text($activity['type_code'] ?? null, 1, 64) &&
        trim($activity['type_code']) !== '' && ok_core_api_text($activity['external_activity_id'] ?? null, 1, 191) &&
        trim($activity['external_activity_id']) !== '', 400, 'INVALID_ACTIVITY', 'activity', 'Activity reference is required.');
    $context = $body['context_package'] ?? null;
    ok_core_api_field(is_array($context) && ok_core_api_text($context['external_context_package_id'] ?? null, 1, 191) &&
        trim($context['external_context_package_id']) !== '' && is_array($context['items'] ?? null) && array_is_list($context['items']),
        400, 'INVALID_CONTEXT', 'context_package', 'Context package is required.');
    if (array_key_exists('created_at', $context)) {
        ok_core_api_field(is_string($context['created_at']) && ok_core_api_is_iso_time($context['created_at']),
            400, 'INVALID_CONTEXT', 'context_package.created_at', 'Invalid context timestamp.');
    }
    foreach ($context['items'] as $i => $item) {
        ok_core_api_field(is_array($item) && ok_core_api_text($item['entity_type_code'] ?? null, 1, 64) &&
            trim($item['entity_type_code']) !== '' && ok_core_api_text($item['entity_id'] ?? null, 1, 191) &&
            trim($item['entity_id']) !== '' && (is_int($item['display_order'] ?? null) ||
                (is_float($item['display_order'] ?? null) && is_finite($item['display_order']) && floor($item['display_order']) === $item['display_order'])) &&
            $item['display_order'] >= 0 && $item['display_order'] <= 2147483647 &&
            is_array($item['snapshot'] ?? null), 400, 'INVALID_CONTEXT', 'context_package.items[' . $i . ']', 'Invalid context item.');
    }
    ok_core_api_field(isset($body['share_group_ids']) && is_array($body['share_group_ids']) && array_is_list($body['share_group_ids']),
        400, 'INVALID_GROUPS', 'share_group_ids', 'share_group_ids must be an array.');
    foreach ($body['share_group_ids'] as $i => $id) {
        ok_core_api_field(ok_core_api_positive_integer($id, '2147483647'), 403, 'GROUP_FORBIDDEN',
            'share_group_ids[' . $i . ']', 'A requested share group is unavailable.');
    }
    return ok_core_api_stages($body);
}

function ok_core_api_canonical_value($value)
{
    if (is_object($value)) {
        $members = get_object_vars($value);
        ksort($members, SORT_STRING);
        foreach ($members as &$member) $member = ok_core_api_canonical_value($member);
        return (object)$members;
    }
    if (is_array($value)) return array_map('ok_core_api_canonical_value', $value);
    return $value;
}

function ok_core_api_same_snapshot(?string $stored, string $incoming): bool
{
    if ($stored === null) return false;
    try {
        $a = json_decode($stored, false, 512, JSON_THROW_ON_ERROR);
        $b = json_decode($incoming, false, 512, JSON_THROW_ON_ERROR);
        return json_encode(ok_core_api_canonical_value($a), JSON_PRESERVE_ZERO_FRACTION) ===
            json_encode(ok_core_api_canonical_value($b), JSON_PRESERVE_ZERO_FRACTION);
    } catch (JsonException $error) { return false; }
}
