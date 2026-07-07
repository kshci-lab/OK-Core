<?php
// save_discussion_history.php
// Discussion board posts -> discussion_history table (no reload)
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
// prevent PHP warnings/notices from being printed into JSON responses
ini_set('display_errors', 0);

// simple debug logger to file for investigation
function _dbg($msg){
    $fn = __DIR__ . '/debug_discussion.txt';
    $ts = date('c');
    @file_put_contents($fn, "[".$ts."] ".(is_string($msg)?$msg:print_r($msg,true))."\n", FILE_APPEND);
}
_dbg("save_discussion_history called");
_dbg([ 'REMOTE_ADDR'=>($_SERVER['REMOTE_ADDR']??''), 'REQUEST_METHOD'=>$_SERVER['REQUEST_METHOD'] ?? '' ]);

session_start();
require_once __DIR__ . '/connect_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    _dbg('method not allowed: ' . ($_SERVER['REQUEST_METHOD'] ?? ''));
    echo json_encode(['status'=>'error','message'=>'Method Not Allowed']);
    exit;
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    _dbg('DB connection missing or invalid');
    echo json_encode(['status'=>'error','message'=>'DB接続失敗']);
    exit;
}
@$mysqli->set_charset('utf8mb4');

$user_id = isset($_SESSION['USERID']) ? (int)$_SESSION['USERID'] : 0;
if ($user_id <= 0) {
    http_response_code(401);
    _dbg('user not logged in or invalid user id: ' . var_export($_SESSION, true));
    echo json_encode(['status'=>'error','message'=>'未ログインまたはユーザー不明']);
    exit;
}

$content = isset($_POST['content']) ? trim((string)$_POST['content']) : '';
if ($content === '') {
    _dbg('empty content posted');
    echo json_encode(['status'=>'error','message'=>'content が空です']);
    exit;
}
if (mb_strlen($content,'UTF-8') > 255) {
    // DB列 VARCHAR(255) に収まるように切り詰め
    $content = mb_substr($content, 0, 255, 'UTF-8');
}

// optional: knowledge_fragment_id - which fragment(s) this discussion belongs to
// Accept either a single id, an array of ids, or a comma-separated string. Normalize to a comma-separated string when appropriate.
$knowledge_fragment_id = null;
if (isset($_POST['knowledge_fragment_id']) && $_POST['knowledge_fragment_id'] !== '') {
    if (is_array($_POST['knowledge_fragment_id'])) {
        $arr = array_map('trim', $_POST['knowledge_fragment_id']);
        $arr = array_filter($arr, function($v){ return $v !== ''; });
        if (count($arr)) { $knowledge_fragment_id = implode(',', $arr); }
    } else {
        $kraw = trim((string)$_POST['knowledge_fragment_id']);
        if ($kraw !== '') {
            if (strpos($kraw, ',') !== false) {
                $parts = array_map('trim', explode(',', $kraw));
                $parts = array_filter($parts, function($v){ return $v !== ''; });
                if (count($parts)) { $knowledge_fragment_id = implode(',', $parts); }
            } else {
                $knowledge_fragment_id = $kraw;
            }
        }
    }
}

$fragment_source_type = isset($_POST['fragment_source_type']) ? strtolower(trim((string)$_POST['fragment_source_type'])) : '';
if ($fragment_source_type === 'externalized') { $fragment_source_type = 'discussion'; }
if (!in_array($fragment_source_type, ['experience', 'discussion', 'srl'], true)) {
    $fragment_source_type = ($knowledge_fragment_id !== null && $knowledge_fragment_id !== '') ? 'experience' : '';
}

$table = 'discussion_history';
// テーブル存在チェック
$tbl = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($table)."'");
if (!$tbl) {
    http_response_code(500);
    _dbg('SHOW TABLES failed: ' . $mysqli->error);
    echo json_encode(['status'=>'error','message'=>'テーブル存在確認失敗']);
    exit;
}
if ($tbl->num_rows === 0) {
    // 作成（PRIMARY KEY, posted_time DEFAULT CURRENT_TIMESTAMP）
    $createSql = "CREATE TABLE `discussion_history` (\n".
                 "  discussion_history_id INT(255) NOT NULL,\n".
                 "  user_id INT(255) NOT NULL,\n".
                 "  posted_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n".
                 "  content VARCHAR(255) NOT NULL,\n".
                 "  PRIMARY KEY (discussion_history_id),\n".
                 "  KEY user_id (user_id)\n".
                 ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if(!$mysqli->query($createSql)) {
        http_response_code(500);
        _dbg('create table failed: ' . $mysqli->error . ' SQL: ' . $createSql);
        echo json_encode(['status'=>'error','message'=>'テーブル作成失敗']);
        exit;
    }
}
$tbl->close();

