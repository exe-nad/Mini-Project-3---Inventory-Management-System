<?php
/**
 * public/dashboard.php - Dashboard Statistik & Laporan Agregat Inventaris
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 * Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/escape.php';
require_once dirname(__DIR__) . '/helpers/auth.php';

require_login();

$db = get_db();
$currentUser = current_user();

// 1. Metrik Agregat Utama
$totalProducts = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalStock = (int)$db->query("SELECT COALESCE(SUM(stock), 0) FROM products")->fetchColumn();
$totalValuation = (float)$db->query("SELECT COALESCE(SUM(stock * price), 0) FROM products")->fetchColumn();
$lowStockCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock <= 10")->fetchColumn();

// Transaksi Masuk dan Keluar (Agregat)
$totalTrxIn = (int)$db->query("SELECT COUNT(*) FROM inventory_transactions WHERE type = 'IN'")->fetchColumn();
$totalTrxOut = (int)$db->query("SELECT COUNT(*) FROM inventory_transactions WHERE type = 'OUT'")->fetchColumn();
$sumQtyIn = (int)$db->query("SELECT COALESCE(SUM(quantity), 0) FROM inventory_transactions WHERE type = 'IN'")->fetchColumn();
$sumQtyOut = (int)$db->query("SELECT COALESCE(SUM(quantity), 0) FROM inventory_transactions WHERE type = 'OUT'")->fetchColumn();

// 2. Laporan Stok per Kategori
$catStatsSql = "
    SELECT c.name AS category_name, 
           COUNT(p.id) AS product_count, 
           COALESCE(SUM(p.stock), 0) AS total_stock,
           COALESCE(SUM(p.stock * p.price), 0) AS total_value
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY total_value DESC
";
$categoryStats = $db->query($catStatsSql)->fetchAll();

// 3. Produk dengan Stok Kritis / Menipis (Stok <= 10)
$lowStockProducts = $db->query("
    SELECT p.id, p.sku, p.name, p.stock, p.unit, p.price, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock <= 10
    ORDER BY p.stock ASC
    LIMIT 6
")->fetchAll();

// 4. Histori 5 Transaksi Terbaru
$recentTransactions = $db->query("
    SELECT t.reference_no, t.type, t.quantity, t.stock_before, t.stock_after, t.transaction_date,
           p.sku, p.name AS product_name,
           u.full_name AS operator_name
    FROM inventory_transactions t
    JOIN products p ON t.product_id = p.id
    JOIN users u ON t.user_id = u.id
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard Inventaris';
include dirname(__DIR__) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📊 Dashboard Inventaris SweetBakes</h1>
        <p class="page-subtitle">Ringkasan status stok, perputaran produk, dan agregat nilai inventaris bakery.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= e(get_base_url()) ?>/public/stock/in.php" class="btn btn-success">📥 + Barang Masuk</a>
        <a href="<?= e(get_base_url()) ?>/public/stock/out.php" class="btn btn-danger">📤 - Barang Keluar</a>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #EFEBE9; color: var(--primary);">🍞</div>
        <div>
            <div class="stat-label">Total Varian Menu</div>
            <div class="stat-value"><?= number_format($totalProducts) ?> <span style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted);">item</span></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--info-bg); color: var(--info);">📦</div>
        <div>
            <div class="stat-label">Total Stok Fisik</div>
            <div class="stat-value"><?= number_format($totalStock) ?> <span style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted);">unit</span></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-bg); color: var(--success);">💰</div>
        <div>
            <div class="stat-label">Valuasi Inventaris</div>
            <div class="stat-value" style="font-size: 1.25rem;"><?= format_rupiah($totalValuation) ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: <?= $lowStockCount > 0 ? 'var(--danger-bg)' : 'var(--success-bg)' ?>; color: <?= $lowStockCount > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
            <?= $lowStockCount > 0 ? '⚠️' : '✅' ?>
        </div>
        <div>
            <div class="stat-label">Stok Kritis (&le; 10)</div>
            <div class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                <?= number_format($lowStockCount) ?> <span style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted);">varian</span>
            </div>
        </div>
    </div>
</div>

<!-- Transaksi Masuk/Keluar Ringkas -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.75rem; background: linear-gradient(135deg, rgba(199, 107, 50, 0.05), rgba(212, 175, 55, 0.05));">
    <div style="display: flex; justify-content: space-around; flex-wrap: wrap; text-align: center; gap: 1rem;">
        <div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Transaksi Masuk (IN)</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--success); margin-top: 4px;">
                +<?= number_format($sumQtyIn) ?> unit <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">(<?= $totalTrxIn ?> trx)</span>
            </div>
        </div>
        <div style="border-right: 1px solid var(--border-color);"></div>
        <div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Transaksi Keluar (OUT)</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--danger); margin-top: 4px;">
                -<?= number_format($sumQtyOut) ?> unit <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">(<?= $totalTrxOut ?> trx)</span>
            </div>
        </div>
        <div style="border-right: 1px solid var(--border-color);"></div>
        <div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Net Flow Barang</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: <?= ($sumQtyIn - $sumQtyOut >= 0) ? 'var(--info)' : 'var(--warning)' ?>; margin-top: 4px;">
                <?= number_format($sumQtyIn - $sumQtyOut) ?> unit
            </div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Laporan Stok per Kategori -->
    <div class="card" style="margin-bottom: 0;">
        <h2 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem;">🏷️ Laporan Stok per Kategori</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Varian</th>
                        <th>Total Stok</th>
                        <th>Valuasi (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categoryStats)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">Belum ada data kategori.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categoryStats as $row): ?>
                        <tr>
                            <td><strong><?= e($row['category_name']) ?></strong></td>
                            <td><?= number_format($row['product_count']) ?></td>
                            <td><?= number_format($row['total_stock']) ?> pcs</td>
                            <td><?= format_rupiah($row['total_value']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alert Stok Kritis -->
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.15rem; font-weight: 800;">⚠️ Peringatan Stok Menipis (&le; 10)</h2>
            <a href="<?= e(get_base_url()) ?>/public/products/index.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Menu Produk</th>
                        <th>Sisa Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lowStockProducts)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--success); font-weight: 600;">✅ Seluruh stok produk dalam batas aman (> 10).</td></tr>
                    <?php else: ?>
                        <?php foreach ($lowStockProducts as $p): ?>
                        <tr>
                            <td><span class="badge-sku"><?= e($p['sku']) ?></span></td>
                            <td><?= e($p['name']) ?></td>
                            <td>
                                <span class="badge badge-danger"><?= number_format($p['stock']) ?> <?= e($p['unit']) ?></span>
                            </td>
                            <td>
                                <a href="<?= e(get_base_url()) ?>/public/stock/in.php?product_id=<?= (int)$p['id'] ?>" class="btn btn-success btn-sm">+ Tambah</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 5 Transaksi Terbaru -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.15rem; font-weight: 800;">📜 Histori Transaksi Inventaris Terbaru</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Aktivitas penerimaan & pengeluaran etalase terkini</p>
        </div>
        <a href="<?= e(get_base_url()) ?>/public/stock/index.php" class="btn btn-secondary btn-sm">Buka Histori Lengkap &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ref Transaksi</th>
                    <th>Waktu</th>
                    <th>Tipe</th>
                    <th>SKU & Produk</th>
                    <th>Jumlah</th>
                    <th>Sebelum &rarr; Sesudah</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTransactions)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted);">Belum ada riwayat transaksi.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $tx): ?>
                    <tr>
                        <td><code><?= e($tx['reference_no']) ?></code></td>
                        <td><?= format_datetime($tx['transaction_date']) ?></td>
                        <td>
                            <?php if ($tx['type'] === 'IN'): ?>
                                <span class="badge badge-success">📥 MASUK</span>
                            <?php else: ?>
                                <span class="badge badge-danger">📤 KELUAR</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-sku"><?= e($tx['sku']) ?></span>
                            <strong><?= e($tx['product_name']) ?></strong>
                        </td>
                        <td style="font-weight: 700; color: <?= $tx['type'] === 'IN' ? 'var(--success)' : 'var(--danger)' ?>;">
                            <?= $tx['type'] === 'IN' ? '+' : '-' ?><?= number_format($tx['quantity']) ?>
                        </td>
                        <td>
                            <?= number_format($tx['stock_before']) ?> &rarr; <strong><?= number_format($tx['stock_after']) ?></strong>
                        </td>
                        <td>👤 <?= e($tx['operator_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include dirname(__DIR__) . '/views/footer.php'; ?>
