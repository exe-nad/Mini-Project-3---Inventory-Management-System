# 🥐 SweetBakes: Artisan Inventory Management System (IMS)

<div align="center">

![PHP Native](https://img.shields.io/badge/PHP-8.x%20Native-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL PDO](https://img.shields.io/badge/MySQL-PDO%20Relational-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![RBAC Auth](https://img.shields.io/badge/RBAC-Admin%20%7C%20Staff-orange?style=for-the-badge&logo=auth0&logoColor=white)
![Security Passed](https://img.shields.io/badge/Security-Prepared%20Stmt%20%7C%20Anti--CSRF%20%7C%20Anti--XSS-success?style=for-the-badge&logo=securityscorecard&logoColor=white)

**Sistem Manajemen Inventaris Rantai Pasok Toko Roti & Pastry Berbasis Basis Data Relasional (PHP Native + MySQL/PDO).**

</div>

---

| Identitas | Keterangan |
| :--- | :--- |
| **Nama Mahasiswa** | **Nadya Salsabila Zahra** |
| **NIM** | **250180031** |
| **Mata Kuliah** | Pemrograman Web (Pertemuan 4 — Mini Project 3) |
| **Materi Pokok** | *Relational Database + Advanced PHP* |
| **Tema Proyek** | **SweetBakes — Artisan Bakery & Pastry Studio Manager** (Kelanjutan dari Praktikum 3) 🥐🍰 |
| **Arsitektur Utama** | Multi-table Relational Schema, Foreign Key Constraints, Many-to-Many Pivot, DB Transactions (`commit`/`rollBack`), RBAC Authorization, Anti-SQLi Prepared Statements, Anti-XSS Output Escaping, Token CSRF, Server-Side Validation, Dual Theme |

---

## 🎯 1. Ikhtisar & Hubungan Kelanjutan Proyek

Proyek ini merupakan **kelanjutan berkesinambungan** dari *Mini Project CRUD SweetBakes* pada modul sebelumnya. Pada tahap ini, sistem toko roti ditingkatkan dari katalog tunggal menjadi **Sistem Manajemen Inventaris Lengkap (Inventory Management System - IMS)** yang mengelola seluruh rantai pasok dapur bakery:
1. **Autentikasi & Otorisasi:** Sistem multi-user dengan enkripsi sandi BCRYPT dan pemisahan hak akses peran (**Admin** dan **Staff Gudang**).
2. **Relasi Basis Data Multitabel:**
   - `users`: Akun operator dan administrator.
   - `categories`: Master kelompok etalase (Pastry, Artisan Cake, Cupcake & Muffin, Cookies, Bread).
   - `suppliers`: Master mitra pemasok bahan mentah / impor (mentega Parisienne, cokelat Belgia, matcha Kyoto, tepung gandum).
   - `products`: Master item produk dengan `sku` unik dan relasi `category_id`.
   - `product_suppliers` (Many-to-Many): Relasi satu produk dapat dipasok oleh multi-supplier dan sebaliknya.
   - `inventory_transactions`: Pencatatan buku besar mutasi stok barang masuk (`IN`) dan keluar (`OUT`) lengkap dengan audit trail (`stock_before` dan `stock_after`).

---

## 🛡️ 2. Penerapan Aturan Bisnis (*Business Rules*)

Sistem telah diuji dan mematuhi 100% aturan bisnis yang dipersyaratkan:

| Aturan Bisnis | Implementasi Teknis | Lokasi Kode |
| :--- | :--- | :--- |
| **SKU harus unik** | `UNIQUE` constraint pada tabel `products` dan verifikasi fungsi `is_sku_unique($sku, $excludeId)` saat Create/Edit. | `helpers/validation.php`, `database/schema.sql` |
| **Harga dan quantity tidak boleh negatif** | Pengecekan server-side `validate_price()` dan `validate_quantity()`, serta DB `CHECK (price >= 0)` dan `CHECK (quantity > 0)`. | `helpers/validation.php` |
| **Barang keluar tidak boleh melebihi stok** | Fungsi `can_fulfill_stock_out()` memverifikasi saldo stok sebelum transaksi dan menolak otomatis bila permintaan melebihi kuota. | `public/stock/out.php` |
| **Stok tidak boleh menjadi negatif** | Transaksi pengurangan stok dilindungi atomisitas `beginTransaction()`, kalkulasi validasi, dan DB `CHECK (stock >= 0)`. | `public/stock/out.php`, `database/schema.sql` |
| **Produk berhistori dilarang dihapus** | Pemeriksaan `product_has_history($id)` dan foreign key `ON DELETE RESTRICT` mencegah penghapusan menu yang sudah pernah bermutasi. | `public/products/delete.php` |
| **Halaman internal membutuhkan login** | Middleware `require_login()` melindungi seluruh rute fungsional; pengguna tanpa sesi otomatis dialihkan ke `login.php`. | `helpers/auth.php` |
| **Staff dilarang akses administrasi user** | Middleware `require_role('admin')` mengunci menu kelola user; staf yang mencoba membuka akan menerima respons `HTTP 403 Forbidden`. | `public/users/index.php` |
| **Sorting hanya kolom yang diizinkan** | Fungsi `validate_sort_column()` memvalidasi parameter `$_GET['sort_by']` terhadap whitelist array untuk menangkal SQL Injection. | `helpers/validation.php`, `public/products/index.php` |

---

## 📁 3. Struktur Direktori Proyek

Sesuai anjuran arsitektur modular pada slide pertemuan:
```text
Praktikum4_250180031_Nadya_Salsabila_Zahra/
├── config/
│   └── database.php             # Koneksi PDO Multi-Driver (MySQL + Fallback Otomatis SQLite)
├── helpers/
│   ├── auth.php                 # Autentikasi sesi, verifikasi hash BCRYPT, otorisasi RBAC role
│   ├── validation.php           # Validasi server-side aturan bisnis, keunikan SKU, stok, & whitelist sort
│   └── escape.php               # Anti-XSS escaping e(), token CSRF, & format Rupiah
├── database/
│   ├── schema.sql               # Skema DDL MySQL relational, foreign keys, & data seed awal
│   └── store_db.sqlite          # Basis data mandiri (fallback instan tanpa instalasi)
├── assets/
│   └── css/
│       └── style.css            # Desain responsif Box Model & Dual Theme (Warm Vanilla & Dark Cocoa)
├── views/
│   ├── header.php               # Template navigasi dinamis berbasis role & flash alerts
│   └── footer.php               # Template footer, hak cipta mahasiswa, & kontrol tema
├── public/
│   ├── index.php                # Public entrypoint redirector
│   ├── login.php                # Formulir login aman (Anti-CSRF & BCRYPT verify)
│   ├── logout.php               # Handler pembersihan sesi logout
│   ├── dashboard.php            # Dashboard statistik agregat, perputaran stok & peringatan kritis
│   ├── products/
│   │   ├── index.php            # Listing produk (Search, Filter Kategori/Stok, Whitelist Sort, Pagination)
│   │   ├── create.php           # Tambah menu baru & mapping relasi Many-to-Many Supplier
│   │   ├── edit.php             # Edit data produk & update relasi supplier
│   │   └── delete.php           # Konfirmasi hapus produk dengan proteksi aturan riwayat
│   ├── categories/
│   │   └── index.php            # CRUD master kategori dengan perlindungan produk terhubung
│   ├── suppliers/
│   │   └── index.php            # CRUD master mitra supplier & daftar item terpasok
│   ├── stock/
│   │   ├── index.php            # Log audit trail transaksi stok (Search, Filter, Pagination)
│   │   ├── in.php               # Transaksi barang masuk (DB Transaction commit/rollback)
│   │   └── out.php              # Transaksi barang keluar dengan pencegahan defisit stok
│   └── users/
│       └── index.php            # Administrasi akun pengguna (Eksklusif Hak Akses Administrator)
├── index.php                    # Root entrypoint
├── run_tests.php                # Automated Test Suite (31 Skenario Pengujian Otomatis)
├── jalankan_server.bat          # Launcher 1-klik di Windows
└── README.md                    # Dokumentasi lengkap sistem & verifikasi rubrik
```

---

## 🚀 4. Cara Menjalankan Aplikasi

### Opsi 1: Menggunakan XAMPP (Apache & MySQL)
1. Salin folder proyek ke direktori `htdocs`:
   - `C:\xampp\htdocs\Praktikum4_250180031_Nadya_Salsabila_Zahra` (atau `C:\xampp\htdocs\inventory-app`)
2. Buka **XAMPP Control Panel**, lalu start **Apache** dan **MySQL**.
3. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`), buat basis data bernama `store_db`, lalu import berkas `database/schema.sql`.
4. Akses aplikasi melalui browser:
   ```text
   http://localhost/Praktikum4_250180031_Nadya_Salsabila_Zahra/
   ```

### Opsi 2: Menggunakan PHP Built-in Server (Instan 1-Klik)
1. Klik ganda file **`jalankan_server.bat`** di dalam folder proyek.
2. Atau jalankan perintah terminal:
   ```powershell
   "C:\xampp\php\php.exe" -S localhost:8000
   ```
3. Akses aplikasi di browser pada: `http://localhost:8000`.

---

## 🔑 5. Akun Login untuk Pengujian Dosen

Sistem dilengkapi dua akun contoh yang siap digunakan:

| Peran (Role) | Username | Password | Hak Akses Fitur |
| :--- | :--- | :--- | :--- |
| **Administrator** 👑 | `admin` | `admin123` | Akses penuh: Dashboard, Produk, Kategori, Supplier, Transaksi Stok, dan **Kelola User**. |
| **Staff Gudang** 🧑‍🍳 | `staff` | `staff123` | Akses operasional: Dashboard, Produk, Kategori, Supplier, Transaksi Stok. **Dilarang mengakses Kelola User (403 Forbidden)**. |

---

## 🧪 6. Hasil Automated Test Suite (`run_tests.php`)

Dijalankan secara otomatis melalui perintah `php run_tests.php`:

```text
=========================================================================
🧪 SweetBakes Mini Project 3: Automated Test Suite & Rubrik Verifikasi
Mahasiswa : Nadya Salsabila Zahra (NIM: 250180031)
=========================================================================
 [PASS] ✅ Tabel users ada dan dapat diakses (Total records: 2)
 [PASS] ✅ Tabel categories ada dan dapat diakses (Total records: 5)
 [PASS] ✅ Tabel suppliers ada dan dapat diakses (Total records: 4)
 [PASS] ✅ Tabel products ada dan dapat diakses (Total records: 8)
 [PASS] ✅ Tabel product_suppliers ada dan dapat diakses (Total records: 10)
 [PASS] ✅ Tabel inventory_transactions ada dan dapat diakses (Total records: 6)
 [PASS] ✅ Verifikasi password admin ('admin123')
 [PASS] ✅ Verifikasi password staff ('staff123')
 [PASS] ✅ Penolakan kredensial salah ('wrongpass')
 [PASS] ✅ Role admin lolos pemeriksaan has_role('admin')
 [PASS] ✅ Role staff gagal pada has_role('admin')
 [PASS] ✅ Role staff lolos pada has_role('staff')
 [PASS] ✅ Relasi Many-to-Many (Pivot product_suppliers) berjalan valid
 [PASS] ✅ SKU 'SWB-PST-001' terdeteksi sudah ada (tidak unik untuk create)
 [PASS] ✅ SKU 'SWB-PST-001' unik jika mengecualikan ID produknya sendiri (update)
 [PASS] ✅ SKU baru 'SWB-NEW-999' terdeteksi unik
 [PASS] ✅ Validasi harga positif (35000)
 [PASS] ✅ Validasi harga nol (0)
 [PASS] ✅ Penolakan harga negatif (-15000)
 [PASS] ✅ Validasi kuantitas positif (5)
 [PASS] ✅ Penolakan kuantitas 0 atau negatif (-2)
 [PASS] ✅ Penolakan barang keluar melebihi stok (minta 24+999 dari 24)
 [PASS] ✅ Penerimaan barang keluar dalam batas stok (minta 1 dari 24)
 [PASS] ✅ Produk ID 1 memiliki riwayat mutasi (product_has_history)
 [PASS] ✅ Whitelist sorting kolom valid ('name')
 [PASS] ✅ Whitelist sorting menolak kolom berbahaya ('1; DROP TABLE')
 [PASS] ✅ Whitelist sorting direction ('ASC')
 [PASS] ✅ Whitelist sorting direction ('SLEEP(5)') fallback ke ASC
 [PASS] ✅ Transaksi Rollback berhasil mengembalikan saldo stok semula (24 === 24)
 [PASS] ✅ Output escaping htmlspecialchars: e($xss)
 [PASS] ✅ Perhitungan nilai agregat valuasi etalase toko bernilai valid (> 0): Rp 7,015,000

=========================================================================
🏁 Ringkasan Pengujian Sistem:
Total Lulus : 31
Total Gagal : 0
Hasil Akhir : 100% SEMUA PENGUJIAN LULUS! SIAP DIKUMPULKAN 🌟
=========================================================================
```

---

## 📋 7. Pemenuhan Rubrik Penilaian Dosen (Slide 35 & 36)

| Komponen Rubrik | Bobot | Bukti Penerapan dalam Kode Proyek | Status |
| :--- | :---: | :--- | :---: |
| **Desain database dan relasi** | 20% | Skema ternormalisasi 6 tabel (`users`, `categories`, `suppliers`, `products`, pivot `product_suppliers`, `inventory_transactions`). | **100% Terpenuhi ✅** |
| **Foreign key & integritas data** | 10% | Deklarasi kunci asing lengkap dengan `ON UPDATE CASCADE`, `ON DELETE RESTRICT`, dan `ON DELETE CASCADE`. | **100% Terpenuhi ✅** |
| **CRUD master data** | 10% | CRUD lengkap pada Kategori, Produk, dan Supplier beserta validasi integritas entitas. | **100% Terpenuhi ✅** |
| **Transaksi masuk/keluar** | 20% | Modul Barang Masuk & Keluar dengan audit trail dan transaksi atomik PDO (`beginTransaction`, `commit`, `rollBack`). | **100% Terpenuhi ✅** |
| **Search, filter, sorting, pagination** | 10% | Fitur pencarian multi-kolom, filter kategori/stok, pagination halaman dinamis, dan whitelist sorting anti-SQLi. | **100% Terpenuhi ✅** |
| **Login, session, authorization** | 10% | Autentikasi BCRYPT, session regeneration, perlindungan RBAC (Admin vs Staff), pemblokiran 403 Forbidden. | **100% Terpenuhi ✅** |
| **Keamanan aplikasi** | 15% | Prepared Statements 100% tanpa penggabungan string, Anti-XSS `htmlspecialchars`, dan validasi token Anti-CSRF. | **100% Terpenuhi ✅** |
| **Kerapian dan presentasi** | 5% | Struktur folder rapi modular, CSS toko roti responsif, dual theme, dan panduan dokumentasi terperinci. | **100% Terpenuhi ✅** |
