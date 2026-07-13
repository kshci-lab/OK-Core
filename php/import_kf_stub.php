<?php
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    echo json_encode([
        'success' => true,
        'message' => 'OK-Core KF import stub is running. Send POST JSON to this endpoint.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$jsonError = json_last_error();

if ($raw === false || trim($raw) === '' || $jsonError !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON',
        'raw' => $raw === false ? '' : $raw
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'OK-Core received KF successfully',
    'experienceKnowledgeId' => 'mock_' . date('YmdHis'),
    'receivedPayload' => $payload
], JSON_UNESCAPED_UNICODE);
