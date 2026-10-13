<?php
/**
 * public/products/delete.php - Hapus Produk dengan Pemeriksaan Aturan Bisnis
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

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    set_flash('danger', 'ID Produk tidak valid.');
    header("Location: {$baseUrl}/public/products/index.php");
    exit;
}

$stmt = $db->prepare("
    SELECT p.id, p.sku, p.name, p.stock, p.unit, c.name AS category_name,
           (SELECT COUNT(*) FROM inventory_transactions WHERE product_id = p.id) AS trx_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.id = :id
");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk yang ingin dihapus tidak ditemukan.');
    header("Location: {$baseUrl}/public/products/index.php");
    exit;
}

$hasHistory = (int)$product['trx_count'] > 0;

// Proses POST Penghapusan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Validasi token keamanan (CSRF) gagal.');
        header("Location: {$baseUrl}/public/products/index.php");
        exit;
    }

    // Aturan Bisnis: Produk yang memiliki histori tidak boleh dihapus sembarangan
    if ($hasHistory) {
        set_flash('danger', "Pelanggaran Aturan Bisnis: Produk \"{$product['name']}\" ({$product['sku']}) tidak boleh dihapus karena memiliki {$product['trx_count']} riwayat transaksi inventaris!");
        header("Location: {$baseUrl}/public/products/index.php");
        exit;
    }

    try {
        $db->beginTransaction();

        // Hapus relasi di product_suppliers
        $delPS = $db->prepare("DELETE FROM product_suppliers WHERE product_id = :id");
        $delPS->execute([':id' => $id]);

        // Hapus produk
        $delProd = $db->prepare("DELETE FROM products WHERE id = :id");
        $delProd->execute([':id' => $id]);

        $db->commit();

        set_flash('success', "Produk \"{$product['name']}\" ({$product['sku']}) berhasil dihapus permanen.");
        header("Location: {$baseUrl}/public/products/index.php");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('danger', 'Gagal menghapus produk: ' . $e->getMessage());
        header("Location: {$baseUrl}/public/products/index.php");
        exit;
    }
}

$pageTitle = 'Konfirmasi Hapus Produk';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🗑️ Konfirmasi Penghapusan Produk</h1>
        <p class="page-subtitle">Verifikasi integritas data dan riwayat sebelum melakukan tindakan destruktif.</p>
    </div>
    <div>
        <a href="<?= e($baseUrl) ?>/public/products/index.php" class="btn btn-secondary">&larr; Batal & Kembali</a>
    </div>
</div>

<div class="card" style="max-width: 650px; margin: 2rem auto; border-top: 5px solid var(--danger);">
    <div style="font-size: 2.5rem; text-align: center; margin-bottom: 0.5rem;">⚠️</div>
    <h2 style="text-align: center; font-size: 1.35rem; color: var(--danger); margin-bottom: 1rem;">
        Konfirmasi Hapus Menu: "<?= e($product['name']) ?>"
    </h2>

    <div style="background: var(--bg-main); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; font-size: 0.9rem;">
            <strong>Kode SKU:</strong> <span><code><?= e($product['sku']) ?></code></span>
            <strong>Nama Menu:</strong> <span><?= e($product['name']) ?></span>
            <strong>Kategori:</strong> <span><?= e($product['category_name']) ?></span>
            <strong>Stok Tersisa:</strong> <span><?= number_format($product['stock']) ?> <?= e($product['unit']) ?></span>
            <strong>Total Mutasi:</strong> <span><strong><?= number_format($product['trx_count']) ?></strong> transaksi tercatat</span>
        </div>
    </div>

    <?php if ($hasHistory): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <div>
                <div style="font-weight: 800; font-size: 0.95rem; margin-bottom: 0.35rem;">🚫 Tindakan Diblokir oleh Sistem!</div>
                <div>
                    Sesuai <strong>Aturan Bisnis</strong>: <em>"Produk yang memiliki histori tidak boleh dihapus sembarangan."</em>
                </div>
                <div style="font-size: 0.83rem; margin-top: 0.5rem; opacity: 0.9;">
                    Menu ini memiliki <?= $product['trx_count'] ?> catatan transaksi keluar/masuk. Untuk menjaga integritas pembukuan dan audit trail, penghapusan produk ini tidak diizinkan.
                </div>
            </div>
        </div>

        <div style="text-align: center;">
            <a href="<?= e($baseUrl) ?>/public/products/index.php" class="btn btn-primary">&larr; Kembali ke Daftar Produk</a>
        </div>
    <?php else: ?>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem; text-align: center;">
            Produk ini belum memiliki catatan riwayat mutasi transaksi, sehingga aman untuk dihapus. Tindakan ini tidak dapat dibatalkan.
        </p>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

            <div style="display: flex; justify-content: center; gap: 1rem;">
                <a href="<?= e($baseUrl) ?>/public/products/index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-danger">🗑️ Ya, Hapus Produk Ini</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/footer.php'; ?>
