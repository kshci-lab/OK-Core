// Isolated HTTP contract checks. No deployed configuration or real DB is used.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const php = process.env.PHP_BINARY || 'C:/MAMP/bin/php/php8.2.14/php.exe';
const root = fs.mkdtempSync(path.join(os.tmpdir(), 'ok-core-phase1-'));
const api = path.join(root, 'api/v1');
fs.mkdirSync(api, { recursive: true });
fs.mkdirSync(path.join(root, 'php'));
for (const file of ['service.php', 'contract.php']) {
  fs.copyFileSync(path.join(__dirname, '../api/v1', file), path.join(api, file));
}
fs.writeFileSync(path.join(api, 'config.local.php'), `<?php return ['source_systems'=>[
 'forest-platform'=>['token'=>'http-test-forest','share_policy'=>'replace'],
 'readonly'=>['token'=>'http-test-readonly','scopes'=>['groups:read']],
 'disabled'=>['enabled'=>false,'token'=>'http-test-disabled']]];`);
fs.writeFileSync(path.join(root, 'php/connect_db.php'), `<?php
echo 'bootstrap noise that must not reach JSON';
if (isset($_SERVER['HTTP_X_TEST_DB_FAILURE'])) throw new RuntimeException('test-private-db-detail');
$mysqli = null;`);
const router = path.join(root, 'router.php');
fs.writeFileSync(router, `<?php require __DIR__.'/api/v1/service.php'; ok_core_api_dispatch();`);
let server;
let checks = 0;

(async () => {
  const socket = net.createServer();
  await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  server = spawn(php, ['-n', '-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, router], { stdio: ['ignore', 'ignore', 'pipe'], windowsHide: true });
  let serverLog = '';
  server.stderr.on('data', data => { serverLog = (serverLog + data).slice(-2000); });
  const base = `http://127.0.0.1:${port}/OK-Core/api/v1`;
  const request = async (route, options = {}) => {
    const response = await fetch(base + route, { ...options, signal: AbortSignal.timeout(5000) });
    const text = await response.text();
    const body = JSON.parse(text);
    assert.equal(response.headers.get('content-type'), 'application/json; charset=UTF-8');
    assert.equal(response.headers.get('cache-control'), 'no-store');
    assert.equal(response.headers.get('x-request-id'), body.meta.request_id);
    assert.match(body.meta.request_id, /^[A-Za-z0-9._:-]{1,80}$/);
    assert(!text.includes('http-test-forest') && !text.includes('test-private-db-detail') && !text.includes('bootstrap noise'));
    checks++;
    return { response, body };
  };
  let ready = false, lastError;
  for (let i = 0; i < 40; i++) {
    try { await request('/capabilities'); ready = true; break; }
    catch (error) { lastError = error; await new Promise(resolve => setTimeout(resolve, 50)); }
  }
  assert(ready, 'PHP test server failed to start: ' + lastError + '\n' + serverLog);
  const caps = await request('/capabilities', { headers: { 'X-Request-Id': 'caller:phase1-01' } });
  assert.equal(caps.response.status, 200);
  assert.equal(caps.body.meta.request_id, 'caller:phase1-01');
  assert.equal(caps.body.data.max_payload_bytes, 1048576);
  assert.equal(caps.body.data.storage.normalized_stages_and_slots, false);
  assert.equal(caps.body.data.context.generic_live_context_proxy, true);
  const replaced = await request('/capabilities', { headers: { 'X-Request-Id': 'bad request id' } });
  assert.notEqual(replaced.body.meta.request_id, 'bad request id');
  const url = '/source-systems/forest-platform/knowledge-fragments/knowledge_fragment_7';
  const token = { Authorization: 'Bearer http-test-forest', 'X-Acting-User-Sub': 'example-user' };
  for (const [route, options, status, code] of [
    ['/unknown', {}, 404, 'NOT_FOUND'],
    ['/capabilities', { method: 'POST' }, 405, 'METHOD_NOT_ALLOWED'],
    [url, { method: 'GET' }, 405, 'METHOD_NOT_ALLOWED'],
    ['/users/me/knowledge-groups', {}, 401, 'INVALID_TOKEN'],
    ['/users/me/knowledge-groups', { headers: { Authorization: 'Bearer http-test-disabled' } }, 401, 'INVALID_TOKEN'],
    [url, { method: 'PUT', headers: { ...token, Authorization: 'Bearer http-test-readonly' } }, 403, 'SOURCE_SYSTEM_FORBIDDEN'],
    ['/source-systems/readonly/knowledge-fragments/7', { method: 'DELETE', headers: { ...token, Authorization: 'Bearer http-test-readonly' } }, 403, 'SCOPE_FORBIDDEN'],
    ['/users/me/knowledge-groups', { headers: { Authorization: 'Bearer http-test-forest' } }, 401, 'ACTING_USER_REQUIRED'],
    ['/users/me/knowledge-groups', { headers: token }, 503, 'DATABASE_UNAVAILABLE'],
    ['/users/me/knowledge-groups', { headers: { ...token, 'X-Test-Db-Failure': '1' } }, 500, 'INTERNAL_ERROR'],
    ['/health', {}, 503, 'DATABASE_UNAVAILABLE'],
    ['/index.php/capabilities', {}, 200, null],
  ]) {
    const result = await request(route, options);
    assert.equal(result.response.status, status, route);
    if (code) assert.equal(result.body.error.code, code, route);
    if (status === 405) assert(result.response.headers.get('allow'));
    if (status === 401) assert.equal(result.response.headers.get('www-authenticate'), 'Bearer');
  }
  console.log(`PASS: ${checks} isolated HTTP responses: capabilities, auth/scopes, routing, request IDs and common errors.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  if (server && server.exitCode === null) {
    const exited = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await exited;
  }
  // Verify the resolved target before recursive cleanup on Windows.
  const actual = fs.realpathSync(root);
  const parent = fs.realpathSync(os.tmpdir());
  assert(path.dirname(actual).toLowerCase() === parent.toLowerCase() && path.basename(actual).startsWith('ok-core-phase1-'));
  fs.rmSync(actual, { recursive: true, force: true });
});
