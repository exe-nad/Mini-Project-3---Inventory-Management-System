<?php
/**
 * public/categories/index.php - CRUD Master Data Kategori
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
$editCategory = null;
$editId = (int)($_GET['edit_id'] ?? 0);

if ($editId > 0) {
    $stmtFind = $db->prepare("SELECT * FROM categories WHERE id = :id");
    $stmtFind->execute([':id' => $editId]);
    $editCategory = $stmtFind->fetch();
}

// Handle Form Submission (Create / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $catId   = (int)($_POST['id'] ?? 0);
            $catName = trim($_POST['name'] ?? '');
            $catDesc = trim($_POST['description'] ?? '');

            if (empty($catName)) {
                $errors[] = 'Nama kategori wajib diisi.';
            } else {
                // Cek keunikan nama kategori
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM categories WHERE name = :name AND id != :id");
                $stmtCheck->execute([':name' => $catName, ':id' => $catId]);
                if ((int)$stmtCheck->fetchColumn() > 0) {
                    $errors[] = "Kategori \"{$catName}\" sudah ada.";
                } else {
                    if ($catId > 0) {
                        // UPDATE
                        $stmtUpd = $db->prepare("UPDATE categories SET name = :name, description = :desc WHERE id = :id");
                        $stmtUpd->execute([':name' => $catName, ':desc' => $catDesc ?: null, ':id' => $catId]);
                        set_flash('success', "Kategori \"{$catName}\" berhasil diperbarui!");
                    } else {
                        // INSERT
                        $stmtIns = $db->prepare("INSERT INTO categories (name, description) VALUES (:name, :desc)");
                        $stmtIns->execute([':name' => $catName, ':desc' => $catDesc ?: null]);
                        set_flash('success', "Kategori baru \"{$catName}\" berhasil ditambahkan!");
                    }
                    header("Location: {$baseUrl}/public/categories/index.php");
                    exit;
                }
            }
        } elseif ($action === 'delete') {
            $catId = (int)($_POST['id'] ?? 0);
            
            // Cek apakah ada produk yang terhubung (Foreign key constraint check)
            $checkProd = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = :cid");
            $checkProd->execute([':cid' => $catId]);
            $countProd = (int)$checkProd->fetchColumn();

            if ($countProd > 0) {
                set_flash('danger', "Kategori tidak dapat dihapus karena masih digunakan oleh {$countProd} produk etalase!");
            } else {
                $delStmt = $db->prepare("DELETE FROM categories WHERE id = :id");
                $delStmt->execute([':id' => $catId]);
                set_flash('success', 'Kategori berhasil dihapus.');
            }
            header("Location: {$baseUrl}/public/categories/index.php");
            exit;
        }
    }
}

// Ambil Semua Kategori Beserta Jumlah Produknya
$categories = $db->query("
    SELECT c.id, c.name, c.description, c.created_at,
           COUNT(p.id) AS total_products
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.description, c.created_at
    ORDER BY c.name ASC
")->fetchAll();

$pageTitle = 'Master Data Kategori';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🏷️ Master Data Kategori Menu</h1>
        <p class="page-subtitle">Pengelompokan varian pastry, artisan cake, dan roti studio.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
    <!-- Form Tambah / Edit Kategori -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem;">
            <?= $editCategory ? '✏️ Edit Kategori' : '➕ Tambah Kategori Baru' ?>
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
            <input type="hidden" name="id" value="<?= $editCategory ? (int)$editCategory['id'] : 0 ?>">

            <div class="form-group">
                <label class="form-label" for="name">Nama Kategori <span style="color:red">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required
                       value="<?= e($editCategory['name'] ?? '') ?>" placeholder="Misal: Viennoiserie">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Deskripsi</label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="Keterangan kategori..."><?= e($editCategory['description'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">
                    <?= $editCategory ? '💾 Simpan Perubahan' : '➕ Tambah' ?>
                </button>
                <?php if ($editCategory): ?>
                    <a href="<?= e($baseUrl) ?>/public/categories/index.php" class="btn btn-secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabel Daftar Kategori -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th>Total Produk</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 2rem;">Belum ada kategori.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><strong style="color: var(--primary);"><?= e($cat['name']) ?></strong></td>
                            <td style="font-size: 0.85rem; color: var(--text-muted); max-width: 250px;">
                                <?= !empty($cat['description']) ? e($cat['description']) : '-' ?>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?= number_format($cat['total_products']) ?> menu</span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="?edit_id=<?= (int)$cat['id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                <?php if ((int)$cat['total_products'] === 0): ?>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Hapus kategori ini?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled title="Terkunci: Masih memiliki produk terkait" style="opacity: 0.5; cursor: not-allowed;">🔒</button>
                                <?php endif; ?>
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
