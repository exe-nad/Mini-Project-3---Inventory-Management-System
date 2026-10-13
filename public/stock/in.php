<?php
/**
 * public/stock/in.php - Transaksi Barang Masuk (Restock / Penerimaan Supplier)
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

$products  = $db->query("SELECT id, sku, name, stock, unit FROM products ORDER BY name ASC")->fetchAll();
$suppliers = $db->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$errors = [];
$productIdVal  = $preselectedProductId ?: '';
$supplierIdVal = '';
$quantityVal   = '';
$notesVal      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid.';
    }

    $productIdVal  = (int)($_POST['product_id'] ?? 0);
    $supplierIdVal = (int)($_POST['supplier_id'] ?? 0);
    $quantityVal   = trim($_POST['quantity'] ?? '');
    $notesVal      = trim($_POST['notes'] ?? '');

    if ($productIdVal <= 0) {
        $errors[] = 'Silakan pilih produk yang akan ditambahkan stoknya.';
    }

    // Aturan Bisnis: Kuantitas tidak boleh negatif / <= 0
    if (!validate_quantity($quantityVal)) {
        $errors[] = 'Jumlah barang masuk harus berupa bilangan bulat positif lebih dari 0.';
    }

    $qtyInt = (int)$quantityVal;

    if (empty($errors)) {
        try {
            // DB Transaction with commit & rollback
            $db->beginTransaction();

            // Lock row produk (FOR UPDATE di MySQL / SELECT di SQLite)
            $stmtProd = $db->prepare("SELECT id, sku, name, stock FROM products WHERE id = :id");
            $stmtProd->execute([':id' => $productIdVal]);
            $product = $stmtProd->fetch();

            if (!$product) {
                throw new Exception('Produk tidak ditemukan di basis data.');
            }

            $stockBefore = (int)$product['stock'];
            $stockAfter  = $stockBefore + $qtyInt;

            // Generate nomor referensi unik
            $refNo = 'TRX-IN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            // Simpan catatan transaksi ke inventory_transactions
            $stmtTrx = $db->prepare("
                INSERT INTO inventory_transactions 
                (reference_no, product_id, supplier_id, user_id, type, quantity, stock_before, stock_after, notes)
                VALUES (:ref, :pid, :sid, :uid, 'IN', :qty, :before, :after, :notes)
            ");
            $stmtTrx->execute([
                ':ref'    => $refNo,
                ':pid'    => $productIdVal,
                ':sid'    => $supplierIdVal > 0 ? $supplierIdVal : null,
                ':uid'    => (int)$_SESSION['user_id'],
                ':qty'    => $qtyInt,
                ':before' => $stockBefore,
                ':after'  => $stockAfter,
                ':notes'  => $notesVal ?: 'Penerimaan stok masuk'
            ]);

            // Update stok produk
            $stmtUpd = $db->prepare("UPDATE products SET stock = :after, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmtUpd->execute([
                ':after' => $stockAfter,
                ':id'    => $productIdVal
            ]);

            $db->commit();

            set_flash('success', "Transaksi barang masuk berhasil dicatat! [Ref: {$refNo}] Stok \"{$product['name']}\" bertambah +{$qtyInt} (Total: {$stockAfter}).");
            header("Location: {$baseUrl}/public/stock/index.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Gagal memproses transaksi: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Catat Barang Masuk (IN)';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📥 Catat Transaksi Barang Masuk (Restock)</h1>
        <p class="page-subtitle">Tambah stok fisik produk dari dapur produksi atau pasokan supplier.</p>
    </div>
    <div>
        <a href="<?= e($baseUrl) ?>/public/stock/index.php" class="btn btn-secondary">&larr; Kembali ke Histori</a>
    </div>
</div>

<div class="card" style="max-width: 650px; margin: 0 auto; border-top: 5px solid var(--success);">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <div>
                <strong>Gagal memproses transaksi:</strong>
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
            <label class="form-label" for="product_id">Pilih Produk Roti / Kue <span style="color:red">*</span></label>
            <select id="product_id" name="product_id" class="form-control" required>
                <option value="">-- Pilih Produk --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $productIdVal == $p['id'] ? 'selected' : '' ?>>
                        [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Sisa Stok: <?= number_format($p['stock']) ?> <?= e($p['unit']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label" for="quantity">Jumlah Masuk <span style="color:red">*</span></label>
                <input type="number" min="1" id="quantity" name="quantity" class="form-control" 
                       value="<?= e($quantityVal) ?>" required placeholder="Contoh: 20">
                <small style="color: var(--text-muted); font-size: 0.75rem;">Aturan: Jumlah harus bilangan positif.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="supplier_id">Pemasok / Sumber Bahan</label>
                <select id="supplier_id" name="supplier_id" class="form-control">
                    <option value="">-- Dapur Internal / Umum --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $supplierIdVal == $s['id'] ? 'selected' : '' ?>>
                            🚚 <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="notes">Keterangan / Catatan Batch</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" 
                      placeholder="Contoh: Penerimaan adonan pagi, batch fresh bake #104..."><?= e($notesVal) ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <a href="<?= e($baseUrl) ?>/public/stock/index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-success">📥 Simpan Barang Masuk</button>
        </div>
    </form>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/footer.php'; ?>
