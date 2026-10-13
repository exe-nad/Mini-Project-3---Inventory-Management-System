<?php
/**
 * helpers/validation.php - Validasi Aturan Bisnis Server-Side
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 */

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Cek apakah SKU unik di tabel products (dapat mengecualikan ID tertentu saat update)
 */
function is_sku_unique(string $sku, ?int $excludeProductId = null): bool {
    $db = get_db();
    $sql = "SELECT COUNT(*) FROM products WHERE sku = :sku";
    $params = [':sku' => trim($sku)];

    if ($excludeProductId !== null) {
        $sql .= " AND id != :id";
        $params[':id'] = $excludeProductId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() === 0;
}

/**
 * Validasi harga non-negatif
 */
function validate_price($price): bool {
    return is_numeric($price) && (float)$price >= 0;
}

/**
 * Validasi kuantitas transaksi harus bilangan bulat positif (> 0)
 */
function validate_quantity($qty): bool {
    return is_numeric($qty) && (int)$qty > 0 && floor((float)$qty) == (float)$qty;
}

/**
 * Cek apakah barang keluar melebihi stok yang tersedia
 */
function can_fulfill_stock_out(int $productId, int $requestedQty): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT id, name, sku, stock FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        return ['allowed' => false, 'error' => 'Produk tidak ditemukan di database.', 'current_stock' => 0];
    }

    $currentStock = (int)$product['stock'];
    if ($requestedQty > $currentStock) {
        return [
            'allowed' => false,
            'error' => "Jumlah barang keluar ({$requestedQty}) melebihi stok saat ini ({$currentStock}) untuk produk \"{$product['name']}\".",
            'current_stock' => $currentStock,
            'product' => $product
        ];
    }

    return ['allowed' => true, 'current_stock' => $currentStock, 'product' => $product];
}

/**
 * Cek apakah produk memiliki riwayat transaksi di inventory_transactions
 * (Aturan Bisnis: Produk yang memiliki histori tidak boleh dihapus sembarangan)
 */
function product_has_history(int $productId): bool {
    $db = get_db();
    $stmt = $db->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE product_id = :id");
    $stmt->execute([':id' => $productId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Whitelist kolom sorting untuk mencegah SQL Injection via ORDER BY
 */
function validate_sort_column(?string $column, array $allowedColumns, string $default = 'id'): string {
    if ($column !== null && in_array($column, $allowedColumns, true)) {
        return $column;
    }
    return $default;
}

/**
 * Whitelist arah sorting (ASC atau DESC)
 */
function validate_sort_direction(?string $direction, string $default = 'ASC'): string {
    $dir = strtoupper(trim((string)$direction));
    return ($dir === 'DESC') ? 'DESC' : 'ASC';
}
