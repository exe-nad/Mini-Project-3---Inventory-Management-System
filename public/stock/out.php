<?php
/**
 * public/stock/out.php - Transaksi Barang Keluar (Penjualan / Distribusi)
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

$preselectedProductId = (int)($_GET['product_id'] ?? 0);

$products = $db->query("SELECT id, sku, name, stock, unit FROM products ORDER BY name ASC")->fetchAll();

$errors = [];
$productIdVal = $preselectedProductId ?: '';
$quantityVal  = '';
$notesVal     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid.';
    }

    $productIdVal = (int)($_POST['product_id'] ?? 0);
    $quantityVal  = trim($_POST['quantity'] ?? '');
    $notesVal     = trim($_POST['notes'] ?? '');

    if ($productIdVal <= 0) {
        $errors[] = 'Silakan pilih produk yang akan dikeluarkan.';
    }

    // Aturan Bisnis: Kuantitas tidak boleh negatif atau 0
    if (!validate_quantity($quantityVal)) {
        $errors[] = 'Jumlah barang keluar harus berupa bilangan positif lebih dari 0.';
    }

    $qtyInt = (int)$quantityVal;

    // Aturan Bisnis: Barang keluar tidak boleh melebihi stok & stok tidak boleh menjadi negatif
    if (empty($errors)) {
        $checkFulfill = can_fulfill_stock_out($productIdVal, $qtyInt);
        if (!$checkFulfill['allowed']) {
            $errors[] = $checkFulfill['error'];
        }
    }

    if (empty($errors)) {
        try {
            // DB Transaction with commit & rollback
            $db->beginTransaction();

            $stmtProd = $db->prepare("SELECT id, sku, name, stock FROM products WHERE id = :id");
            $stmtProd->execute([':id' => $productIdVal]);
            $product = $stmtProd->fetch();

            if (!$product) {
                throw new Exception('Produk tidak ditemukan di basis data.');
            }

            $stockBefore = (int)$product['stock'];
            if ($qtyInt > $stockBefore) {
                throw new Exception("Pelanggaran Stok: Permintaan keluar ({$qtyInt}) melebihi stok yang tersisa ({$stockBefore})!");
            }

            $stockAfter = $stockBefore - $qtyInt;

            // Pastikan stok tidak menjadi negatif
            if ($stockAfter < 0) {
                throw new Exception('Aturan Bisnis: Stok tidak boleh menjadi negatif.');
            }

            $refNo = 'TRX-OUT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $stmtTrx = $db->prepare("
                INSERT INTO inventory_transactions 
                (reference_no, product_id, supplier_id, user_id, type, quantity, stock_before, stock_after, notes)
                VALUES (:ref, :pid, NULL, :uid, 'OUT', :qty, :before, :after, :notes)
            ");
            $stmtTrx->execute([
                ':ref'    => $refNo,
                ':pid'    => $productIdVal,
                ':uid'    => (int)$_SESSION['user_id'],
                ':qty'    => $qtyInt,
                ':before' => $stockBefore,
                ':after'  => $stockAfter,
                ':notes'  => $notesVal ?: 'Pengeluaran display etalase / penjualan'
            ]);

            $stmtUpd = $db->prepare("UPDATE products SET stock = :after, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmtUpd->execute([
                ':after' => $stockAfter,
                ':id'    => $productIdVal
            ]);

            $db->commit();

            set_flash('success', "Transaksi barang keluar berhasil dicatat! [Ref: {$refNo}] Stok \"{$product['name']}\" berkurang -{$qtyInt} (Sisa Stok: {$stockAfter}).");
            header("Location: {$baseUrl}/public/stock/index.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Gagal memproses transaksi barang keluar: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Catat Barang Keluar (OUT)';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📤 Catat Transaksi Barang Keluar</h1>
        <p class="page-subtitle">Pencatatan distribusi display etalase, penjualan harian, atau konsumsi toko.</p>
    </div>
    <div>
        <a href="<?= e($baseUrl) ?>/public/stock/index.php" class="btn btn-secondary">&larr; Kembali ke Histori</a>
    </div>
</div>

<div class="card" style="max-width: 650px; margin: 0 auto; border-top: 5px solid var(--danger);">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <div>
                <strong>Transaksi ditolak sistem:</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="product_id">Pilih Produk Yang Dikeluarkan <span style="color:red">*</span></label>
            <select id="product_id" name="product_id" class="form-control" required onchange="updateMaxQty(this)">
                <option value="">-- Pilih Produk --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" 
                            data-stock="<?= (int)$p['stock'] ?>"
                            data-unit="<?= e($p['unit']) ?>"
                            <?= $productIdVal == $p['id'] ? 'selected' : '' ?>>
                        [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Tersedia: <?= number_format($p['stock']) ?> <?= e($p['unit']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="quantity">Jumlah Keluar <span style="color:red">*</span></label>
            <input type="number" min="1" id="quantity" name="quantity" class="form-control" 
                   value="<?= e($quantityVal) ?>" required placeholder="Contoh: 5">
            <small id="stockHint" style="color: var(--text-muted); font-size: 0.75rem;">
                Aturan Bisnis: Barang keluar tidak boleh melebihi stok yang ada di sistem.
            </small>
        </div>

        <div class="form-group">
            <label class="form-label" for="notes">Tujuan / Alasan Pengeluaran</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" 
                      placeholder="Contoh: Display etalase kafe pagi, pesanan katering kantor..."><?= e($notesVal) ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <a href="<?= e($baseUrl) ?>/public/stock/index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-danger">📤 Simpan Barang Keluar</button>
        </div>
    </form>
</div>

<script>
function updateMaxQty(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const stock = selectedOption.getAttribute('data-stock');
    const unit = selectedOption.getAttribute('data-unit') || 'unit';
    const qtyInput = document.getElementById('quantity');
    const hint = document.getElementById('stockHint');
    
    if (stock !== null) {
        qtyInput.max = stock;
        hint.innerHTML = 'Maksimal yang bisa dikeluarkan: <strong style="color:var(--danger);">' + stock + ' ' + unit + '</strong>';
    } else {
        hint.innerHTML = 'Aturan Bisnis: Barang keluar tidak boleh melebihi stok yang ada di sistem.';
    }
}
</script>

<?php include dirname(dirname(__DIR__)) . '/views/footer.php'; ?>
