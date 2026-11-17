<?php
// Sense POS - nhận thông báo biến động số dư từ SePay (webhook). Không sửa file này.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_file(__DIR__ . '/config.php')) { http_response_code(503); exit('{"success":false}'); }
$cfg = require __DIR__ . '/config.php';
try {
  $pdo = new PDO('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['db'] . ';charset=utf8mb4', $cfg['user'], $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $st = $pdo->prepare('SELECT data FROM pos_docs WHERE col = ? AND id = ?'); $st->execute(['config', 'sepay']);
  $r = $st->fetch(); $conf = $r ? json_decode($r['data'], true) : [];
  $key = is_array($conf) ? (string)($conf['key'] ?? '') : '';
  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
  if ($auth === '' && function_exists('getallheaders')) { foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') $auth = $v; }
  if ($key === '' || !hash_equals('Apikey ' . $key, trim($auth))) { http_response_code(401); exit('{"success":false,"error":"unauthorized"}'); }
  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in) || !isset($in['id'])) { http_response_code(400); exit('{"success":false}'); }
  if (($in['transferType'] ?? '') !== 'in') { echo '{"success":true,"ignored":true}'; exit; }
  $id = preg_replace('/[^0-9A-Za-z_\-]/', '', (string)$in['id']);
  $doc = [
    'sid' => $id, 'amount' => (int)round((float)($in['transferAmount'] ?? 0)), 'content' => (string)($in['content'] ?? ''),
    'code' => (string)($in['code'] ?? ''), 'gateway' => (string)($in['gateway'] ?? ''), 'acc' => (string)($in['accountNumber'] ?? ''),
    'ref' => (string)($in['referenceCode'] ?? ''), 'bankTime' => (string)($in['transactionDate'] ?? ''), 'at' => (int)(microtime(true) * 1000), 'billId' => null,
  ];
  $st = $pdo->prepare('SELECT 1 FROM pos_docs WHERE col = ? AND id = ?'); $st->execute(['bank_tx', 'sp' . $id]);
  if (!$st->fetch()) {
    $pdo->prepare('INSERT INTO pos_docs (col, id, data) VALUES (?, ?, ?)')->execute(['bank_tx', 'sp' . $id, json_encode($doc, JSON_UNESCAPED_UNICODE)]);
    $pdo->prepare('INSERT INTO pos_vers (col, v) VALUES (?, 1) ON DUPLICATE KEY UPDATE v = v + 1')->execute(['bank_tx']);
  }
  echo '{"success":true}';
} catch (Throwable $e) { http_response_code(500); exit('{"success":false}'); }
