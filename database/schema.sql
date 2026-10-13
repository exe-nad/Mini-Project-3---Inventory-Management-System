-- =====================================================================
-- Mini Project 3: Relational Database + Advanced PHP
-- Inventory Management System: SweetBakes Bakery & Pastry Studio
-- Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `store_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `store_db`;

-- 1. Tabel Users (Authentication & Authorization Role)
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `product_suppliers`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Categories (Master Data Kategori)
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Suppliers (Master Data Supplier)
CREATE TABLE `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Products (Master Data Produk dengan Relasi Kategori & SKU Unik)
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `category_id` INT NOT NULL,
    `price` DECIMAL(12, 2) NOT NULL CHECK (`price` >= 0),
    `stock` INT NOT NULL DEFAULT 0 CHECK (`stock` >= 0),
    `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Many-to-Many: Relasi Produk dan Supplier
CREATE TABLE `product_suppliers` (
    `product_id` INT NOT NULL,
    `supplier_id` INT NOT NULL,
    `supply_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `lead_time_days` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`product_id`, `supplier_id`),
    CONSTRAINT `fk_ps_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_ps_supplier` FOREIGN KEY (`supplier_id`) 
        REFERENCES `suppliers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel Inventory Transactions (Transaksi Masuk / Keluar & Histori)
CREATE TABLE `inventory_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(60) NOT NULL UNIQUE,
    `product_id` INT NOT NULL,
    `supplier_id` INT NULL,
    `user_id` INT NOT NULL,
    `type` ENUM('IN', 'OUT') NOT NULL,
    `quantity` INT NOT NULL CHECK (`quantity` > 0),
    `stock_before` INT NOT NULL,
    `stock_after` INT NOT NULL,
    `notes` TEXT NULL,
    `transaction_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_trx_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_trx_supplier` FOREIGN KEY (`supplier_id`) 
        REFERENCES `suppliers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_trx_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA AWAL (SweetBakes Bakery & Pastry Studio)
-- =====================================================================

-- Users (admin: admin123, staff: staff123)
-- Hash dibuat dengan BCRYPT
INSERT INTO `users` (`username`, `password`, `full_name`, `role`) VALUES
('admin', '$2y$10$CIIZVsGL.w9IUNmKe8NXfeCHvhwYs/4CC3DSKZVgI9iajirdjZzYC', 'Nadya Salsabila Zahra (Administrator)', 'admin'),
('staff', '$2y$10$s3deMXBCDTugC2wYKAapLecnUkUS5rd/VpLG5EpVxwzR8qCyZOh0q', 'Budi Santoso (Staff Gudang & Etalase)', 'staff');

-- Categories
INSERT INTO `categories` (`name`, `description`) VALUES
('Pastry', 'Aneka pastry panggang berlapis renyah seperti croissant dan danish.'),
('Artisan Cake', 'Kue bolu premium dan cheesecake untuk perayaan dan kafe.'),
('Cupcake & Muffin', 'Kue mungil lembut dengan aneka topping dan isian buah.'),
('Cookies & Treats', 'Kue kering artisan renyah di luar dan lumer di dalam.'),
('Bread & Loaf', 'Roti gandum ragi alami sourdough dan brioche mentega.');

-- Suppliers
INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`, `address`) VALUES
('PT Parisienne Dairy & Butter', 'Jean Pierre', '081234567890', 'contact@parisienne.co.id', 'Kawasan Industri Pulo Gadung, Jakarta Timur'),
('Belcolade Cacao Indonesia', 'Rian Hidayat', '081987654321', 'sales@belcolade.id', 'Jl. Gatot Subroto No. 45, Bandung'),
('Kyoto Uji Tea Importir', 'Meilani Wijaya', '081345678912', 'info@kyotouji.com', 'Ruko Central Park No. 12, Surabaya'),
('Sentosa Flour Mill & Grain', 'Hendra Tan', '081122334455', 'order@sentosaflour.co.id', 'Kawasan Industri Cikarang, Bekasi');