// 次ID採番: MAX >= 55555 なら +1、それ以下なら 55555
$nextId = 55555;
if ($resId = $mysqli->query("SELECT MAX(discussion_history_id) AS max_id FROM `$table`")) {
    $rowId = $resId->fetch_assoc();
    if ($rowId && $rowId['max_id'] !== null) {
        $m = (int)$rowId['max_id'];
        $nextId = ($m >= 55555) ? ($m + 1) : 55555;
    }
    $resId->close();
}

// detect if discussion_history has knowledge_fragment_id column; add if missing
$hasKFragCol = false;
if ($resCol = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'knowledge_fragment_id'")) {
    $hasKFragCol = ($resCol->num_rows > 0);
    $resCol->free();
}

$hasSourceTypeCol = false;
if ($resSourceCol = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'fragment_source_type'")) {
    $hasSourceTypeCol = ($resSourceCol->num_rows > 0);
    $resSourceCol->free();
}
if (!$hasSourceTypeCol) {
    $alterSourceSql = "ALTER TABLE `$table` ADD COLUMN fragment_source_type VARCHAR(32) NULL DEFAULT NULL";
    if ($mysqli->query($alterSourceSql)) {
        $hasSourceTypeCol = true;
        error_log("save_discussion_history: added column fragment_source_type to $table");
    } else {
        error_log("save_discussion_history: failed to add fragment_source_type column: " . $mysqli->error);
    }
}
if (!$hasKFragCol) {
    // try to add column (non-blocking; report to error log on failure)
    // Use VARCHAR to allow storing multiple ids as a comma-separated string
    $alterSql = "ALTER TABLE `$table` ADD COLUMN knowledge_fragment_id VARCHAR(255) NULL DEFAULT NULL";
    if ($mysqli->query($alterSql)) {
        $hasKFragCol = true;
        error_log("save_discussion_history: added column knowledge_fragment_id to $table");
    } else {
        error_log("save_discussion_history: failed to add knowledge_fragment_id column: " . $mysqli->error);
    }
}

// if column exists, detect whether it allows NULL and its type; coerce empty values appropriately
$kfragAllowsNull = true;
$kfragIsVarchar = false;
if ($hasKFragCol) {
    // Use LIKE to reliably fetch the column definition (Type, Null)
    if ($resCol2 = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'knowledge_fragment_id'")) {
        $rowCol2 = $resCol2->fetch_assoc();
        if ($rowCol2 && isset($rowCol2['Null'])) {
            $kfragAllowsNull = (strtoupper(trim($rowCol2['Null'])) === 'YES');
        }
        if ($rowCol2 && isset($rowCol2['Type'])) {
            $kfragIsVarchar = (stripos($rowCol2['Type'], 'varchar') !== false);
        }
        $resCol2->free();
    }
    // If incoming value is CSV and column is not VARCHAR, try to auto-migrate to VARCHAR
    if (!$kfragIsVarchar && is_string($knowledge_fragment_id) && strpos($knowledge_fragment_id, ',') !== false) {
        $alterSql2 = "ALTER TABLE `$table` MODIFY COLUMN knowledge_fragment_id VARCHAR(255) NULL";
        if ($mysqli->query($alterSql2)) {
            $kfragIsVarchar = true;
            error_log("save_discussion_history: modified knowledge_fragment_id to VARCHAR(255) to store CSV ids");
        } else {
            error_log("save_discussion_history: failed to modify knowledge_fragment_id to VARCHAR: " . $mysqli->error);
        }
    }
    if (!$kfragAllowsNull && $knowledge_fragment_id === null) {
        // coerce to appropriate empty value to avoid NULL insert failure in strict mode
        if ($kfragIsVarchar) {
            _dbg('knowledge_fragment_id column NOT NULL (VARCHAR) but no fragment provided; coercing to empty string');
            $knowledge_fragment_id = '';
        } else {
            _dbg('knowledge_fragment_id column NOT NULL (INT) but no fragment provided; coercing to 0');
            $knowledge_fragment_id = 0;
        }
    }
}

