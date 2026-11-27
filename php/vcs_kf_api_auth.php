<?php
declare(strict_types=1);

function vcs_kf_api_auth_required(): bool
{
    return getenv('OKCORE_VCS_AUTH_REQUIRED') === '1';
}

function vcs_kf_require_service_bearer(): void
{
    if (!vcs_kf_api_auth_required()) return;

    $path = (string)getenv('OKCORE_VCS_TOKEN_FILE');
    $expected = $path !== '' ? trim((string)@file_get_contents($path)) : '';
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($expected === '' || !preg_match('/^Bearer ([^\s]+)$/iD', $authorization, $match)
        || !hash_equals($expected, $match[1])) {
        vcs_kf_api_forbidden(401, 'Authentication required');
    }
}

function vcs_kf_require_source(string $system, string $type): void
{
    if (vcs_kf_api_auth_required() && ($system !== 'discussion-kf-system' || $type !== 'discussion')) {
        vcs_kf_api_forbidden(403, 'Source is not allowed');
    }
}

function vcs_kf_require_group(string $groupId): void
{
    if (!vcs_kf_api_auth_required()) return;

    $allowed = array_map('trim', explode(',', (string)getenv('OKCORE_VCS_ALLOWED_GROUP_IDS')));
    if (!ctype_digit($groupId) || (int)$groupId <= 0 || !in_array($groupId, $allowed, true)) {
        vcs_kf_api_forbidden(403, 'Group is not allowed');
    }
}

function vcs_kf_api_forbidden(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