-- Products
INSERT INTO `products` (`sku`, `name`, `category_id`, `price`, `stock`, `unit`, `description`) VALUES
('SWB-PST-001', 'Butter Croissant Parisienne', 1, 28000.00, 24, 'pcs', 'Croissant mentega Prancis dengan tekstur renyah dan rongga berlapis sempurna.'),
('SWB-PST-002', 'Pain au Chocolat Belgia', 1, 34000.00, 18, 'pcs', 'Pastry gulung berisi lelehan cokelat hitam murni Belgia 70%.'),
('SWB-CAK-001', 'Signature Dark Chocolate Fudge Cake', 2, 245000.00, 6, 'slice', 'Kue bolu cokelat lembut berlapis ganache dark chocolate premium.'),
('SWB-CAK-002', 'Uji Matcha Basque Cheesecake', 2, 275000.00, 5, 'slice', 'Cheesecake panggang beraroma teh hijau Uji Kyoto dengan lumeran krim lembut.'),
('SWB-CUP-001', 'Red Velvet Cream Cheese Cupcake', 3, 32000.00, 30, 'pcs', 'Cupcake merah beludru klasik berpadu frosting cream cheese vanila lembut.'),
('SWB-CUP-002', 'Blueberry Crumble Muffin', 3, 29000.00, 20, 'pcs', 'Muffin vanila isi selai bluberi segar dengan taburan remah mentega garing.'),
('SWB-COO-001', 'Double Choco Walnut Soft Cookie', 4, 22000.00, 35, 'pcs', 'Kue kering empuk berlumur cokelat leleh dan renyahnya kacang walnut.'),
('SWB-BRD-001', 'Artisan French Sourdough Loaf', 5, 48000.00, 12, 'loaf', 'Roti gandum ragi alami dengan kulit renyah dan tekstur dalam kenyal gurih.');

-- Many-to-Many: Product Suppliers
INSERT INTO `product_suppliers` (`product_id`, `supplier_id`, `supply_price`, `lead_time_days`) VALUES
(1, 1, 15000.00, 2),
(1, 4, 6000.00, 3),
(2, 1, 16000.00, 2),
(2, 2, 12000.00, 4),
(3, 2, 120000.00, 5),
(4, 3, 140000.00, 7),
(5, 1, 14000.00, 2),
(6, 4, 11000.00, 3),
(7, 2, 9000.00, 4),
(8, 4, 18000.00, 2);

-- Initial Transaction History (Barang Masuk & Keluar)
INSERT INTO `inventory_transactions` (`reference_no`, `product_id`, `supplier_id`, `user_id`, `type`, `quantity`, `stock_before`, `stock_after`, `notes`, `transaction_date`) VALUES
('TRX-IN-20261010-001', 1, 1, 1, 'IN', 30, 0, 30, 'Penerimaan batch fresh bake mentega Parisienne', '2026-10-10 08:30:00'),
('TRX-OUT-20261010-002', 1, NULL, 2, 'OUT', 6, 30, 24, 'Distribusi penjualan pagi etalase kafe', '2026-10-10 11:15:00'),
('TRX-IN-20261011-003', 2, 2, 1, 'IN', 25, 0, 25, 'Penerimaan cokelat Belgia batch baru', '2026-10-11 09:00:00'),
('TRX-OUT-20261011-004', 2, NULL, 2, 'OUT', 7, 25, 18, 'Penjualan weekend rush counter utama', '2026-10-11 15:45:00'),
('TRX-IN-20261012-005', 4, 3, 1, 'IN', 8, 0, 8, 'Produksi batch matcha cheesecake Kyoto', '2026-10-12 10:00:00'),
('TRX-OUT-20261012-006', 4, NULL, 2, 'OUT', 3, 8, 5, 'Pesanan pre-order ulang tahun pelanggan VIP', '2026-10-12 16:20:00');