// Build INSERT with optional knowledge_fragment_id / fragment_source_type
$sqlIns = "INSERT INTO `$table` (discussion_history_id, user_id, content"
    . ($hasKFragCol ? ", knowledge_fragment_id" : "")
    . ($hasSourceTypeCol ? ", fragment_source_type" : "")
    . ") VALUES (?,?,?"
    . ($hasKFragCol ? ",?" : "")
    . ($hasSourceTypeCol ? ",?" : "")
    . ")"; // posted_time は DEFAULT
if(!$stmt = $mysqli->prepare($sqlIns)) {
    _dbg('prepare failed: ' . $mysqli->error . ' SQL: ' . $sqlIns);
    echo json_encode(['status'=>'error','message'=>'prepare失敗']);
    exit;
}
if($hasKFragCol && $hasSourceTypeCol){
    if (!isset($kfragIsVarchar)) { $kfragIsVarchar = false; }
    if ($kfragIsVarchar) {
        $stmt->bind_param('iisss', $nextId, $user_id, $content, $knowledge_fragment_id, $fragment_source_type);
    } else {
        $kfragInt = ($knowledge_fragment_id === null || $knowledge_fragment_id === '') ? 0 : (int)$knowledge_fragment_id;
        $stmt->bind_param('iisis', $nextId, $user_id, $content, $kfragInt, $fragment_source_type);
    }
} elseif($hasKFragCol){
    if (!isset($kfragIsVarchar)) { $kfragIsVarchar = false; }
    if ($kfragIsVarchar) {
        // bind knowledge_fragment_id as string
        $stmt->bind_param('iiss', $nextId, $user_id, $content, $knowledge_fragment_id);
    } else {
        // bind as int (legacy)
        $kfragInt = ($knowledge_fragment_id === null || $knowledge_fragment_id === '') ? 0 : (int)$knowledge_fragment_id;
        $stmt->bind_param('iisi', $nextId, $user_id, $content, $kfragInt);
    }
} elseif($hasSourceTypeCol) {
    $stmt->bind_param('iiss', $nextId, $user_id, $content, $fragment_source_type);
} else {
    $stmt->bind_param('iis', $nextId, $user_id, $content);
}
if(!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    _dbg('execute failed: ' . $err);
    echo json_encode(['status'=>'error','message'=>'INSERT失敗']);
    exit;
}
$stmt->close();
error_log('save_discussion_history: inserted discussion_history_id=' . $nextId . ' user_id=' . $user_id . ' kfrag=' . var_export($knowledge_fragment_id,true) . ' source=' . var_export($fragment_source_type,true));

// 投稿日時取得（DBの値を正確に返すため SELECT または NOW() を利用）
$posted = null;
if ($resSel = $mysqli->prepare("SELECT posted_time FROM `$table` WHERE discussion_history_id=? LIMIT 1")) {
    $resSel->bind_param('i', $nextId);
    if ($resSel->execute()) {
        $resSel->bind_result($pt);
        if ($resSel->fetch()) { $posted = $pt; }
    }
    $resSel->close();
}
// 投稿者の表示名を取得（users テーブルがあれば照会）
$user_name = '';
if ($resName = $mysqli->prepare("SELECT name FROM `users` WHERE user_id=? LIMIT 1")) {
    $resName->bind_param('i', $user_id);
    if ($resName->execute()) {
        $resName->bind_result($uname);
        if ($resName->fetch()) { $user_name = $uname; }
    }
    $resName->close();
}
if (!$user_name && isset($_SESSION['USERNAME'])) { $user_name = $_SESSION['USERNAME']; }
$mysqli->close();

    _dbg('save_discussion_history succeeded: id=' . $nextId . ' user=' . $user_id . ' kfrag=' . var_export($knowledge_fragment_id,true));

    echo json_encode([
        'status' => 'ok',
        'discussion_history_id' => $nextId,
        'user_id' => $user_id,
        'user_name' => $user_name,
        'content' => $content,
        'posted_time' => $posted
    ]);
