<?php
/**
 * public/index.php - Public Entrypoint Redirector
 */
require_once dirname(__DIR__) . '/helpers/auth.php';

$baseUrl = get_base_url();
if (is_logged_in()) {
    header("Location: {$baseUrl}/public/dashboard.php");
} else {
    header("Location: {$baseUrl}/public/login.php");
}
exit;
