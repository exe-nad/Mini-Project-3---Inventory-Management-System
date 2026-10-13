<?php
/**
 * helpers/auth.php - Helper Autentikasi & Otorisasi Berbasis Peran (RBAC)
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/escape.php';
require_once dirname(__DIR__) . '/config/database.php';

/**
 * Mendapatkan base path URL dinamis
 */
function get_base_url(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Mencari posisi folder proyek
    if (preg_match('#^(/[^/]+)#', $script, $matches)) {
        // Jika berjalan di subfolder seperti /Praktikum4_... atau /inventory-app
        if (strpos($script, '/public/') !== false) {
            $parts = explode('/public/', $script);
            return $parts[0];
        }
    }
    return '';
}

/**
 * Cek apakah pengguna sedang login
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Mengambil informasi user aktif dari session
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? 'staff'
    ];
}

/**
 * Cek apakah role pengguna sesuai
 */
function has_role(string $role): bool {
    $user = current_user();
    if (!$user) return false;
    return strtolower($user['role']) === strtolower($role);
}

/**
 * Middleware: Wajib Login
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('danger', 'Akses ditolak! Silakan masuk ke akun Anda terlebih dahulu.');
        $baseUrl = get_base_url();
        header("Location: {$baseUrl}/public/login.php");
        exit;
    }
}

/**
 * Middleware: Wajib Memiliki Role Tertentu (misal: Admin)
 */
function require_role(string $role): void {
    require_login();
    if (!has_role($role)) {
        http_response_code(403);
        $user = current_user();
        include dirname(__DIR__) . '/views/header.php';
        ?>
        <div class="card alert-box" style="border-left: 5px solid #dc3545; margin-top: 30px;">
            <div style="font-size: 2.5rem; color: #dc3545; margin-bottom: 15px;">🚫 403 Akses Ditolak</div>
            <h2 style="color: #b02a37;">Otorisasi Tidak Memadai</h2>
            <p>Maaf, peran Anda saat ini sebagai <strong><?= e(strtoupper($user['role'])) ?></strong> tidak memiliki hak akses untuk membuka halaman administrasi ini.</p>
            <p class="text-muted">Aturan Bisnis: <em>"Staff tidak boleh mengakses administrasi user."</em></p>
            <div style="margin-top: 20px;">
                <a href="<?= e(get_base_url()) ?>/public/dashboard.php" class="btn btn-primary">⬅ Kembali ke Dashboard</a>
            </div>
        </div>
        <?php
        include dirname(__DIR__) . '/views/footer.php';
        exit;
    }
}

/**
 * Proses login dengan verifikasi hash password
 */
function attempt_login(string $username, string $password): bool {
    $db = get_db();
    $stmt = $db->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => trim($username)]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerasi session ID untuk mencegah session fixation
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        return true;
    }

    return false;
}

/**
 * Logout pengguna
 */
function logout(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
