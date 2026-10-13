<?php
/**
 * public/products/index.php - Listing Produk (Search, Filter, Sorting, Pagination)
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

// 1. Parameter Input GET: Search, Filter, Sorting, Pagination
$search = trim($_GET['search'] ?? '');
$categoryId = trim($_GET['category_id'] ?? '');
$stockFilter = trim($_GET['stock_filter'] ?? ''); // 'low', 'empty', 'available'

// Whitelist Sorting (Aturan Bisnis: Sorting hanya menggunakan kolom yang diizinkan)
$allowedSortColumns = ['sku', 'name', 'category', 'price', 'stock', 'created_at'];
$sortBy = validate_sort_column($_GET['sort_by'] ?? 'created_at', $allowedSortColumns, 'created_at');
$sortOrder = validate_sort_direction($_GET['sort_order'] ?? 'DESC', 'DESC');

// Pagination
$perPage = 6;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// 2. Membangun Kueri Dinamis Berparameter (Prepared Statements Aman Anti-SQLi)
$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(p.name LIKE :s_name OR p.sku LIKE :s_sku OR p.description LIKE :s_desc)";
    $params[':s_name'] = "%{$search}%";
    $params[':s_sku']  = "%{$search}%";
    $params[':s_desc'] = "%{$search}%";
}

if ($categoryId !== '' && is_numeric($categoryId)) {
    $whereClauses[] = "p.category_id = :cat_id";
    $params[':cat_id'] = (int)$categoryId;
}

if ($stockFilter === 'low') {
    $whereClauses[] = "p.stock <= 10 AND p.stock > 0";
} elseif ($stockFilter === 'empty') {
    $whereClauses[] = "p.stock = 0";
} elseif ($stockFilter === 'available') {
    $whereClauses[] = "p.stock > 0";
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Hitung Total Data untuk Pagination
$countSql = "SELECT COUNT(*) FROM products p {$whereSql}";
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Pemetaan ORDER BY yang aman
$orderColumnSql = match ($sortBy) {
    'sku' => 'p.sku',
    'name' => 'p.name',
    'category' => 'c.name',
    'price' => 'p.price',
    'stock' => 'p.stock',
    default => 'p.created_at'
};

// Ambil Data Produk dengan Relasi Many-to-Many Supplier
// Many-to-Many: kita agregasikan nama supplier dengan GROUP_CONCAT
$dataSql = "
    SELECT p.id, p.sku, p.name, p.category_id, p.price, p.stock, p.unit, p.description, p.created_at,
           c.name AS category_name,
           (SELECT COUNT(*) FROM inventory_transactions WHERE product_id = p.id) AS transaction_count,
           (
               SELECT GROUP_CONCAT(s.name SEPARATOR ', ')
               FROM product_suppliers ps
               JOIN suppliers s ON ps.supplier_id = s.id
               WHERE ps.product_id = p.id
           ) AS suppliers_list
    FROM products p
    JOIN categories c ON p.category_id = c.id
    {$whereSql}
    ORDER BY {$orderColumnSql} {$sortOrder}
    LIMIT {$perPage} OFFSET {$offset}
";

$stmtData = $db->prepare($dataSql);
$stmtData->execute($params);
$products = $stmtData->fetchAll();

// Ambil List Kategori untuk Filter Dropdown
$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Katalog & Inventaris Produk';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🍞 Master Data Produk & Etalase</h1>
        <p class="page-subtitle">Kelola master produk, SKU, kategori, relasi supplier, dan stok inventaris.</p>
    </div>
    <div>
        <a href="<?= e($baseUrl) ?>/public/products/create.php" class="btn btn-primary">➕ Tambah Menu Baru</a>
    </div>
</div>

<!-- Filter, Search & Sorting Bar -->
<form method="GET" action="" class="filter-bar">
    <div style="flex: 2; min-width: 220px;">
        <input type="text" name="search" class="form-control" placeholder="🔍 Cari nama roti, rasa, atau SKU..." value="<?= e($search) ?>">
    </div>

    <div style="flex: 1; min-width: 160px;">
        <select name="category_id" class="form-control">
            <option value="">-- Semua Kategori --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="flex: 1; min-width: 140px;">
        <select name="stock_filter" class="form-control">
            <option value="">-- Status Stok --</option>
            <option value="available" <?= $stockFilter === 'available' ? 'selected' : '' ?>>Tersedia (&gt; 0)</option>
            <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Menipis (&le; 10)</option>
            <option value="empty" <?= $stockFilter === 'empty' ? 'selected' : '' ?>>Habis (0)</option>
        </select>
    </div>

    <div style="flex: 1; min-width: 140px;">
        <select name="sort_by" class="form-control">
            <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Urut: Terbaru</option>
            <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Urut: Nama Menu</option>
            <option value="sku" <?= $sortBy === 'sku' ? 'selected' : '' ?>>Urut: Kode SKU</option>
            <option value="price" <?= $sortBy === 'price' ? 'selected' : '' ?>>Urut: Harga</option>
            <option value="stock" <?= $sortBy === 'stock' ? 'selected' : '' ?>>Urut: Jumlah Stok</option>
        </select>
    </div>

    <div style="width: 100px;">
        <select name="sort_order" class="form-control">
            <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>A &rarr; Z / Naik</option>
            <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Z &rarr; A / Turun</option>
        </select>
    </div>

    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($search !== '' || $categoryId !== '' || $stockFilter !== '' || $sortBy !== 'created_at' || $sortOrder !== 'DESC'): ?>
        <a href="<?= e($baseUrl) ?>/public/products/index.php" class="btn btn-sm btn-secondary" title="Reset Filter">Reset</a>
    <?php endif; ?>
</form>

<!-- Table of Products -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Harga Satuan</th>
                    <th>Stok Fisik</th>
                    <th>Mitra Supplier (M:N)</th>
                    <th>Histori</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🥖</div>
                            Tidak ada produk yang cocok dengan kriteria pencarian / filter Anda.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <span class="badge-sku"><?= e($p['sku']) ?></span>
                        </td>
                        <td>
                            <strong style="font-size: 0.95rem;"><?= e($p['name']) ?></strong>
                            <?php if (!empty($p['description'])): ?>
                                <div style="font-size: 0.78rem; color: var(--text-muted); max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= e($p['description']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-primary"><?= e($p['category_name']) ?></span>
                        </td>
                        <td style="font-weight: 700; color: var(--text-primary);">
                            <?= format_rupiah($p['price']) ?>
                            <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">/<?= e($p['unit']) ?></span>
                        </td>
                        <td>
                            <?php if ($p['stock'] == 0): ?>
                                <span class="badge badge-danger">Habis (0)</span>
                            <?php elseif ($p['stock'] <= 10): ?>
                                <span class="badge badge-warning"><?= number_format($p['stock']) ?> <?= e($p['unit']) ?> (Menipis)</span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= number_format($p['stock']) ?> <?= e($p['unit']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.8rem; max-width: 200px;">
                            <?php if (!empty($p['suppliers_list'])): ?>
                                <span>🚚 <?= e($p['suppliers_list']) ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic;">Belum terhubung</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$p['transaction_count'] > 0): ?>
                                <span class="badge badge-info" title="Memiliki riwayat mutasi barang">
                                    <?= number_format($p['transaction_count']) ?> mutasi
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: #E0E0E0; color: #616161;">0 mutasi</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="<?= e($baseUrl) ?>/public/products/edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Produk">✏️ Edit</a>
                            <a href="<?= e($baseUrl) ?>/public/products/delete.php?id=<?= (int)$p['id'] ?>" class="btn btn-danger btn-sm" title="Hapus Produk">🗑️ Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination Bar -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <div>
        Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <strong><?= $totalRows ?></strong> produk)
    </div>
    <div class="pagination-links">
        <?php
        // Query string generator untuk menjaga state search/filter/sort saat ganti halaman
        $queryParams = $_GET;
        ?>
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
