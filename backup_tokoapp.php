<?php
/**
 * backup_tokoapp.php
 * UI sederhana + aksi backup DB "tokoapp" (struktur + data) via PDO (tanpa mysqldump).
 *
 * Mode:
 *  - ?mode=list            -> Tampilkan daftar file backup di /backups
 *  - ?mode=download        -> Download .sql
 *  - ?mode=download_gz     -> Download .sql.gz (butuh zlib)
 *  - ?mode=save            -> Simpan .sql ke /backups + tampil ringkasan
 *  - ?mode=save_gz         -> Simpan .sql.gz ke /backups + tampil ringkasan
 *  - (tanpa mode)          -> Tampilkan halaman menu (tidak langsung simpan/download)
 */

require_once __DIR__ . '/config.php';
require_access('BACKUP');

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  die('PDO tidak tersedia dari config.php');
}

$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!$dbName) { die('Tidak terhubung ke database.'); }

$mode   = $_GET['mode'] ?? ''; // kontrol utama
$step   = 1000; // batch insert

function page_header($title='Backup DB') {
  global $pdo;
  try {
    $set = $pdo->query("SELECT theme FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
  } catch (PDOException $e) {
    $set = ['theme' => 'dark'];
  }
  $theme = $set['theme'] ?? 'dark';

  echo "<!doctype html><html lang='id' data-theme='{$theme}'><head><meta charset='utf-8'><title>{$title}</title>
  <style>
    :root {
      --bg-page: #0f172a;
      --card-bg: #111827;
      --card-bd: #334155;
      --text-main: #e2e8f0;
      --text-muted: #94a3b8;
      --btn-bg: #0b1220;
      --row-bd: #1f2937;
    }
    [data-theme='light'] {
      --bg-page: #f1f5f9;
      --card-bg: #ffffff;
      --card-bd: #cbd5e1;
      --text-main: #0f172a;
      --text-muted: #475569;
      --btn-bg: #ffffff;
      --row-bd: #e2e8f0;
    }
    body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:var(--bg-page);color:var(--text-main);margin:0;padding:1rem}
    h1,h2,h3{margin:.2rem 0 .8rem}
    .wrap{max-width:900px;margin:0 auto}
    .card{border:1px solid var(--card-bd);background:var(--card-bg);border-radius:.6rem;padding:1rem;margin-bottom:1rem;box-shadow: 0 1px 3px rgba(0,0,0,0.1);}
    .row{display:flex;gap:.6rem;flex-wrap:wrap}
    .btn{border:1px solid var(--card-bd);background:var(--btn-bg);color:var(--text-main);padding:.5rem .8rem;border-radius:.4rem;text-decoration:none;font-weight:500}
    .btn:hover{filter: brightness(0.9);}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{border:1px solid var(--row-bd);padding:.5rem .6rem}
    a{color:#0284c7;text-decoration:none}
    [data-theme='dark'] a{color:#93c5fd}
    a:hover{text-decoration:underline}
    .right{text-align:right}
    .muted{color:var(--text-muted)}
  </style></head><body><div class='wrap'>";
}

function page_footer() {
  echo "</div></body></html>";
}

function list_backups(): array {
  $dir = __DIR__ . '/backups';
  $out = [];
  if (is_dir($dir)) {
    foreach (scandir($dir) as $f) {
      if ($f==='.' || $f==='..') continue;
      $p = $dir . '/' . $f;
      if (is_file($p)) {
        $out[] = [
          'name'=>$f,
          'size'=>filesize($p),
          'mtime'=>filemtime($p),
          'href'=>'backups/' . rawurlencode($f),
        ];
      }
    }
    usort($out, fn($a,$b)=>$b['mtime'] <=> $a['mtime']);
  }
  return $out;
}

function human_size($bytes): string {
  if ($bytes >= 1048576) return round($bytes/1048576,2).' MB';
  if ($bytes >= 1024)    return round($bytes/1024,2).' KB';
  return $bytes.' B';
}

// ===== Halaman Menu (default) =====
if ($mode === '') {
  page_header('Backup Database');
  $files = list_backups();
  echo "<h2>Backup Database</h2>
  <div class='card'>
    <div class='row' style='margin-bottom:.6rem'>
      <a class='btn' href='?mode=download'>⬇️ Download .sql</a>
      <a class='btn' href='?mode=download_gz'>⬇️ Download .sql.gz</a>
      <a class='btn' href='?mode=save'>💾 Simpan ke Server (.sql)</a>
      <a class='btn' href='?mode=save_gz'>💾 Simpan ke Server (.sql.gz)</a>
      <a class='btn' href='?mode=list'>📁 Lihat Semua Backup</a>
      <a class='btn' href='index.php'>🏠 Kembali</a>
    </div>
    <div class='muted'>Tip: gunakan Simpan ke Server jika ingin ada riwayat, bukan langsung download.</div>
  </div>";

  echo "<h3>Backup Terbaru</h3>
  <div class='card'>";
  if (!$files) {
    echo "Belum ada backup.";
  } else {
    echo "<table><thead><tr><th>Nama File</th><th class='right'>Ukuran</th><th>Tanggal/Jam</th><th>Unduh</th></tr></thead><tbody>";
    $top = array_slice($files, 0, 10);
    foreach ($top as $f) {
      echo "<tr>
        <td>".htmlspecialchars($f['name'])."</td>
        <td class='right'>".human_size($f['size'])."</td>
        <td>".date('d-m-Y H:i:s', $f['mtime'])."</td>
        <td><a href='".htmlspecialchars($f['href'])."' download>Unduh</a></td>
      </tr>";
    }
    echo "</tbody></table>";
  }
  echo "</div>";
  page_footer();
  exit;
}

// ===== Mode List =====
if ($mode === 'list') {
  page_header('Daftar Backup');
  echo "<h2>Daftar Backup</h2>
  <div class='row' style='margin-bottom:.6rem'>
    <a class='btn' href='?mode=save'>➕ Buat Backup (.sql)</a>
    <a class='btn' href='?mode=save_gz'>➕ Buat Backup (.sql.gz)</a>
    <a class='btn' href='?'>⬅️ Kembali ke Menu</a>
  </div>";
  $files = list_backups();
  echo "<div class='card'><table>
    <thead><tr><th>Nama File</th><th class='right'>Ukuran</th><th>Tanggal/Jam</th><th>Unduh</th></tr></thead><tbody>";
  if (!$files) echo "<tr><td colspan='4'>Belum ada backup.</td></tr>";
  else foreach ($files as $f) {
    echo "<tr>
      <td>".htmlspecialchars($f['name'])."</td>
      <td class='right'>".human_size($f['size'])."</td>
      <td>".date('d-m-Y H:i:s', $f['mtime'])."</td>
      <td><a href='".htmlspecialchars($f['href'])."' download>Unduh</a></td>
    </tr>";
  }
  echo "</tbody></table></div>";
  page_footer();
  exit;
}

// ===== Mulai proses backup (download/save) =====
$wantGzip = in_array($mode, ['download_gz','save_gz'], true);
if ($wantGzip && !extension_loaded('zlib')) {
  die('zlib tidak tersedia: tidak bisa membuat .gz');
}

$ts       = date('Ymd_His');
$baseName = "backup_{$dbName}_{$ts}.sql";
$fileName = $wantGzip ? ($baseName.'.gz') : $baseName;

$tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = [];
while ($r = $tablesStmt->fetch(PDO::FETCH_NUM)) { $tables[] = $r[0]; }
if (!$tables) { die('Tidak ada tabel untuk dibackup.'); }

$dumpHeader =
"-- ------------------------------------------------------\n" .
"--  Backup Database : {$dbName}\n" .
"--  Tanggal         : " . date('Y-m-d H:i:s') . "\n" .
"--  Host            : " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n" .
"--  PHP             : " . PHP_VERSION . "\n" .
"-- ------------------------------------------------------\n\n" .
"SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n" .
"SET AUTOCOMMIT = 0;\n" .
"START TRANSACTION;\n" .
"SET time_zone = \"+00:00\";\n\n" .
"/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n" .
"/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n" .
"/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n" .
"/*!40101 SET NAMES utf8mb4 */;\n\n";

$dumpFooter =
"\nCOMMIT;\n" .
"/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n" .
"/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n" .
"/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";

$saveToServer = in_array($mode, ['save','save_gz'], true);
$out = null;
$serverPath = null;

if ($saveToServer) {
  $dir = __DIR__ . '/backups';
  if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
  $serverPath = $dir . '/' . $fileName;
  $out = $wantGzip ? gzopen($serverPath, 'wb9') : fopen($serverPath, 'wb');
  if (!$out) { die('Gagal membuat file di server.'); }
} else {
  // download streaming
  if (ob_get_level()) { ob_end_clean(); }
  header('Pragma: public');
  header('Expires: 0');
  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
  header('Cache-Control: private', false);
  header('Content-Transfer-Encoding: binary');
  header('Content-Type: '.($wantGzip?'application/gzip':'application/sql'));
  header('Content-Disposition: attachment; filename="'.$fileName.'"');
  $out = fopen('php://output', 'wb');
}

$write = function($str) use ($saveToServer, $wantGzip, $out) {
  if ($saveToServer) {
    if ($wantGzip) gzwrite($out, $str); else fwrite($out, $str);
  } else {
    // download: tulis langsung (tanpa gzencode ulang)
    fwrite($out, $str);
  }
};

// tulis header
$write($dumpHeader);

// isi dump
foreach ($tables as $table) {
  $write("--\n-- Struktur tabel `{$table}`\n--\n\n");
  $write("DROP TABLE IF EXISTS `{$table}`;\n");
  $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
  $ddl = preg_replace('/\r\n?/', "\n", $create['Create Table'] ?? '');
  $write($ddl . ";\n\n");

  $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
  if ($count === 0) { continue; }

  $write("--\n-- Dumping data untuk tabel `{$table}` (total baris: {$count})\n--\n");

  $colsStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
  $cols = [];
  while ($c = $colsStmt->fetch(PDO::FETCH_ASSOC)) { $cols[] = $c['Field']; }
  $colList = '`' . implode('`,`', $cols) . '`';

  for ($offset=0; $offset<$count; $offset+=$step) {
    $q = $pdo->prepare("SELECT * FROM `{$table}` LIMIT {$step} OFFSET {$offset}");
    $q->execute();
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) continue;

    $valsChunk = [];
    foreach ($rows as $r) {
      $vals = [];
      foreach ($cols as $col) {
        $v = $r[$col];
        if (is_null($v)) $vals[] = 'NULL';
        elseif (is_numeric($v) && !preg_match('/^0[0-9]+$/', (string)$v)) $vals[] = $v;
        else $vals[] = $GLOBALS['pdo']->quote($v);
      }
      $valsChunk[] = '(' . implode(',', $vals) . ')';
    }
    if ($valsChunk) {
      $write("INSERT INTO `{$table}` ({$colList}) VALUES \n" . implode(",\n", $valsChunk) . ";\n");
    }
  }
  $write("\n");
}

// footer
$write($dumpFooter);

// tutup writer
if ($saveToServer) {
  if ($wantGzip) gzclose($out); else fclose($out);

  // ringkasan + daftar
  $fileTime = time();
  $fileSize = is_file($serverPath) ? filesize($serverPath) : 0;
  $fileUrl  = 'backups/' . rawurlencode($fileName);

  page_header('Backup Selesai');
  echo "<h2>Backup Berhasil</h2>
  <div class='card'>
    <div><strong>Nama file:</strong> ".htmlspecialchars($fileName)."</div>
    <div><strong>Tanggal/Jam:</strong> ".date('d-m-Y H:i:s', $fileTime)."</div>
    <div><strong>Ukuran:</strong> ".human_size($fileSize)."</div>
    <div class='row' style='margin-top:.6rem'>
      <a class='btn' href='".htmlspecialchars($fileUrl)."' download>⬇️ Unduh File</a>
      <a class='btn' href='?mode=list'>📁 Lihat Semua Backup</a>
      <a class='btn' href='?'>🏠 Kembali ke Menu</a>
    </div>
  </div>";

  // Daftar terbaru di bawah
  $files = list_backups();
  echo "<h3>Daftar Backup (Terbaru)</h3><div class='card'><table>
    <thead><tr><th>Nama File</th><th class='right'>Ukuran</th><th>Tanggal/Jam</th><th>Unduh</th></tr></thead><tbody>";
  if (!$files) echo "<tr><td colspan='4'>Belum ada backup lain.</td></tr>";
  else foreach ($files as $f) {
    echo "<tr>
      <td>".htmlspecialchars($f['name'])."</td>
      <td class='right'>".human_size($f['size'])."</td>
      <td>".date('d-m-Y H:i:s', $f['mtime'])."</td>
      <td><a href='".htmlspecialchars($f['href'])."' download>Unduh</a></td>
    </tr>";
  }
  echo "</tbody></table></div>";
  page_footer();
  exit;

} else {
  // download stream selesai
  fclose($out);
  exit;
}
