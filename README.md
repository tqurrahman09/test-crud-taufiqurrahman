# Inventaris Barang Gudang — PHP 5.6 Native + MySQL

Aplikasi CRUD inventaris gudang + transaksi barang masuk/keluar.
Ditulis dengan **PHP native** (tanpa framework/library), koneksi memakai fungsi `mysql_*`.

## Menjalankan

```bash
docker compose up -d --build
```

| Layanan     | URL / Host                     | Kredensial                      |
|-------------|--------------------------------|---------------------------------|
| Aplikasi    | http://localhost:8080          | –                               |
| phpMyAdmin  | http://localhost:8081          | `root` / `root123`              |
| MySQL 5.7   | `localhost:3309`               | `gudang` / `gudang123` (db: `db_gudang`) |

Skema + data contoh otomatis di-import dari `db/init.sql` saat volume database pertama kali dibuat.
Reset ke data awal: `docker compose down -v && docker compose up -d`.

## Struktur

```
docker-compose.yml        # web (php:5.6-apache + ext mysql), db (mysql:5.7), phpmyadmin
docker/php/Dockerfile
db/init.sql               # DDL + data contoh
src/
  koneksi.php             # mysql_connect, mysql_select_db, mysql_query, mysql_fetch_array + helper query
  index.php               # daftar barang (JOIN kategori & satuan), cari, filter, urut, paginasi
  tambah.php / edit.php   # form barang + validasi
  hapus.php               # hapus barang (POST + confirm())
  transaksi.php           # daftar transaksi (JOIN 4 tabel), filter rentang tanggal, rekap
  transaksi_tambah.php    # input barang masuk / keluar, stok otomatis berubah
  transaksi_hapus.php     # hapus transaksi + stok dikembalikan
  inc/                    # functions.php, header/footer, partial form
  assets/style.css
```

## Desain Database (`db_gudang`)

```
kategori (1) ──< barang >── (1) satuan
                   │
                   └──< transaksi
```

| Tabel       | Kolom penting | PK / Unik / FK |
|-------------|---------------|----------------|
| `kategori`  | kode_kategori, nama_kategori, keterangan | PK id_kategori; UNIQUE kode & nama |
| `satuan`    | kode_satuan, nama_satuan | PK id_satuan; UNIQUE kode & nama |
| `barang`    | kode_barang, nama_barang, stok `INT UNSIGNED`, stok_minimum, harga_beli/harga_jual `DECIMAL(15,2)`, lokasi_rak, tanggal_masuk `DATE`, created_at/updated_at | PK id_barang; UNIQUE kode & nama; FK → kategori, satuan (`ON DELETE RESTRICT`) |
| `transaksi` | no_transaksi, jenis `ENUM('masuk','keluar')`, tanggal `DATE`, jumlah `INT UNSIGNED`, harga_satuan `DECIMAL(15,2)`, keterangan | PK id_transaksi; UNIQUE no_transaksi; FK → barang (`ON DELETE RESTRICT`) |

Catatan desain:
- Uang memakai `DECIMAL`, bukan `FLOAT`, agar tidak ada pembulatan.
- `stok` hanya berubah lewat transaksi. Stok awal saat tambah barang otomatis dicatat sebagai transaksi masuk "Stok awal",
  sehingga `stok` selalu = Σ masuk − Σ keluar.
- Barang yang sudah memiliki riwayat transaksi tidak dapat dihapus (FK RESTRICT + pesan yang jelas), agar histori stok tidak hilang.

## Keamanan
- **SQL Injection**: setiap string melewati `mysql_real_escape_string` (fungsi `esc()`) dan selalu dikutip;
  angka/ID di-cast `(int)` setelah divalidasi `ctype_digit`; pola LIKE juga meng-escape `%` dan `_`;
  kolom sort memakai whitelist.
- **XSS**: semua output melalui `htmlspecialchars` (`e()`).
- **CSRF**: semua form POST (tambah, edit, hapus) memakai token sesi.
- Hapus hanya lewat POST, didahului `confirm()` JavaScript.
- Perubahan stok memakai transaksi database (`START TRANSACTION` + `SELECT … FOR UPDATE`) agar aman dari input bersamaan.

## Validasi
- Semua field wajib diisi (dicek di server, bukan hanya `required` HTML).
- Unik: kode barang dan nama barang tidak boleh dobel (saat tambah & edit, mengecualikan dirinya sendiri), plus UNIQUE KEY di DB.
- Format: tanggal `YYYY-MM-DD` dan tanggalnya harus benar-benar ada (`checkdate`), stok/jumlah harus bilangan bulat, harga angka maks. 2 desimal.
- Validasi tambahan: format kode (`BRG-0001`), kategori/satuan harus ada di tabel master, harga > 0,
  harga jual ≥ harga beli, tanggal tidak boleh di masa depan, jumlah transaksi 1–1.000.000,
  barang keluar tidak boleh melebihi stok, tanggal transaksi tidak boleh sebelum barang terdaftar,
  hapus transaksi masuk ditolak bila stok akan menjadi negatif.

## Fitur Tambahan
- Kartu ringkasan: jumlah jenis barang, total unit, nilai persediaan, stok menipis/habis.
- Status stok (Aman / Menipis / Habis) berdasarkan stok minimum.
- Pencarian, filter kategori/status, pengurutan, dan paginasi di daftar barang.
- Transaksi: nomor otomatis (`IN-20261116-0001` / `OUT-…`), harga terisi otomatis sesuai jenis, total dihitung langsung,
  info stok tersedia, tombol rentang cepat (hari ini / 7 hari / bulan ini / bulan lalu), filter jenis/barang/kata kunci,
  rekap total di bawah tabel + rekap per barang, tampilan cetak.
- Hapus transaksi otomatis mengembalikan stok.
