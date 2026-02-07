<?php
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);
require_once __DIR__ . '/includes/header.php';

// Pagination & Filter
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT * FROM audit_logs";
$params = [];

if ($search) {
    $sql .= " WHERE action LIKE ? OR description LIKE ? OR username LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Count total for pagination
$count_sql = "SELECT COUNT(*) FROM audit_logs";
if ($search) {
    $count_sql .= " WHERE action LIKE ? OR description LIKE ? OR username LIKE ?";
    $st_count = $pdo->prepare($count_sql);
    $st_count->execute($params);
} else {
    $st_count = $pdo->query($count_sql);
}
$total_logs = $st_count->fetchColumn();
$total_pages = ceil($total_logs / $limit);
?>

<article>
    <header style="display:flex; justify-content:space-between; align-items:center;">
        <hgroup>
            <h3>Audit Trail</h3>
            <p>Histori aktivitas pengguna aplikasi</p>
        </hgroup>
        <form method="get" style="margin:0; width:300px;">
            <input type="search" name="search" placeholder="Cari aksi, user, atau detail..." value="<?= h($search) ?>" style="margin-bottom:0;">
        </form>
    </header>

    <div class="overflow-auto">
        <table class="striped">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>User</th>
                    <th>Aksi</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center;">Belum ada aktivitas tercatat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space:nowrap; font-size:0.85rem;"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                            <td><strong><?= h($log['username']) ?></strong></td>
                            <td><mark style="font-size:0.75rem;"><?= h($log['action']) ?></mark></td>
                            <td style="font-size:0.9rem;"><?= h($log['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav>
            <ul>
                <?php if ($page > 1): ?>
                    <li><a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">« Prev</a></li>
                <?php endif; ?>
                <li>Halaman <?= $page ?> dari <?= $total_pages ?></li>
                <?php if ($page < $total_pages): ?>
                    <li><a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next »</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
