<?php
/**
 * public/suppliers/index.php - CRUD Master Data Supplier & Relasi Produk
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 * Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/helpers/escape.php';
require_once dirname(dirname(__DIR__)) . '/helpers/auth.php';

require_login();

$db = get_db();
$baseUrl = get_base_url();

$errors = [];
$editSupplier = null;
$editId = (int)($_GET['edit_id'] ?? 0);

if ($editId > 0) {
    $stmtFind = $db->prepare("SELECT * FROM suppliers WHERE id = :id");
    $stmtFind->execute([':id' => $editId]);
    $editSupplier = $stmtFind->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $supId      = (int)($_POST['id'] ?? 0);
            $name       = trim($_POST['name'] ?? '');
            $cp         = trim($_POST['contact_person'] ?? '');
            $phone      = trim($_POST['phone'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $address    = trim($_POST['address'] ?? '');

            if (empty($name)) {
                $errors[] = 'Nama supplier wajib diisi.';
            } else {
                if ($supId > 0) {
                    $stmtUpd = $db->prepare("
                        UPDATE suppliers 
                        SET name = :name, contact_person = :cp, phone = :phone, email = :email, address = :addr 
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        ':name'  => $name,
                        ':cp'    => $cp ?: null,
                        ':phone' => $phone ?: null,
                        ':email' => $email ?: null,
                        ':addr'  => $address ?: null,
                        ':id'    => $supId
                    ]);
                    set_flash('success', "Data supplier \"{$name}\" berhasil diperbarui!");
                } else {
                    $stmtIns = $db->prepare("
                        INSERT INTO suppliers (name, contact_person, phone, email, address)
                        VALUES (:name, :cp, :phone, :email, :addr)
                    ");
                    $stmtIns->execute([
                        ':name'  => $name,
                        ':cp'    => $cp ?: null,
                        ':phone' => $phone ?: null,
                        ':email' => $email ?: null,
                        ':addr'  => $address ?: null
                    ]);
                    set_flash('success', "Mitra supplier baru \"{$name}\" berhasil didaftarkan!");
                }
                header("Location: {$baseUrl}/public/suppliers/index.php");
                exit;
            }
        } elseif ($action === 'delete') {
            $supId = (int)($_POST['id'] ?? 0);
            
            // Cek apakah supplier ini pernah tercatat di histori transaksi
            $checkTrx = $db->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE supplier_id = :sid");
            $checkTrx->execute([':sid' => $supId]);
            $countTrx = (int)$checkTrx->fetchColumn();

            if ($countTrx > 0) {
                set_flash('warning', "Supplier ini memiliki {$countTrx} riwayat transaksi. Penghapusan akan memutuskan relasi produk dan mengatur referensi transaksi menjadi arsip.");
            }

            $stmtDel = $db->prepare("DELETE FROM suppliers WHERE id = :id");
            $stmtDel->execute([':id' => $supId]);
            set_flash('success', 'Supplier berhasil dihapus dari sistem.');
            header("Location: {$baseUrl}/public/suppliers/index.php");
            exit;
        }
    }
}

// Ambil Semua Supplier dengan Agregat Produk yang Terhubung (Many-to-Many)
$suppliers = $db->query("
    SELECT s.*, 
           COUNT(DISTINCT ps.product_id) AS total_products_supplied,
           (
               SELECT GROUP_CONCAT(p.name SEPARATOR ', ')
               FROM product_suppliers ps2
               JOIN products p ON ps2.product_id = p.id
               WHERE ps2.supplier_id = s.id
           ) AS supplied_items
    FROM suppliers s
    LEFT JOIN product_suppliers ps ON ps.supplier_id = s.id
    GROUP BY s.id
    ORDER BY s.name ASC
")->fetchAll();

$pageTitle = 'Master Data Mitra Supplier';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🚚 Master Data Mitra Supplier</h1>
        <p class="page-subtitle">Kelola mitra distributor bahan baku dan hubungan Many-to-Many dengan produk etalase.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
    <!-- Form Tambah / Edit Supplier -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem;">
            <?= $editSupplier ? '✏️ Edit Data Supplier' : '➕ Tambah Mitra Supplier' ?>
        </h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1rem;">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editSupplier ? (int)$editSupplier['id'] : 0 ?>">

            <div class="form-group">
                <label class="form-label" for="name">Nama Perusahaan / Supplier <span style="color:red">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required
                       value="<?= e($editSupplier['name'] ?? '') ?>" placeholder="Misal: PT Cacao Indonesia">
            </div>

            <div class="form-group">
                <label class="form-label" for="contact_person">Contact Person (PIC)</label>
                <input type="text" id="contact_person" name="contact_person" class="form-control"
                       value="<?= e($editSupplier['contact_person'] ?? '') ?>" placeholder="Nama penanggung jawab">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label class="form-label" for="phone">No. Telepon / WhatsApp</label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="<?= e($editSupplier['phone'] ?? '') ?>" placeholder="0812...">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($editSupplier['email'] ?? '') ?>" placeholder="sales@...">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="address">Alamat Gudang / Kantor</label>
                <textarea id="address" name="address" class="form-control" rows="2"
                          placeholder="Alamat distributor..."><?= e($editSupplier['address'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">
                    <?= $editSupplier ? '💾 Simpan Perubahan' : '➕ Tambah Supplier' ?>
                </button>
                <?php if ($editSupplier): ?>
                    <a href="<?= e($baseUrl) ?>/public/suppliers/index.php" class="btn btn-secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabel Daftar Supplier -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Supplier</th>
                        <th>Kontak PIC</th>
                        <th>Bahan / Produk Terpasok (M:N)</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 2rem;">Belum ada supplier terdaftar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td>
                                <strong><?= e($s['name']) ?></strong>
                                <?php if (!empty($s['address'])): ?>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);"><?= e($s['address']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <div>👤 <?= e($s['contact_person'] ?? '-') ?></div>
                                <div style="color: var(--text-muted);">📞 <?= e($s['phone'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="badge badge-info" style="margin-bottom: 4px;">
                                    <?= number_format($s['total_products_supplied']) ?> produk
                                </span>
                                <?php if (!empty($s['supplied_items'])): ?>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= e($s['supplied_items']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="?edit_id=<?= (int)$s['id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Hapus supplier ini?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/footer.php'; ?>
