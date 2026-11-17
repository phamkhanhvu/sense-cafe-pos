<?php
// Cài đặt một lần. Sau khi xong HÃY XÓA file này.
header('Content-Type: text/html; charset=utf-8');
$cfgFile = __DIR__ . '/config.php';
function page($body) { echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;max-width:520px;margin:40px auto;padding:0 16px">' . $body; exit; }
if (is_file($cfgFile)) page('<h2>Đã cài đặt</h2><p>Hệ thống đã được cài. <b>Hãy xóa file install.php</b> trong thư mục này rồi mở <a href="./">trang POS</a>.</p>');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $c = ['host' => trim($_POST['host'] ?? 'localhost'), 'db' => trim($_POST['db'] ?? ''), 'user' => trim($_POST['user'] ?? ''), 'pass' => $_POST['pass'] ?? ''];
  try {
    $pdo = new PDO('mysql:host=' . $c['host'] . ';dbname=' . $c['db'] . ';charset=utf8mb4', $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS pos_docs (col VARCHAR(40) NOT NULL, id VARCHAR(80) NOT NULL, data MEDIUMTEXT NOT NULL, PRIMARY KEY (col, id)) DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE IF NOT EXISTS pos_vers (col VARCHAR(40) NOT NULL PRIMARY KEY, v BIGINT NOT NULL DEFAULT 0) DEFAULT CHARSET=utf8mb4');
    $n = (int)$pdo->query('SELECT COUNT(*) FROM pos_docs')->fetchColumn();
    $seeded = 0;
    if ($n === 0 && is_file(__DIR__ . '/seed.json')) {
      $seed = json_decode(file_get_contents(__DIR__ . '/seed.json'), true) ?: [];
      $ins = $pdo->prepare('REPLACE INTO pos_docs (col, id, data) VALUES (?, ?, ?)');
      $vv = $pdo->prepare('INSERT INTO pos_vers (col, v) VALUES (?, 1) ON DUPLICATE KEY UPDATE v = v + 1');
      foreach ($seed as $col => $docs) { foreach ($docs as $id => $d) { $ins->execute([$col, $id, json_encode($d, JSON_UNESCAPED_UNICODE)]); $seeded++; } $vv->execute([$col]); }
    }
    if (file_put_contents($cfgFile, "<?php\nreturn " . var_export($c, true) . ";\n") === false) page('<h2>Lỗi</h2><p>Không ghi được config.php. Kiểm tra quyền ghi của thư mục.</p>');
    @chmod($cfgFile, 0600);
    page('<h2>Cài đặt xong</h2><p>Đã tạo bảng dữ liệu và nạp ' . $seeded . ' mục (menu, bàn, cài đặt quán).</p><p><b>Việc cuối: xóa file install.php</b> rồi mở <a href="./">trang POS</a>.</p>');
  } catch (Throwable $e) { page('<h2>Không kết nối được</h2><p>Kiểm tra lại tên database, user và mật khẩu.</p><p><a href="">Thử lại</a></p>'); }
}
page('<h2>Cài đặt Sense POS</h2><p>Nhập thông tin cơ sở dữ liệu MySQL (tạo trong Plesk › Databases).</p><form method="post">
<p>Máy chủ<br><input name="host" value="localhost" style="width:100%;padding:8px"></p>
<p>Tên database<br><input name="db" required style="width:100%;padding:8px"></p>
<p>Tên người dùng<br><input name="user" required style="width:100%;padding:8px"></p>
<p>Mật khẩu<br><input name="pass" type="password" required style="width:100%;padding:8px"></p>
<button style="padding:10px 20px">Cài đặt</button></form>');
