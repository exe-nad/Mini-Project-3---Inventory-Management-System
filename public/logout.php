<?php
/**
 * public/logout.php - Logout Handler
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

logout();

$baseUrl = get_base_url();
header("Location: {$baseUrl}/public/login.php");
exit;
