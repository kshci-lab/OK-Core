<?php
declare(strict_types=1);
require_once __DIR__ . '/knowledge_group_access.php';

/** Canonical references preserve source identity, including equal numeric IDs. */
function ok_core_fragment_refs($raw, string $legacyType = 'experience'): array
{
    if (is_string($raw) && str_starts_with(trim($raw), '[')) {
        $raw = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    }
    if (!is_array($raw)) $raw = explode(',', (string)$raw);
    $refs = [];
    foreach ($raw as $value) {
        if (!is_scalar($value)) throw new InvalidArgumentException('KFの指定が不正です。');
        $value = trim((string)$value);
        if ($value === '') continue;
        if (ctype_digit($value)) $value = $legacyType . ':' . $value;
        if (!preg_match('/^(experience|discussion):([1-9][0-9]*)$/D', $value, $match)
            || strlen($match[2]) > 10 || (int)$match[2] > 2147483647) {
            throw new InvalidArgumentException('KFはexperience:IDまたはdiscussion:IDで指定してください。');
        }
        $ref = $match[1] . ':' . (int)$match[2];
        $refs[$ref] = $ref;
    }
    if (count($refs) > 100) throw new InvalidArgumentException('一度に選択できるKFは100件までです。');
    return array_values($refs);
}

function ok_core_request_fragment_refs(array $request, string $legacyKey = 'knowledge_fragment_id'): array
{
    if (array_key_exists('fragment_refs', $request)) return ok_core_fragment_refs($request['fragment_refs']);
    $type = strtolower(trim((string)($request['fragment_source_type'] ?? 'experience')));
    if ($type === 'externalized') $type = 'discussion';
    if (!in_array($type, ['experience', 'discussion'], true)) throw new InvalidArgumentException('KFのsourceが不正です。');
    return ok_core_fragment_refs($request[$legacyKey] ?? '', $type);
}

function ok_core_refs_by_type(array $refs): array
{
    $types = ['experience' => [], 'discussion' => []];
    foreach ($refs as $ref) {
        [$type, $id] = explode(':', $ref, 2);
        $types[$type][] = (int)$id;
    }
    return $types;
}

function ok_core_group_has_fragment_refs(mysqli $db, string $group, array $refs): bool
{
    if (!$refs) return false;
    foreach (ok_core_refs_by_type($refs) as $type => $ids) {
        if ($ids && !ok_core_group_has_fragments($db, $group, $type, $ids)) return false;
    }
    return true;
}
