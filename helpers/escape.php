<?php
/**
 * helpers/escape.php - Helper Keamanan Output Escaping & Format Tampilan
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 */

/**
 * Anti-XSS Context-Aware Escaping
 */
function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format Angka ke Standar Rupiah Indonesia
 */
function format_rupiah($angka): string {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Format Tanggal & Waktu Bahasa Indonesia
 */
function format_datetime(?string $datetime): string {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    if (!$time) return e($datetime);
    return date('d M Y, H:i', $time);
}

/**
 * Mengambil CSRF token dari session
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render input hidden CSRF token untuk form HTML
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validasi token CSRF anti timing-attack
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Helper Flash Message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
