<?php
require __DIR__ . '/../php/sso_return.php';
function check($value, $message) { if (!$value) { throw new RuntimeException($message); } }
$base = 'http://localhost:8888/OK-Core';
$return = 'http://localhost:8888/OKAgent/?group_id=100&activity=hci';
foreach ([$return, 'http://localhost:8888/OKAgent/index.php?group_id=100', 'http://localhost:8888/OKAgent/dist/?group_id=100'] as $url) {
    check(hcimlab_sso_is_safe_agent_return($url, $base), 'Allow registered entry');
}
foreach ([null, [], '//evil.test/OKAgent/', 'https://localhost:8888/OKAgent/', 'http://localhost/OKAgent/',
    'http://127.0.0.1:8888/OKAgent/', 'http://localhost:8888.evil.test/OKAgent/',
    'http://user@localhost:8888/OKAgent/', 'http://localhost:8888/OKAgent/../evil',
    'http://localhost:8888/OKAgent/%2e%2e/evil', 'http://localhost:8888/OKAgent/other.php',
    "http://localhost:8888/OKAgent/\r\nX-Test: evil", 'http://localhost:8888\\@evil.test/OKAgent/'] as $url) {
    check(!hcimlab_sso_is_safe_agent_return($url, $base), 'Reject unsafe destination');
}
$_SESSION = []; $_GET = ['return_to' => $return];
hcimlab_sso_capture_agent_return($base);
$_GET = []; // Callback uses server-side state, not a callback query destination.
check(hcimlab_sso_consume_agent_return($base) === $return, 'Preserve group and activity');
check(hcimlab_sso_consume_agent_return($base) === $base . '/index.php', 'Consume only once');
$_SESSION['OKAGENT_LOGIN_RETURN'] = $return;
$_GET = ['return_to' => 'https://evil.test/'];
try { hcimlab_sso_capture_agent_return($base); throw new LogicException('Must reject'); }
catch (RuntimeException $error) { check(!isset($_SESSION['OKAGENT_LOGIN_RETURN']), 'Clear stale return'); }
$_SESSION['OKAGENT_LOGIN_RETURN'] = 'https://evil.test/';
check(hcimlab_sso_consume_agent_return($base) === $base . '/index.php', 'Revalidate before redirect');
echo "PASS: return URL validation, group/activity preservation, one-shot redirect\n";
