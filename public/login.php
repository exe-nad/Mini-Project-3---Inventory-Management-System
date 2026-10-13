<?php
/**
 * public/login.php - Halaman Masuk / Autentikasi Pengguna
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/escape.php';
require_once dirname(__DIR__) . '/helpers/auth.php';

$baseUrl = get_base_url();

if (is_logged_in()) {
    header("Location: {$baseUrl}/public/dashboard.php");
    exit;
}

$error = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submittedToken)) {
        $error = 'Validasi token keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $usernameVal = trim($_POST['username'] ?? '');
        $passwordVal = $_POST['password'] ?? '';

        if (empty($usernameVal) || empty($passwordVal)) {
            $error = 'Mohon isi username dan password lengkap.';
        } else {
            if (attempt_login($usernameVal, $passwordVal)) {
                set_flash('success', 'Selamat datang kembali, ' . e($_SESSION['full_name']) . '!');
                header("Location: {$baseUrl}/public/dashboard.php");
                exit;
            } else {
                $error = 'Kredensial tidak valid! Username atau kata sandi salah.';
            }
        }
    }
}

$pageTitle = 'Masuk ke Sistem';
include dirname(__DIR__) . '/views/header.php';
?>

<div style="max-width: 440px; margin: 3rem auto;">
    <div class="card" style="padding: 2.25rem; border-top: 5px solid var(--primary);">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">🥐</div>
            <h1 style="font-size: 1.5rem; color: var(--text-primary); font-weight: 800;">SweetBakes Studio</h1>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Sistem Manajemen Inventaris & Rantai Pasok</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
            <span>⚠️ <?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form action="" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" 
                       value="<?= e($usernameVal) ?>" required autofocus autocomplete="username"
                       placeholder="Masukkan username Anda">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" class="form-control" 
                       required autocomplete="current-password"
                       placeholder="Masukkan kata sandi">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem; margin-top: 0.5rem;">
                🔐 Masuk ke Sistem
            </button>
        </form>

        <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px dashed var(--border-color); font-size: 0.8rem; color: var(--text-muted);">
            <div style="font-weight: 700; margin-bottom: 0.4rem; color: var(--text-primary);">Akun Demo untuk Pengujian Dosen:</div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span>👑 <strong>Administrator:</strong> <code>admin</code></span>
                <span>Sandi: <code>admin123</code></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>🧑‍🍳 <strong>Staff Gudang:</strong> <code>staff</code></span>
                <span>Sandi: <code>staff123</code></span>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/views/footer.php'; ?>
