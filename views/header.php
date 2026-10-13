<?php
/**
 * views/header.php - Template Header & Navigasi Aplikasi
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 * Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/helpers/escape.php';
require_once dirname(__DIR__) . '/helpers/auth.php';

$currentUser = current_user();
$base = get_base_url();
$currentPage = $_SERVER['SCRIPT_NAME'] ?? '';
$pageTitle = $pageTitle ?? 'SweetBakes Studio';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — SweetBakes Inventory</title>
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="<?= e($base) ?>/public/dashboard.php" class="brand">
            <span style="font-size: 1.5rem;">🥐</span>
            <span>SweetBakes</span>
            <span class="badge-logo">Inventory IMS</span>
        </a>

        <?php if ($currentUser): ?>
        <ul class="nav-menu">
            <li><a href="<?= e($base) ?>/public/dashboard.php" class="nav-link <?= strpos($currentPage, 'dashboard.php') !== false ? 'active' : '' ?>">📊 Dashboard</a></li>
            <li><a href="<?= e($base) ?>/public/products/index.php" class="nav-link <?= strpos($currentPage, '/products/') !== false ? 'active' : '' ?>">🍞 Produk</a></li>
            <li><a href="<?= e($base) ?>/public/categories/index.php" class="nav-link <?= strpos($currentPage, '/categories/') !== false ? 'active' : '' ?>">🏷️ Kategori</a></li>
            <li><a href="<?= e($base) ?>/public/suppliers/index.php" class="nav-link <?= strpos($currentPage, '/suppliers/') !== false ? 'active' : '' ?>">🚚 Supplier</a></li>
            <li><a href="<?= e($base) ?>/public/stock/index.php" class="nav-link <?= strpos($currentPage, '/stock/') !== false ? 'active' : '' ?>">📦 Transaksi Stok</a></li>
            
            <?php if (has_role('admin')): ?>
            <li><a href="<?= e($base) ?>/public/users/index.php" class="nav-link <?= strpos($currentPage, '/users/') !== false ? 'active' : '' ?>">👥 Kelola User</a></li>
            <?php endif; ?>
        </ul>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="user-badge">
                <span>👤 <?= e($currentUser['full_name']) ?></span>
                <span class="role-tag <?= e($currentUser['role']) ?>"><?= e($currentUser['role']) ?></span>
            </div>
            <button type="button" class="theme-btn" id="themeToggle" title="Ganti Mode Tampilan">🌙</button>
            <a href="<?= e($base) ?>/public/logout.php" class="btn btn-secondary btn-sm" onclick="return confirm('Apakah Anda yakin ingin keluar?')">🚪 Keluar</a>
        </div>
        <?php else: ?>
        <div>
            <button type="button" class="theme-btn" id="themeToggle" title="Ganti Mode Tampilan">🌙</button>
        </div>
        <?php endif; ?>
    </nav>

    <main class="container">
        <?php
        $flash = get_flash();
        if ($flash):
        ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
            <span><?= e($flash['message']) ?></span>
            <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;cursor:pointer;font-weight:bold;font-size:1.1rem;color:inherit;">&times;</button>
        </div>
        <?php endif; ?>
