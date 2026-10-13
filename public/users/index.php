<?php
/**
 * public/users/index.php - Administrasi Pengguna (Khusus Role Admin)
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 * Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/helpers/escape.php';
require_once dirname(dirname(__DIR__)) . '/helpers/auth.php';

// Aturan Bisnis: Staff tidak boleh mengakses administrasi user (Role-Based Access Control)
require_role('admin');

$db = get_db();
$baseUrl = get_base_url();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = trim($_POST['role'] ?? 'staff');

            if (empty($username) || empty($fullName) || empty($password)) {
                $errors[] = 'Semua field wajib diisi.';
            } elseif (strlen($password) < 6) {
                $errors[] = 'Kata sandi minimal 6 karakter.';
            } else {
                // Cek keunikan username
                $check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
                $check->execute([':u' => $username]);
                if ((int)$check->fetchColumn() > 0) {
                    $errors[] = "Username \"{$username}\" sudah terdaftar.";
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmtIns = $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (:u, :p, :f, :r)");
                    $stmtIns->execute([
                        ':u' => $username,
                        ':p' => $hash,
                        ':f' => $fullName,
                        ':r' => in_array($role, ['admin', 'staff']) ? $role : 'staff'
                    ]);
                    set_flash('success', "Akun pengguna baru \"{$username}\" ({$role}) berhasil dibuat!");
                    header("Location: {$baseUrl}/public/users/index.php");
                    exit;
                }
            }
        } elseif ($action === 'delete') {
            $delId = (int)($_POST['id'] ?? 0);

            // Cegah admin menghapus dirinya sendiri
            if ($delId === (int)$_SESSION['user_id']) {
                set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan!');
            } else {
                // Cek apakah user memiliki riwayat transaksi
                $checkTrx = $db->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE user_id = :uid");
                $checkTrx->execute([':uid' => $delId]);
                $trxCount = (int)$checkTrx->fetchColumn();

                if ($trxCount > 0) {
                    set_flash('danger', "Pengguna ini tidak dapat dihapus karena telah menandatangani {$trxCount} catatan transaksi inventaris.");
                } else {
                    $delStmt = $db->prepare("DELETE FROM users WHERE id = :id");
                    $delStmt->execute([':id' => $delId]);
                    set_flash('success', 'Akun pengguna berhasil dihapus.');
                }
            }
            header("Location: {$baseUrl}/public/users/index.php");
            exit;
        }
    }
}

$users = $db->query("
    SELECT u.id, u.username, u.full_name, u.role, u.created_at,
           (SELECT COUNT(*) FROM inventory_transactions WHERE user_id = u.id) AS activity_count
    FROM users u
    ORDER BY u.role ASC, u.id ASC
")->fetchAll();

$pageTitle = 'Administrasi Pengguna (Admin Only)';
include dirname(dirname(__DIR__)) . '/views/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">👥 Administrasi Pengguna & Role Staff</h1>
        <p class="page-subtitle">Halaman khusus <strong>Administrator</strong> untuk mengelola akun dan hak akses sistem.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
    <!-- Form Tambah User -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem;">➕ Registrasi Pengguna Baru</h2>

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
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label" for="username">Username <span style="color:red">*</span></label>
                <input type="text" id="username" name="username" class="form-control" required placeholder="Misal: nadya_staff">
            </div>

            <div class="form-group">
                <label class="form-label" for="full_name">Nama Lengkap & Gelar <span style="color:red">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="Misal: Siti Nurhaliza">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Kata Sandi Awal <span style="color:red">*</span></label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Minimal 6 karakter">
            </div>

            <div class="form-group">
                <label class="form-label" for="role">Hak Akses / Peran (Role) <span style="color:red">*</span></label>
                <select id="role" name="role" class="form-control" required>
                    <option value="staff">Staff (Akses Inventaris & Transaksi)</option>
                    <option value="admin">Administrator (Akses Penuh Termasuk User Admin)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 0.5rem;">
                💾 Buat Akun Pengguna
            </button>
        </form>
    </div>

    <!-- Tabel Daftar Pengguna -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Username & Nama</th>
                        <th>Peran (Role)</th>
                        <th>Aktivitas Mutasi</th>
                        <th>Terdaftar</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <strong><?= e($u['full_name']) ?></strong>
                            <div style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace;">@<?= e($u['username']) ?></div>
                        </td>
                        <td>
                            <span class="role-tag <?= e($u['role']) ?>"><?= e($u['role']) ?></span>
                        </td>
                        <td>
                            <span class="badge badge-info"><?= number_format($u['activity_count']) ?> mutasi diinput</span>
                        </td>
                        <td style="font-size: 0.82rem; color: var(--text-muted);">
                            <?= format_datetime($u['created_at']) ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if ($u['id'] !== $_SESSION['user_id'] && (int)$u['activity_count'] === 0): ?>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Hapus user @<?= e($u['username']) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?= $u['id'] === $_SESSION['user_id'] ? '(Sesi Aktif)' : '🔒 Memiliki Histori' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/footer.php'; ?>
