<?php
declare(strict_types=1);
namespace OrgHistoryTest;

// Isolated tests: no application connection/config, no persistent DB, no real sessions.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

final class mysqli
{
    public \PDO $pdo;
    public int $insert_id = 0;
    public function __construct() {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $this->pdo->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
        $this->pdo->sqliteCreateFunction('IF', static fn($condition, $yes, $no) => $condition ? $yes : $no);
    }
    public function prepare(string $sql): mysqli_stmt { return new mysqli_stmt($this, $sql); }
    public function query(string $sql): Result { $stmt = $this->prepare($sql); $stmt->execute(); return $stmt->get_result(); }
    public function begin_transaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollback(): bool { return !$this->pdo->inTransaction() || $this->pdo->rollBack(); }
    public function set_charset(string $name): bool { return true; }
}
final class mysqli_stmt
{
    private array $params = [], $rows = [], $outputs = [];
    private int $cursor = 0;
    public int $affected_rows = 0;
    public function __construct(private mysqli $db, private string $sql) {}
    public function bind_param(string $types, &...$params): bool { $this->params = &$params; return true; }
    public function bind_result(&...$outputs): bool { $this->outputs = &$outputs; return true; }
    public function fetch(): ?bool {
        $row = $this->rows[$this->cursor++] ?? null;
        if ($row === null) return null;
        foreach (array_values($row) as $index => $value) $this->outputs[$index] = $value;
        return true;
    }
    public function execute(): bool {
        if (str_contains($this->sql, 'information_schema.TABLES')) {
            $stmt = $this->db->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $stmt->execute([$this->params[0]]);
            $this->rows = $stmt->fetchColumn() ? [['TABLE_TYPE'=>'BASE TABLE','ENGINE'=>'InnoDB']] : [];
        } elseif (str_contains($this->sql, 'information_schema.COLUMNS')) {
            $fields = $this->db->pdo->query('PRAGMA table_info(organizational_knowledge_histories)')->fetchAll(\PDO::FETCH_ASSOC);
            $this->rows = [];
            foreach ($fields as $field) if (in_array($field['name'], ['created_at','updated_at'], true)) $this->rows[] = ['COLUMN_NAME'=>$field['name']];
        } else {
            $stmt = $this->db->pdo->prepare(preg_replace('/\s+FOR UPDATE\b/i', '', $this->sql));
            $stmt->execute(array_values($this->params));
            $this->affected_rows = $stmt->rowCount();
            $this->db->insert_id = (int)$this->db->pdo->lastInsertId();
            $this->rows = $stmt->columnCount() ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        }
        return true;
    }
    public function get_result(): Result { return new Result($this->rows); }
    public function close(): void {}
}
final class Result
{
    private int $cursor = 0;
    public function __construct(private array $rows) {}
    public function fetch_assoc(): ?array { return $this->rows[$this->cursor++] ?? null; }
    public function fetch_all(int $mode = 1): array { return $this->rows; }
    public function free(): void {}
}
function hcimlab_start_session(): void {}
function check(bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); }
function load_source(string $name): void {
    global $mysqli, $historyUser, $historyAccess, $historyGroup, $historyAction;
    $path = __DIR__ . '/../php/' . $name;
    if (!is_file($path)) $path = (getenv('OK_CORE_PROJECT_ROOT') ?: dirname(__DIR__)) . '/php/' . $name;
    $text = file_get_contents($path);
    $text = preg_replace('/^<\?php\s*/', '', $text);
    $text = preg_replace('/declare\(strict_types=1\);/', '', $text);
    $text = preg_replace('/^\s*require(?:_once)?\b[^;]*;/m', '', $text);
    eval('namespace OrgHistoryTest; use RuntimeException; use InvalidArgumentException; use DomainException; use Throwable; use JsonException; use DateTimeImmutable; use DateTimeZone;' . $text);
}
function fixture(): mysqli {
    $db = new mysqli();
    $db->pdo->exec(<<<'SQL'
CREATE TABLE knowledge_explorer (knowledge_node_id INTEGER PRIMARY KEY AUTOINCREMENT, parent_node_id INTEGER, node_title TEXT NOT NULL,
 knowledge_fragment_id TEXT, comment TEXT, tacto_when TEXT, tacto_what TEXT, tacto_why TEXT, organizational_basis TEXT,
 updated_by INTEGER, deleted INTEGER DEFAULT 0, sort_order INTEGER, knowledge_group_id INTEGER,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, future_column TEXT);
CREATE TABLE knowledge_explorer_fragment_links (id INTEGER PRIMARY KEY AUTOINCREMENT,knowledge_node_id INTEGER,fragment_source_type TEXT,fragment_source_id INTEGER,display_order INTEGER);
CREATE TABLE organizational_knowledges (organizational_knowledge_id INTEGER PRIMARY KEY,category_id INTEGER,summary TEXT,created_by_user_id INTEGER,deleted_at TEXT);
CREATE TABLE organizational_knowledge_categories (category_id INTEGER PRIMARY KEY,knowledge_group_id INTEGER,category_name TEXT);
CREATE TABLE organizational_knowledge_fragment_links (fragment_link_id INTEGER PRIMARY KEY,organizational_knowledge_id INTEGER,knowledge_fragment_id INTEGER,display_order INTEGER);
CREATE TABLE organizational_knowledge_discussion_posts (discussion_post_id INTEGER PRIMARY KEY,organizational_knowledge_id INTEGER,content TEXT,user_id INTEGER);
CREATE TABLE organizational_knowledge_histories (history_id INTEGER PRIMARY KEY AUTOINCREMENT, source_table TEXT,organizational_knowledge_id INTEGER,knowledge_group_id INTEGER,event_type TEXT,operation_id TEXT,snapshot_version INTEGER,snapshot_json TEXT CHECK(json_valid(snapshot_json)),snapshot_sha256 TEXT,saved_by_user_id INTEGER,created_at TEXT,updated_at TEXT,saved_at TEXT);
CREATE TABLE knowledge_groups (group_id INTEGER,deleted INTEGER);
CREATE TABLE kgroup_user_link (group_id INTEGER,user_id INTEGER,deleted INTEGER,created_at TEXT);
CREATE TABLE shared_nodes (knowledge_fragment_id INTEGER,fragment_source_type TEXT,knowledge_group_id INTEGER,deleted INTEGER);
CREATE TABLE experience_knowledges (experience_knowledge_id INTEGER,deleted INTEGER);
CREATE TABLE externalized_contents (externalized_contents_id INTEGER,deleted INTEGER);
INSERT INTO knowledge_groups VALUES(1,0),(2,0);
INSERT INTO kgroup_user_link VALUES(1,1,0,'2026-11-06');
INSERT INTO knowledge_explorer (knowledge_node_id,node_title,knowledge_fragment_id,knowledge_group_id,sort_order,future_column) VALUES(1,'変更前','7',1,1,'未知のカラムも保存'),(2,'隣のルート',NULL,1,2,NULL),(3,'別グループ',NULL,2,1,NULL);
UPDATE knowledge_explorer SET created_at='2024-01-01 01:02:03', updated_at='2025-02-03 04:05:06';
INSERT INTO knowledge_explorer_fragment_links VALUES(1,1,'experience',7,1),(2,1,'discussion',7,2);
INSERT INTO shared_nodes VALUES(7,'experience',1,0),(7,'discussion',1,0);
INSERT INTO experience_knowledges VALUES(7,0);
INSERT INTO externalized_contents VALUES(7,0);
INSERT INTO organizational_knowledge_categories VALUES(1,1,'カテゴリ');
INSERT INTO organizational_knowledges VALUES(1,1,'正規化した組織知',1,NULL);
INSERT INTO organizational_knowledge_fragment_links VALUES(1,1,7,1);
INSERT INTO organizational_knowledge_discussion_posts VALUES(1,1,'議論の内容',1);
SQL);
    return $db;
}
function histories(mysqli $db): array { return $db->query('SELECT * FROM organizational_knowledge_histories ORDER BY history_id')->fetch_all(); }
function reject_after_snapshot(mysqli $db, string $event): void {
    $db->pdo->exec("CREATE TRIGGER reject_history BEFORE INSERT ON organizational_knowledge_histories WHEN NEW.event_type='$event' BEGIN SELECT RAISE(ABORT, 'fixture_history_failure'); END");
}
load_source('organizational_knowledge_history_store.php');
load_source('organizational_history_node_actions.php');
$mysqli = fixture();
$case = $argv[1] ?? 'store';
if ($case === 'store') {
    $before = $mysqli->query('SELECT * FROM organizational_knowledges')->fetch_all();
    $mysqli->begin_transaction();
    foreach (['knowledge_explorer','organizational_knowledges'] as $source) ok_core_save_organizational_knowledge_history($mysqli,$source,'1',1,1,'manual',str_repeat('a',32));
    $mysqli->commit();
    $rows = histories($mysqli);
    check(count($rows) === 2 && $rows[0]['source_table'] !== $rows[1]['source_table'], 'Same numeric ID must preserve source identity');
    foreach ($rows as $row) {
        check(hash('sha256',$row['snapshot_json']) === $row['snapshot_sha256'], 'Snapshot hash must match stored bytes');
        check((int)$row['saved_by_user_id'] === 1, 'Actor required');
        $knowledge = json_decode($row['snapshot_json'], true)['knowledge'];
        check($row['created_at'] === ($knowledge['created_at'] ?? null) && $row['updated_at'] === ($knowledge['updated_at'] ?? null), 'Source dates must be preserved separately from backup time');
    }
    try { ok_core_save_organizational_knowledge_history($mysqli,'knowledge_explorer','1',1,1,'before_update',str_repeat('b',32)); throw new \RuntimeException('Expected disabled event denial'); } catch (\InvalidArgumentException $error) {}
    check(count(histories($mysqli)) === 2, 'before_update must not append a snapshot');
    $legacy = json_decode($rows[0]['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
    check($legacy['knowledge']['future_column'] === '未知のカラムも保存' && count($legacy['fragment_links']) === 2, 'Full row and typed links must survive');
    $normal = json_decode($rows[1]['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
    check($normal['category']['category_name'] === 'カテゴリ' && count($normal['discussion_posts']) === 1, 'Category and discussions must be captured');
    check($before === $mysqli->query('SELECT * FROM organizational_knowledges')->fetch_all(), 'Canonical original must not change');
    $mysqli->pdo->exec("UPDATE knowledge_explorer SET node_title='後から変更' WHERE knowledge_node_id=1");
    check(histories($mysqli)[0]['snapshot_json'] === $rows[0]['snapshot_json'], 'Backup must remain immutable after later edits');
    try { ok_core_history_node_action($mysqli,'rename',1,2,1,'越境'); throw new \RuntimeException('Expected denial'); } catch (\DomainException $error) {}
    try { ok_core_organizational_history_snapshot($mysqli,'organizational_knowledges','1',2); throw new \RuntimeException('Expected category denial'); } catch (\DomainException $error) {}
    try { ok_core_history_source('knowledge_explorer; DROP TABLE users'); throw new \RuntimeException('Expected source denial'); } catch (\InvalidArgumentException $error) {}
    check(count(histories($mysqli)) === 2, 'Wrong group cannot append histories');
    reject_after_snapshot($mysqli, 'renamed');
    try { ok_core_history_node_action($mysqli,'rename',1,1,1,'失敗させる'); throw new \RuntimeException('Expected storage failure'); } catch (\PDOException $error) {}
    check($mysqli->query('SELECT node_title FROM knowledge_explorer WHERE knowledge_node_id=1')->fetch_assoc()['node_title'] === '後から変更' && count(histories($mysqli)) === 2, 'History failure must roll back source and before-snapshot');
    $mysqli->pdo->exec('DELETE FROM organizational_knowledges WHERE organizational_knowledge_id=1');
    check(count(histories($mysqli)) === 2, 'Removing original must not remove backup');
    echo "PASS store: full snapshot, typed/source identity, hash, immutability, canonical relations, authorization, rollback and retention\n";
    exit;
}

// Execute real endpoints with isolated fixtures; verify their JSON and committed state on exit.
$_SESSION = ['USERID'=>'1'];
$_SERVER['REQUEST_METHOD'] = $case === 'method' ? 'GET' : 'POST';
$_POST = ['group_id'=>'1','node_id'=>'1','node_title'=>'更新後','new_title'=>'名称変更','fragment_refs'=>'[]','dir'=>'down'];
if ($case === 'create') { $_POST['fragment_refs'] = '["experience:7","discussion:7"]'; }
if ($case === 'unauthenticated') $_SESSION = [];
if ($case === 'forbidden') $_POST['group_id'] = '2';
if ($case === 'missing') $mysqli->pdo->exec('DROP TABLE organizational_knowledge_histories');
if ($case === 'missing_dates') $mysqli->pdo->exec('DROP TABLE organizational_knowledge_histories; CREATE TABLE organizational_knowledge_histories (history_id INTEGER PRIMARY KEY, saved_at TEXT)');
if ($case === 'rollback') reject_after_snapshot($mysqli, 'updated');
if ($case === 'initialize') $mysqli->pdo->exec('UPDATE knowledge_explorer SET sort_order=NULL WHERE knowledge_group_id=1');
if ($case === 'manual') $_POST['organizational_knowledge_id'] = '1';
$expectedCode = match ($case) { 'method'=>405,'unauthenticated'=>401,'forbidden'=>403,'missing','missing_dates'=>503,'rollback'=>500,default=>200 };
ob_start();
register_shutdown_function(static function () use ($case,$expectedCode,$mysqli): void {
    $output = ob_get_clean();
    try {
        $reply = json_decode($output,true,512,JSON_THROW_ON_ERROR);
        check(http_response_code() === $expectedCode, "HTTP status for $case: " . $output);
        if ($case === 'missing' || $case === 'missing_dates') { check($mysqli->query('SELECT node_title FROM knowledge_explorer WHERE knowledge_node_id=1')->fetch_assoc()['node_title']==='変更前','Missing schema must not change original'); }
        else {
            $rows = histories($mysqli);
            $expected = match ($case) {'create','manual','update'=>1,'rename','delete'=>2,'reorder','initialize'=>4,default=>0};
            check(count($rows) === $expected, "History count for $case");
            if ($case === 'create') { $snapshot=json_decode($rows[0]['snapshot_json'],true); check(count($snapshot['fragment_links'])===2 && $rows[0]['event_type']==='created','Creation must save typed links'); }
            if ($case === 'update') {
                $new=json_decode($rows[0]['snapshot_json'],true);
                check($rows[0]['event_type'] === 'updated' && $new['knowledge']['node_title']==='更新後' && $new['fragment_links']===[], 'Update must save only the completed version and its current links');
                check($rows[0]['created_at'] === '2024-01-01 01:02:03' && $rows[0]['updated_at'] === $new['knowledge']['updated_at'], 'Creation time must survive updates; update time must come from the saved version');
            }
            if ($case === 'manual') {
                check($rows[0]['source_table'] === 'knowledge_explorer' && $rows[0]['event_type'] === 'manual', 'Manual save defaults to current knowledge_explorer table');
                check(json_decode($rows[0]['snapshot_json'], true)['knowledge']['node_title'] === '変更前', 'Manual backup must not alter current knowledge');
            }
            if ($case === 'delete') check((int)json_decode($rows[1]['snapshot_json'],true)['knowledge']['deleted']===1,'Deleted snapshot must preserve flag');
            if ($case === 'reorder' || $case === 'initialize') check((int)$mysqli->query('SELECT sort_order FROM knowledge_explorer WHERE knowledge_node_id=1')->fetch_assoc()['sort_order']===2,'Root order must change');
            if ($case === 'rollback') check($mysqli->query('SELECT node_title FROM knowledge_explorer WHERE knowledge_node_id=1')->fetch_assoc()['node_title']==='変更前' && count($mysqli->query('SELECT * FROM knowledge_explorer_fragment_links WHERE knowledge_node_id=1')->fetch_all())===2,'Rollback restores title and links');
        }
        echo "PASS endpoint: $case\n";
    } catch (\Throwable $error) { fwrite(STDERR,"FAIL $case: " . $error->getMessage() . "\n"); exit(1); }
});
load_source('knowledge_group_access.php');
load_source('fragment_references.php');
if ($case === 'create') load_source('knowledge_node_create.php');
elseif (in_array($case,['update','rollback','missing_dates'],true)) load_source('update_knowledge_node.php');
else {
    load_source('organizational_history_endpoint_bootstrap.php');
    if ($case === 'manual') load_source('save_organizational_knowledge_history.php');
    else {
        $historyAction = match ($case) {'delete'=>'delete','reorder','initialize'=>'reorder',default=>'rename'};
        load_source('organizational_history_node_action_endpoint.php');
    }
}
