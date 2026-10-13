<?php
/**
 * public/stock/index.php - Histori Mutasi Transaksi Stok (Masuk & Keluar)
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 * Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/helpers/escape.php';
require_once dirname(dirname(__DIR__)) . '/helpers/auth.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';

require_login();

$db = get_db();
$baseUrl = get_base_url();

// Parameter Pencarian, Filter & Pagination
$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? ''); // 'IN' atau 'OUT'
$productId = trim($_GET['product_id'] ?? '');

$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(t.reference_no LIKE :s_ref OR p.name LIKE :s_pname OR p.sku LIKE :s_sku OR t.notes LIKE :s_notes)";
    $params[':s_ref']   = "%{$search}%";
    $params[':s_pname'] = "%{$search}%";
    $params[':s_sku']   = "%{$search}%";
    $params[':s_notes'] = "%{$search}%";
}

if ($typeFilter === 'IN' || $typeFilter === 'OUT') {
    $whereClauses[] = "t.type = :type";
    $params[':type'] = $typeFilter;
}

if ($productId !== '' && is_numeric($productId)) {
    $whereClauses[] = "t.product_id = :pid";
    $params[':pid'] = (int)$productId;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Hitung Total Data
$countSql = "
    SELECT COUNT(*) 
    FROM inventory_transactions t
    JOIN products p ON t.product_id = p.id
    {$whereSql}
";
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Ambil Data Transaksi Berparameter
$dataSql = "
    SELECT t.id, t.reference_no, t.type, t.quantity, t.stock_before, t.stock_after, 
           t.notes, t.transaction_date,
           p.sku, p.name AS product_name, p.unit,
           s.name AS supplier_name,
           u.full_name AS operator_name
    FROM inventory_transactions t
    JOIN products p ON t.product_id = p.id
    LEFT JOIN suppliers s ON t.supplier_id = s.id
    JOIN users u ON t.user_id = u.id
    {$whereSql}
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmtData = $db->prepare($dataSql);
$stmtData->execute($params);
$transactions = $stmtData->fetchAll();

// Daftar produk untuk filter
$productsList = $db->query("SELECT id, name, sku FROM products ORDER BY name ASC")->fetchAll();

$pageTitle = 'Histori Transaksi Stok';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📦 Histori Transaksi & Log Mutasi Stok</h1>
        <p class="page-subtitle">Pencatatan lengkap audit trail barang masuk dan keluar toko roti.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= e($baseUrl) ?>/public/stock/in.php" class="btn btn-success">📥 + Transaksi Masuk (IN)</a>
        <a href="<?= e($baseUrl) ?>/public/stock/out.php" class="btn btn-danger">📤 - Transaksi Keluar (OUT)</a>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar">
    <div style="flex: 2; min-width: 220px;">
        <input type="text" name="search" class="form-control" placeholder="🔍 Cari No. Ref, SKU, produk, catatan..." value="<?= e($search) ?>">
    </div>

    <div style="flex: 1; min-width: 150px;">
        <select name="type" class="form-control">
            <option value="">-- Semua Tipe --</option>
            <option value="IN" <?= $typeFilter === 'IN' ? 'selected' : '' ?>>📥 Barang Masuk (IN)</option>
            <option value="OUT" <?= $typeFilter === 'OUT' ? 'selected' : '' ?>>📤 Barang Keluar (OUT)</option>
        </select>
    </div>

    <div style="flex: 1.5; min-width: 200px;">
        <select name="product_id" class="form-control">
            <option value="">-- Semua Produk --</option>
            <?php foreach ($productsList as $pr): ?>
                <option value="<?= (int)$pr['id'] ?>" <?= $productId == $pr['id'] ? 'selected' : '' ?>>
                    <?= e($pr['sku']) ?> - <?= e($pr['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($search !== '' || $typeFilter !== '' || $productId !== ''): ?>
        <a href="<?= e($baseUrl) ?>/public/stock/index.php" class="btn btn-sm btn-secondary">Reset</a>
    <?php endif; ?>
</form>

<!-- Table of Transactions -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>No. Referensi</th>
                    <th>Waktu Mutasi</th>
                    <th>Tipe</th>
                    <th>Produk / SKU</th>
                    <th>Jumlah</th>
                    <th>Perubahan Stok</th>
                    <th>Pemasok / Asal</th>
                    <th>Petugas</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                            Tidak ditemukan data transaksi inventaris yang sesuai.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $trx): ?>
                    <tr>
                        <td><code><?= e($trx['reference_no']) ?></code></td>
                        <td><?= format_datetime($trx['transaction_date']) ?></td>
                        <td>
                            <?php if ($trx['type'] === 'IN'): ?>
                                <span class="badge badge-success">📥 MASUK</span>
                            <?php else: ?>
                                <span class="badge badge-danger">📤 KELUAR</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-sku"><?= e($trx['sku']) ?></span>
                            <strong><?= e($trx['product_name']) ?></strong>
                        </td>
                        <td style="font-weight: 800; color: <?= $trx['type'] === 'IN' ? 'var(--success)' : 'var(--danger)' ?>;">
                            <?= $trx['type'] === 'IN' ? '+' : '-' ?><?= number_format($trx['quantity']) ?> <?= e($trx['unit']) ?>
                        </td>
                        <td>
                            <?= number_format($trx['stock_before']) ?> &rarr; 
                            <strong style="color: var(--text-primary);"><?= number_format($trx['stock_after']) ?></strong>
                        </td>
                        <td>
                            <?= !empty($trx['supplier_name']) ? '🚚 ' . e($trx['supplier_name']) : '<span style="color:var(--text-muted);">-</span>' ?>
                        </td>
                        <td>👤 <?= e($trx['operator_name']) ?></td>
                        <td style="max-width: 220px; font-size: 0.82rem; color: var(--text-muted);">
                            <?= !empty($trx['notes']) ? e($trx['notes']) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <div>
        Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <strong><?= $totalRows ?></strong> catatan transaksi)
    </div>
    <div class="pagination-links">
        <?php $queryParams = $_GET; ?>
        <?php if ($page > 1): ?>
            <?php $queryParams['page'] = $page - 1; ?>
            <a href="?<?= e(http_build_query($queryParams)) ?>" class="page-btn">&laquo; Sebelumnya</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $queryParams['page'] = $i; ?>
            <a href="?<?= e(http_build_query($queryParams)) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <?php $queryParams['page'] = $page + 1; ?>
            <a href="?<?= e(http_build_query($queryParams)) ?>" class="page-btn">Selanjutnya &raquo;</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include dirname(dirname(__DIR__)) . '/views/footer.php'; ?>
