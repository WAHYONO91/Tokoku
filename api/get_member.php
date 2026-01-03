<?php
/**
 * API: Ambil data member berdasarkan kode
 * URL contoh:
 *   /tokoapp/api/get_member.php?kode=0001
 *
 * Response JSON (jika sukses):
 * {
 *   "kode": "0001",
 *   "nama": "Fathir",
 *   "poin": 305,
 *   "jenis": "umum"
 * }
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Helper kirim JSON + status code
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Ambil kode dari query string
$kode = isset($_GET['kode']) ? trim($_GET['kode']) : '';

if ($kode === '') {
    json_response(['error' => 'Kode kosong'], 400);
}

try {
    // Ambil semua kolom supaya aman meski struktur berubah
    $sql = "SELECT * FROM members WHERE kode = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$kode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_response(['error' => 'Member tidak ditemukan'], 404);
    }

    // PRIORITAS: points > poin > point
    $poin = 0;
    if (array_key_exists('points', $row)) {
        $poin = (int)$row['points'];          // ini yang dipakai karena di DB kamu isi di sini
    } elseif (array_key_exists('poin', $row)) {
        $poin = (int)$row['poin'];
    } elseif (array_key_exists('point', $row)) {
        $poin = (int)$row['point'];
    }

    // Jenis (umum / grosir)
    $jenis = 'umum';
    if (array_key_exists('jenis', $row)) {
        $j = trim((string)$row['jenis']);
        if ($j !== '') {
            $jenis = strtolower($j);
        }
    }

    $data = [
        'kode'  => $row['kode'] ?? $kode,
        'nama'  => $row['nama'] ?? '',
        'poin'  => $poin,
        'jenis' => $jenis,
    ];

    json_response($data);

} catch (Throwable $e) {
    // Kalau ada error DB / PHP, tetap balikin JSON (bukan HTML)
    json_response([
        'error' => 'Server error',
        // kalau tidak mau kelihatan detail error, bisa hapus 'msg' ini
        'msg'   => $e->getMessage(),
    ], 500);
}
