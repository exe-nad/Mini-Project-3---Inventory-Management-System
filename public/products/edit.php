<?php
/**
 * public/products/edit.php - Ubah Data Produk & Kelola Relasi Supplier
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'ID Produk tidak valid.');
    header("Location: {$baseUrl}/public/products/index.php");
    exit;
}

// Ambil data produk saat ini
$stmtProd = $db->prepare("SELECT * FROM products WHERE id = :id");
$stmtProd->execute([':id' => $id]);
$product = $stmtProd->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan di database.');
    header("Location: {$baseUrl}/public/products/index.php");
    exit;
}

// Ambil relasi supplier saat ini
$currentSups = $db->prepare("SELECT supplier_id FROM product_suppliers WHERE product_id = :id");
$currentSups->execute([':id' => $id]);
$assignedSuppliers = $currentSups->fetchAll(PDO::FETCH_COLUMN);

$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers  = $db->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$errors = [];
$skuVal         = $product['sku'];
$nameVal        = $product['name'];
$categoryIdVal  = $product['category_id'];
$priceVal       = $product['price'];
$stockVal       = $product['stock'];
$unitVal        = $product['unit'];
$descVal        = $product['description'];
$selectedSups   = $assignedSuppliers;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid.';
    }

    $skuVal        = strtoupper(trim($_POST['sku'] ?? ''));
    $nameVal       = trim($_POST['name'] ?? '');
    $categoryIdVal = (int)($_POST['category_id'] ?? 0);
    $priceVal      = trim($_POST['price'] ?? '');
    $unitVal       = trim($_POST['unit'] ?? 'pcs');
    $descVal       = trim($_POST['description'] ?? '');
    $selectedSups  = $_POST['suppliers'] ?? [];

    // Validasi SKU unik (mengecualikan ID produk ini)
    if (empty($skuVal)) {
        $errors[] = 'Kode SKU wajib diisi.';
    } elseif (!is_sku_unique($skuVal, $id)) {
        $errors[] = "Kode SKU \"{$skuVal}\" sudah digunakan oleh produk lain. SKU harus unik!";
    }

    if (empty($nameVal)) {
        $errors[] = 'Nama produk wajib diisi.';
    } elseif (mb_strlen($nameVal) < 3) {
        $errors[] = 'Nama produk minimal 3 karakter.';
    }

    if ($categoryIdVal <= 0) {
        $errors[] = 'Pilih kategori produk yang valid.';
    }

    if (!validate_price($priceVal)) {
        $errors[] = 'Harga produk harus berupa angka non-negatif.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $stmtUpdate = $db->prepare("
                UPDATE products 
                SET sku = :sku, name = :name, category_id = :cat_id, price = :price, 
                    unit = :unit, description = :desc, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':sku'     => $skuVal,
                ':name'    => $nameVal,
                ':cat_id'  => $categoryIdVal,
                ':price'   => (float)$priceVal,
                ':unit'    => $unitVal,
                ':desc'    => $descVal ?: null,
                ':id'      => $id
            ]);

            // Sinkronisasi Many-to-Many Supplier
            $delPS = $db->prepare("DELETE FROM product_suppliers WHERE product_id = :id");
            $delPS->execute([':id' => $id]);

            if (!empty($selectedSups) && is_array($selectedSups)) {
                $insPS = $db->prepare("
                    INSERT INTO product_suppliers (product_id, supplier_id, supply_price, lead_time_days)
                    VALUES (:pid, :sid, :price, :lead)
                ");
                foreach ($selectedSups as $supId) {
                    $supIdInt = (int)$supId;
                    if ($supIdInt > 0) {
                        $insPS->execute([
                            ':pid'   => $id,
                            ':sid'   => $supIdInt,
                            ':price' => (float)$priceVal * 0.6,
                            ':lead'  => 2
                        ]);
                    }
                }
            }

            $db->commit();

            set_flash('success', "Perubahan data produk \"{$nameVal}\" berhasil disimpan!");
            header("Location: {$baseUrl}/public/products/index.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Gagal memperbarui produk: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Menu: ' . $product['name'];
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">✏️ Perbarui Data Produk</h1>
        <p class="page-subtitle">Ubah informasi menu, harga, kode SKU, dan daftar supplier yang terhubung.</p>
    </div>
    <div>
        <a href="<?= e($baseUrl) ?>/public/products/index.php" class="btn btn-secondary">&larr; Kembali</a>
    </div>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <div>
                <strong>Kesalahan pengisian form:</strong>
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
                <label class="form-label" for="sku">Kode SKU <span style="color:red">*</span></label>
                <input type="text" id="sku" name="sku" class="form-control" 
                       value="<?= e($skuVal) ?>" required style="font-family: monospace; font-weight: bold;">
                <small style="color: var(--text-muted); font-size: 0.75rem;">SKU unik terjaga di seluruh sistem.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Nama Menu Produk <span style="color:red">*</span></label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= e($nameVal) ?>" required>
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
                       value="<?= e($priceVal) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Stok Fisik Saat Ini</label>
                <input type="text" class="form-control" value="<?= number_format($product['stock']) ?> <?= e($product['unit']) ?>" disabled style="background: #EFEBE9; cursor: not-allowed;">
                <small style="color: var(--text-muted); font-size: 0.72rem;">Mutasi stok wajib via menu Transaksi Stok.</small>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="unit">Satuan Penjualan</label>
            <input type="text" id="unit" name="unit" class="form-control" 
                   value="<?= e($unitVal) ?>" style="max-width: 200px;">
        </div>

        <!-- Many-to-Many Supplier Checkboxes -->
        <div class="form-group" style="background: var(--primary-light); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <label class="form-label" style="margin-bottom: 0.5rem;">
                🚚 Hubungkan Mitra Supplier (Relasi Many-to-Many)
            </label>
            <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                Centang supplier yang menyediakan bahan baku atau produk ini.
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
            <textarea id="description" name="description" class="form-control" rows="3"><?= e($descVal) ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <a href="<?= e($baseUrl) ?>/public/products/index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/footer.php'; ?>
