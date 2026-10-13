<?php
/**
 * public/products/create.php - Tambah Produk Baru & Relasi Many-to-Many Supplier
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

$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers  = $db->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$errors = [];
$skuVal         = '';
$nameVal        = '';
$categoryIdVal  = '';
$priceVal       = '';
$stockVal       = '0';
$unitVal        = 'pcs';
$descVal        = '';
$selectedSups   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verifikasi CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid.';
    }

    $skuVal        = strtoupper(trim($_POST['sku'] ?? ''));
    $nameVal       = trim($_POST['name'] ?? '');
    $categoryIdVal = (int)($_POST['category_id'] ?? 0);
    $priceVal      = trim($_POST['price'] ?? '');
    $stockVal      = trim($_POST['stock'] ?? '0');
    $unitVal       = trim($_POST['unit'] ?? 'pcs');
    $descVal       = trim($_POST['description'] ?? '');
    $selectedSups  = $_POST['suppliers'] ?? [];

    // 2. Validasi Server-Side (Business Rules)
    if (empty($skuVal)) {
        $errors[] = 'Kode SKU wajib diisi.';
    } elseif (!is_sku_unique($skuVal)) {
        // Aturan Bisnis: SKU harus unik
        $errors[] = "Kode SKU \"{$skuVal}\" sudah digunakan pada menu lain. SKU harus unik!";
    }

    if (empty($nameVal)) {
        $errors[] = 'Nama produk wajib diisi.';
    } elseif (mb_strlen($nameVal) < 3) {
        $errors[] = 'Nama produk minimal 3 karakter.';
    }

    if ($categoryIdVal <= 0) {
        $errors[] = 'Silakan pilih kategori produk yang valid.';
    }

    // Aturan Bisnis: Harga dan quantity tidak boleh negatif
    if (!validate_price($priceVal)) {
        $errors[] = 'Harga produk harus berupa angka dan tidak boleh negatif.';
    }

    // Aturan Bisnis: Stok tidak boleh menjadi negatif
    if (!is_numeric($stockVal) || (int)$stockVal < 0) {
        $errors[] = 'Stok awal tidak boleh berupa bilangan negatif.';
    }

    // 3. Simpan Transaksional (DB Transaction with commit/rollback)
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO products (sku, name, category_id, price, stock, unit, description)
                VALUES (:sku, :name, :cat_id, :price, :stock, :unit, :desc)
            ");
            $stmt->execute([
                ':sku'     => $skuVal,
                ':name'    => $nameVal,
                ':cat_id'  => $categoryIdVal,
                ':price'   => (float)$priceVal,
                ':stock'   => (int)$stockVal,
                ':unit'    => $unitVal,
                ':desc'    => $descVal ?: null,
            ]);

            $newProductId = (int)$db->lastInsertId();

            // Many-to-Many: Simpan Supplier yang dipilih
            if (!empty($selectedSups) && is_array($selectedSups)) {
                $stmtPS = $db->prepare("
                    INSERT INTO product_suppliers (product_id, supplier_id, supply_price, lead_time_days)
                    VALUES (:pid, :sid, :price, :lead)
                ");
                foreach ($selectedSups as $supId) {
                    $supIdInt = (int)$supId;
                    if ($supIdInt > 0) {
                        $stmtPS->execute([
                            ':pid'   => $newProductId,
                            ':sid'   => $supIdInt,
                            ':price' => (float)$priceVal * 0.6, // estimasi modal 60%
                            ':lead'  => 2
                        ]);
                    }
                }
            }

            // Jika stok awal > 0, catat sebagai transaksi barang masuk perdana
            if ((int)$stockVal > 0) {
                $stmtTrx = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (reference_no, product_id, supplier_id, user_id, type, quantity, stock_before, stock_after, notes)
                    VALUES (:ref, :pid, :sid, :uid, 'IN', :qty, 0, :after, :notes)
                ");
                $refNo = 'TRX-INIT-' . date('Ymd') . '-' . sprintf('%03d', $newProductId);
                $firstSup = !empty($selectedSups) ? (int)$selectedSups[0] : null;

                $stmtTrx->execute([
                    ':ref'   => $refNo,
                    ':pid'   => $newProductId,
                    ':sid'   => $firstSup,
                    ':uid'   => (int)$_SESSION['user_id'],
                    ':qty'   => (int)$stockVal,
                    ':after' => (int)$stockVal,
                    ':notes' => 'Saldo stok awal saat registrasi menu baru di sistem'
                ]);
            }

            $db->commit();

            set_flash('success', "Menu \"{$nameVal}\" (SKU: {$skuVal}) berhasil ditambahkan ke katalog etalase!");
            header("Location: {$baseUrl}/public/products/index.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Gagal menyimpan produk: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Tambah Menu Baru';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">➕ Tambah Menu Produk Baru</h1>
        <p class="page-subtitle">Daftarkan produk baru, tentukan kode SKU unik, dan hubungkan dengan mitra supplier.</p>
    </div>
    <div>
        <a href="<?= e($baseUrl) ?>/public/products/index.php" class="btn btn-secondary">&larr; Kembali ke Daftar Produk</a>
    </div>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <div>
                <strong>Terdapat beberapa kesalahan validasi:</strong>
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

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label" for="sku">Kode SKU (Unik) <span style="color:red">*</span></label>
                <input type="text" id="sku" name="sku" class="form-control" 
                       value="<?= e($skuVal) ?>" required placeholder="Contoh: SWB-PST-003" style="font-family: monospace; font-weight: bold;">
                <small style="color: var(--text-muted); font-size: 0.75rem;">Aturan: SKU harus unik di database.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Nama Menu Produk <span style="color:red">*</span></label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= e($nameVal) ?>" required placeholder="Contoh: Danish Blueberry Crumble">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label" for="category_id">Kategori Menu <span style="color:red">*</span></label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $categoryIdVal == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="price">Harga Jual (Rp) <span style="color:red">*</span></label>
                <input type="number" step="0.01" min="0" id="price" name="price" class="form-control" 
                       value="<?= e($priceVal) ?>" required placeholder="Contoh: 35000">
                <small style="color: var(--text-muted); font-size: 0.75rem;">Aturan: Harga tidak boleh negatif.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="stock">Stok Awal Fisik <span style="color:red">*</span></label>
                <input type="number" min="0" id="stock" name="stock" class="form-control" 
                       value="<?= e($stockVal) ?>" required placeholder="0">
                <small style="color: var(--text-muted); font-size: 0.75rem;">Aturan: Stok tidak boleh negatif.</small>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="unit">Satuan Penjualan</label>
            <input type="text" id="unit" name="unit" class="form-control" 
                   value="<?= e($unitVal) ?>" placeholder="pcs, slice, loaf, pack" style="max-width: 200px;">
        </div>

        <!-- Many-to-Many Supplier Checkboxes -->
        <div class="form-group" style="background: var(--primary-light); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <label class="form-label" style="margin-bottom: 0.5rem;">
                🚚 Hubungkan Mitra Supplier (Relasi Many-to-Many)
            </label>
            <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                Satu produk bakery dapat dipasok oleh lebih dari satu supplier bahan baku.
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.5rem;">
                <?php foreach ($suppliers as $sup): ?>
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; cursor: pointer;">
                    <input type="checkbox" name="suppliers[]" value="<?= (int)$sup['id'] ?>"
                           <?= in_array($sup['id'], (array)$selectedSups) ? 'checked' : '' ?>>
                    <span><?= e($sup['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="description">Deskripsi Singkat / Catatan Resep</label>
            <textarea id="description" name="description" class="form-control" rows="3" 
                      placeholder="Jelaskan karakteristik pastry, komposisi rasa, atau instruksi simpan..."><?= e($descVal) ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <a href="<?= e($baseUrl) ?>/public/products/index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">💾 Simpan Menu Produk</button>
        </div>
    </form>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/footer.php'; ?>
