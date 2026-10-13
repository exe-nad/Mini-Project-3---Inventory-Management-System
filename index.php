<?php
/**
 * index.php - Root Entrypoint Redirector
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 */

require_once __DIR__ . '/helpers/auth.php';

$baseUrl = get_base_url();
if (is_logged_in()) {
    header("Location: {$baseUrl}/public/dashboard.php");
} else {
    header("Location: {$baseUrl}/public/login.php");
}
exit;
