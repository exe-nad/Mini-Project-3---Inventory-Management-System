<?php
/**
 * config/database.php - Koneksi Database PDO Multi-Driver (MySQL + SQLite Fallback)
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 * Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inisialisasi CSRF Token per Sesi
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'store_db';
$db_user = 'root';
$db_pass = '';

$pdo = null;

try {
    // 1. Coba koneksi ke MySQL
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    try {
        // Coba koneksi root MySQL untuk membuat database store_db jika belum ada
        $rootDsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
        $tempPdo = new PDO($rootDsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

        $pdo = new PDO($dsn, $db_user, $db_pass, $options);

        // Eksekusi skema awal jika tabel belum ada
        $schemaFile = dirname(__DIR__) . '/database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);
        }
    } catch (PDOException $mysqlEx) {
        // 2. Fallback otomatis ke SQLite jika MySQL XAMPP belum dijalankan
        $sqlitePath = dirname(__DIR__) . '/database/store_db.sqlite';
        try {
            $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("PRAGMA foreign_keys = ON;");

            // Buat skema SQLite jika belum ada
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    password TEXT NOT NULL,
                    full_name TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'staff',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS suppliers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    contact_person TEXT,
                    phone TEXT,
                    email TEXT,
                    address TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sku TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    category_id INTEGER NOT NULL,
                    price REAL NOT NULL CHECK(price >= 0),
                    stock INTEGER NOT NULL DEFAULT 0 CHECK(stock >= 0),
                    unit TEXT NOT NULL DEFAULT 'pcs',
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT
                );

                CREATE TABLE IF NOT EXISTS product_suppliers (
                    product_id INTEGER NOT NULL,
                    supplier_id INTEGER NOT NULL,
                    supply_price REAL NOT NULL DEFAULT 0.0,
                    lead_time_days INTEGER NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (product_id, supplier_id),
                    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE,
                    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS inventory_transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    reference_no TEXT NOT NULL UNIQUE,
                    product_id INTEGER NOT NULL,
                    supplier_id INTEGER,
                    user_id INTEGER NOT NULL,
                    type TEXT NOT NULL,
                    quantity INTEGER NOT NULL CHECK(quantity > 0),
                    stock_before INTEGER NOT NULL,
                    stock_after INTEGER NOT NULL,
                    notes TEXT,
                    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
                    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE SET NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
                );
            ");

            // Seed user jika masih kosong
            $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($userCount === 0) {
                $seedUsers = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                $seedUsers->execute(['admin', '$2y$10$CIIZVsGL.w9IUNmKe8NXfeCHvhwYs/4CC3DSKZVgI9iajirdjZzYC', 'Nadya Salsabila Zahra (Administrator)', 'admin']);
                $seedUsers->execute(['staff', '$2y$10$s3deMXBCDTugC2wYKAapLecnUkUS5rd/VpLG5EpVxwzR8qCyZOh0q', 'Budi Santoso (Staff Gudang & Etalase)', 'staff']);
            }

            // Seed categories jika kosong
            $catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            if ($catCount === 0) {
                $seedCats = [
                    ['Pastry', 'Aneka pastry panggang berlapis renyah seperti croissant dan danish.'],
                    ['Artisan Cake', 'Kue bolu premium dan cheesecake untuk perayaan dan kafe.'],
                    ['Cupcake & Muffin', 'Kue mungil lembut dengan aneka topping dan isian buah.'],
                    ['Cookies & Treats', 'Kue kering artisan renyah di luar dan lumer di dalam.'],
                    ['Bread & Loaf', 'Roti gandum ragi alami sourdough dan brioche mentega.']
                ];
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                foreach ($seedCats as $cat) {
                    $stmt->execute($cat);
                }
            }

            // Seed suppliers jika kosong
            $supCount = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
            if ($supCount === 0) {
                $seedSups = [
                    ['PT Parisienne Dairy & Butter', 'Jean Pierre', '081234567890', 'contact@parisienne.co.id', 'Kawasan Industri Pulo Gadung, Jakarta Timur'],
                    ['Belcolade Cacao Indonesia', 'Rian Hidayat', '081987654321', 'sales@belcolade.id', 'Jl. Gatot Subroto No. 45, Bandung'],
                    ['Kyoto Uji Tea Importir', 'Meilani Wijaya', '081345678912', 'info@kyotouji.com', 'Ruko Central Park No. 12, Surabaya'],
                    ['Sentosa Flour Mill & Grain', 'Hendra Tan', '081122334455', 'order@sentosaflour.co.id', 'Kawasan Industri Cikarang, Bekasi']
                ];
                $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
                foreach ($seedSups as $sup) {
                    $stmt->execute($sup);
                }
            }

            // Seed products jika kosong
            $prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            if ($prodCount === 0) {
                $seedProds = [
                    ['SWB-PST-001', 'Butter Croissant Parisienne', 1, 28000.00, 24, 'pcs', 'Croissant mentega Prancis dengan tekstur renyah dan rongga berlapis sempurna.'],
                    ['SWB-PST-002', 'Pain au Chocolat Belgia', 1, 34000.00, 18, 'pcs', 'Pastry gulung berisi lelehan cokelat hitam murni Belgia 70%.'],
                    ['SWB-CAK-001', 'Signature Dark Chocolate Fudge Cake', 2, 245000.00, 6, 'slice', 'Kue bolu cokelat lembut berlapis ganache dark chocolate premium.'],
                    ['SWB-CAK-002', 'Uji Matcha Basque Cheesecake', 2, 275000.00, 5, 'slice', 'Cheesecake panggang beraroma teh hijau Uji Kyoto dengan lumeran krim lembut.'],
                    ['SWB-CUP-001', 'Red Velvet Cream Cheese Cupcake', 3, 32000.00, 30, 'pcs', 'Cupcake merah beludru klasik berpadu frosting cream cheese vanila lembut.'],
                    ['SWB-CUP-002', 'Blueberry Crumble Muffin', 3, 29000.00, 20, 'pcs', 'Muffin vanila isi selai bluberi segar dengan taburan remah mentega garing.'],
                    ['SWB-COO-001', 'Double Choco Walnut Soft Cookie', 4, 22000.00, 35, 'pcs', 'Kue kering empuk berlumur cokelat leleh dan renyahnya kacang walnut.'],
                    ['SWB-BRD-001', 'Artisan French Sourdough Loaf', 5, 48000.00, 12, 'loaf', 'Roti gandum ragi alami dengan kulit renyah dan tekstur dalam kenyal gurih.']
                ];
                $stmt = $pdo->prepare("INSERT INTO products (sku, name, category_id, price, stock, unit, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($seedProds as $prod) {
                    $stmt->execute($prod);
                }

                // Seed product_suppliers
                $seedPS = [
                    [1, 1, 15000.00, 2], [1, 4, 6000.00, 3],
                    [2, 1, 16000.00, 2], [2, 2, 12000.00, 4],
                    [3, 2, 120000.00, 5],
                    [4, 3, 140000.00, 7],
                    [5, 1, 14000.00, 2],
                    [6, 4, 11000.00, 3],
                    [7, 2, 9000.00, 4],
                    [8, 4, 18000.00, 2]
                ];
                $stmtPS = $pdo->prepare("INSERT INTO product_suppliers (product_id, supplier_id, supply_price, lead_time_days) VALUES (?, ?, ?, ?)");
                foreach ($seedPS as $ps) {
                    $stmtPS->execute($ps);
                }

                // Seed transactions
                $seedTrx = [
                    ['TRX-IN-20261010-001', 1, 1, 1, 'IN', 30, 0, 30, 'Penerimaan batch fresh bake mentega Parisienne', '2026-10-10 08:30:00'],
                    ['TRX-OUT-20261010-002', 1, null, 2, 'OUT', 6, 30, 24, 'Distribusi penjualan pagi etalase kafe', '2026-10-10 11:15:00'],
                    ['TRX-IN-20261011-003', 2, 2, 1, 'IN', 25, 0, 25, 'Penerimaan cokelat Belgia batch baru', '2026-10-11 09:00:00'],
                    ['TRX-OUT-20261011-004', 2, null, 2, 'OUT', 7, 25, 18, 'Penjualan weekend rush counter utama', '2026-10-11 15:45:00'],
                    ['TRX-IN-20261012-005', 4, 3, 1, 'IN', 8, 0, 8, 'Produksi batch matcha cheesecake Kyoto', '2026-10-12 10:00:00'],
                    ['TRX-OUT-20261012-006', 4, null, 2, 'OUT', 3, 8, 5, 'Pesanan pre-order ulang tahun pelanggan VIP', '2026-10-12 16:20:00']
                ];
                $stmtTrx = $pdo->prepare("INSERT INTO inventory_transactions (reference_no, product_id, supplier_id, user_id, type, quantity, stock_before, stock_after, notes, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($seedTrx as $trx) {
                    $stmtTrx->execute($trx);
                }
            }
        } catch (Exception $sqliteEx) {
            die("Gagal inisialisasi koneksi basis data: " . $sqliteEx->getMessage());
        }
    }
}

/**
 * Mengembalikan objek PDO aktif
 */
function get_db(): PDO {
    global $pdo;
    return $pdo;
}
