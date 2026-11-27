<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$api = file_get_contents($root . '/php/api/import_okdiag_kf.php');
$service = file_get_contents($root . '/php/api/okdiag_kf_import_service.php');
$legacy = file_get_contents($root . '/php/import_kf_stub.php');
foreach ([
    'OKDIAG_API_TOKEN' => $service,
    'HTTP_AUTHORIZATION' => $service,
    'okdiag_provenance' => $service,
    'externalized_contents' => $service,
    'Conflicting source identifier' => $service,
    'OKDiag must use /php/api/import_okdiag_kf.php' => $legacy,
] as $needle => $haystack) {
    if (strpos($haystack, $needle) === false) { fwrite(STDERR, "Missing contract: {$needle}\n"); exit(1); }
}
echo "OKDiag API contract checks passed\n";
