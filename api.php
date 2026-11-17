<?php
// Sense POS - API đồng bộ dữ liệu (MySQL). Không sửa file này.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') { http_response_code(403); exit('{"error":"forbidden"}'); }
if (!is_file(__DIR__ . '/config.php')) { http_response_code(503); exit('{"error":"not_installed"}'); }
$cfg = require __DIR__ . '/config.php';
try {
  $pdo = new PDO('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['db'] . ';charset=utf8mb4', $cfg['user'], $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Throwable $e) { http_response_code(500); exit('{"error":"db"}'); }

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) { http_response_code(400); exit('{"error":"bad_request"}'); }
$op = $in['op'] ?? '';
$col = preg_replace('/[^a-zA-Z0-9_\-]/', '', $in['col'] ?? '');
$id = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)($in['id'] ?? ''));

function bump($pdo, $col) {
  $pdo->prepare('INSERT INTO pos_vers (col, v) VALUES (?, 1) ON DUPLICATE KEY UPDATE v = v + 1')->execute([$col]);
}
function rowmatch($d, $w) {
  foreach ($w as $c) {
    [$f, $o, $v] = $c;
    $x = array_key_exists($f, $d) ? $d[$f] : null;
    switch ($o) {
      case '==': if ($v === null) { if ($x !== null) return false; } else if ($x !== $v && !(is_numeric($x) && is_numeric($v) && $x == $v && gettype($x) === gettype($v))) return false; break;
      case '!=': if ($x === $v) return false; break;
      case '>=': if ($x === null || !($x >= $v)) return false; break;
      case '>':  if ($x === null || !($x >  $v)) return false; break;
      case '<=': if ($x === null || !($x <= $v)) return false; break;
      case '<':  if ($x === null || !($x <  $v)) return false; break;
      default: return false;
    }
  }
  return true;
}

try {
  if ($op === 'ver') {
    $out = [];
    foreach ($pdo->query('SELECT col, v FROM pos_vers') as $r) $out[$r['col']] = (int)$r['v'];
    echo json_encode($out); exit;
  }
  if ($col === '') { http_response_code(400); exit('{"error":"col"}'); }
  if ($op === 'query') {
    $where = is_array($in['where'] ?? null) ? $in['where'] : [];
    $limit = max(1, min(5000, (int)($in['limit'] ?? 5000)));
    $st = $pdo->prepare('SELECT id, data FROM pos_docs WHERE col = ?'); $st->execute([$col]);
    $rows = [];
    foreach ($st as $r) { $d = json_decode($r['data'], true); if (!is_array($d)) $d = []; if (rowmatch($d, $where)) $rows[] = ['id' => $r['id'], 'data' => $d]; }
    $ob = $in['orderBy'] ?? null;
    if ($ob) usort($rows, function ($a, $b) use ($ob) { return ($a['data'][$ob] ?? null) <=> ($b['data'][$ob] ?? null); });
    echo json_encode(['docs' => array_slice($rows, 0, $limit)], JSON_UNESCAPED_UNICODE); exit;
  }
  if ($op === 'get') {
    $st = $pdo->prepare('SELECT data FROM pos_docs WHERE col = ? AND id = ?'); $st->execute([$col, $id]);
    $r = $st->fetch();
    echo json_encode($r ? ['exists' => true, 'data' => json_decode($r['data'], true)] : ['exists' => false], JSON_UNESCAPED_UNICODE); exit;
  }
  if ($op === 'set') {
    if ($id === '' || !is_array($in['data'] ?? null)) { http_response_code(400); exit('{"error":"bad"}'); }
    $pdo->prepare('REPLACE INTO pos_docs (col, id, data) VALUES (?, ?, ?)')->execute([$col, $id, json_encode($in['data'], JSON_UNESCAPED_UNICODE)]);
    bump($pdo, $col); echo '{"ok":true}'; exit;
  }
  if ($op === 'del') {
    $pdo->prepare('DELETE FROM pos_docs WHERE col = ? AND id = ?')->execute([$col, $id]);
    bump($pdo, $col); echo '{"ok":true}'; exit;
  }
  http_response_code(400); echo '{"error":"op"}';
} catch (Throwable $e) { http_response_code(500); echo '{"error":"server"}'; }
