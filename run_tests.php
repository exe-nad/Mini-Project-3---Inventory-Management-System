<?php
/**
 * run_tests.php - Automated Test Suite & Verifikasi Rubrik Penilaian
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 * Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/escape.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/validation.php';

echo "=========================================================================\n";
echo "🧪 SweetBakes Mini Project 3: Automated Test Suite & Rubrik Verifikasi\n";
echo "Mahasiswa : Nadya Salsabila Zahra (NIM: 250180031)\n";
echo "Waktu Uji : " . date('Y-m-d H:i:s') . "\n";
echo "=========================================================================\n\n";

$db = get_db();
$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] ✅ " . $description . "\n";
        $passed++;
    } else {
        echo " [FAIL] ❌ " . $description . "\n";
        $failed++;
    }
}

// 1. Uji Database & Relasi (Tabel Users, Categories, Suppliers, Products, Product_Suppliers, Transactions)
$tables = ['users', 'categories', 'suppliers', 'products', 'product_suppliers', 'inventory_transactions'];
foreach ($tables as $tbl) {
    $count = $db->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
    assert_test("Tabel {$tbl} ada dan dapat diakses (Total records: {$count})", $count !== false);
}

// 2. Uji Autentikasi (Password Hashing BCRYPT)
assert_test("Verifikasi password admin ('admin123')", attempt_login('admin', 'admin123'));
assert_test("Verifikasi password staff ('staff123')", attempt_login('staff', 'staff123'));
assert_test("Penolakan kredensial salah ('wrongpass')", !attempt_login('admin', 'wrongpass'));

// 3. Uji Otorisasi (RBAC Role Admin vs Staff)
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
assert_test("Role admin lolos pemeriksaan has_role('admin')", has_role('admin'));

$_SESSION['role'] = 'staff';
assert_test("Role staff gagal pada has_role('admin')", !has_role('admin'));
assert_test("Role staff lolos pada has_role('staff')", has_role('staff'));

// 4. Uji Many-to-Many Relasi Produk & Supplier
$stmtPS = $db->query("
    SELECT p.name AS product_name, s.name AS supplier_name
    FROM product_suppliers ps
    JOIN products p ON ps.product_id = p.id
    JOIN suppliers s ON ps.supplier_id = s.id
    LIMIT 1
");
$psRow = $stmtPS->fetch();
assert_test("Relasi Many-to-Many (Pivot product_suppliers) berjalan valid: {$psRow['product_name']} dipasok {$psRow['supplier_name']}", !empty($psRow));

// 5. Uji Business Rule: Keunikan SKU
assert_test("SKU 'SWB-PST-001' terdeteksi sudah ada (tidak unik untuk create)", !is_sku_unique('SWB-PST-001'));
assert_test("SKU 'SWB-PST-001' unik jika mengecualikan ID produknya sendiri (update)", is_sku_unique('SWB-PST-001', 1));
assert_test("SKU baru 'SWB-NEW-999' terdeteksi unik", is_sku_unique('SWB-NEW-999'));

// 6. Uji Business Rule: Harga dan Kuantitas Non-Negatif
assert_test("Validasi harga positif (35000)", validate_price(35000));
assert_test("Validasi harga nol (0)", validate_price(0));
assert_test("Penolakan harga negatif (-15000)", !validate_price(-15000));
assert_test("Validasi kuantitas positif (5)", validate_quantity(5));
assert_test("Penolakan kuantitas 0 atau negatif (-2)", !validate_quantity(-2) && !validate_quantity(0));

// 7. Uji Business Rule: Barang Keluar Tidak Melebihi Stok & Stok Tidak Negatif
$prod1 = $db->query("SELECT stock FROM products WHERE id = 1")->fetch();
$stock1 = (int)$prod1['stock'];
$checkExcess = can_fulfill_stock_out(1, $stock1 + 999);
assert_test("Penolakan barang keluar melebihi stok (minta {$stock1}+999 dari {$stock1})", !$checkExcess['allowed']);
$checkOk = can_fulfill_stock_out(1, 1);
assert_test("Penerimaan barang keluar dalam batas stok (minta 1 dari {$stock1})", $checkOk['allowed']);

// 8. Uji Business Rule: Produk yang Memiliki Histori Tidak Boleh Dihapus
assert_test("Produk ID 1 memiliki riwayat mutasi (product_has_history)", product_has_history(1));

// 9. Uji Whitelist Sorting (Anti-SQLi Order By)
$allowed = ['sku', 'name', 'price', 'stock', 'created_at'];
assert_test("Whitelist sorting kolom valid ('name')", validate_sort_column('name', $allowed) === 'name');
assert_test("Whitelist sorting menolak kolom berbahaya ('1; DROP TABLE')", validate_sort_column('1; DROP TABLE', $allowed, 'id') === 'id');
assert_test("Whitelist sorting direction ('ASC')", validate_sort_direction('ASC') === 'ASC');
assert_test("Whitelist sorting direction ('SLEEP(5)') fallback ke ASC", validate_sort_direction('SLEEP(5)') === 'ASC');

// 10. Uji Transaksi Commit / Rollback (Atomicity)
try {
    $db->beginTransaction();
    $currStock = (int)$db->query("SELECT stock FROM products WHERE id = 1")->fetchColumn();
    $db->exec("UPDATE products SET stock = stock + 10 WHERE id = 1");
    // Batalkan transaksi
    $db->rollBack();
    $afterRollback = (int)$db->query("SELECT stock FROM products WHERE id = 1")->fetchColumn();
    assert_test("Transaksi Rollback berhasil mengembalikan saldo stok semula ({$currStock} === {$afterRollback})", $currStock === $afterRollback);
} catch (Exception $e) {
    assert_test("Uji Transaksi rollback gagal dieksekusi", false);
}

// 11. Uji Anti-XSS Escaping
$xss = "<script>alert('xss')</script>";
assert_test("Output escaping htmlspecialchars: e(\$xss)", e($xss) === "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;");

// 12. Uji Laporan Agregat Dashboard
$totalValuation = (float)$db->query("SELECT SUM(stock * price) FROM products")->fetchColumn();
assert_test("Perhitungan nilai agregat valuasi etalase toko bernilai valid (> 0): Rp " . number_format($totalValuation), $totalValuation > 0);

echo "\n=========================================================================\n";
echo "🏁 Ringkasan Pengujian Sistem:\n";
echo "Total Lulus : {$passed}\n";
echo "Total Gagal : {$failed}\n";
echo "Hasil Akhir : " . ($failed === 0 ? "100% SEMUA PENGUJIAN LULUS! SIAP DIKUMPULKAN 🌟" : "PERIKSA KEMBALI KESALAHAN ❌") . "\n";
echo "=========================================================================\n";
