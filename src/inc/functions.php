<?php
require_once __DIR__ . '/../koneksi.php';

if (session_id() === '') {
    session_start();
}

// Tampilkan halaman error yang ramah bila ada exception tak tertangani
set_exception_handler(function ($ex) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
       . '<div style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:24px;border:1px solid #f1c0c0;background:#fff5f5;border-radius:8px">'
       . '<h2 style="margin-top:0;color:#b42318">Terjadi kesalahan</h2><p>' . e($ex->getMessage()) . '</p>'
       . '<p><a href="index.php">&larr; Kembali ke beranda</a></p></div>';
});

/* ------------------------------------------------------------------ */
/* Output & input                                                      */
/* ------------------------------------------------------------------ */

/** Escape output HTML (anti XSS). */
function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Ambil nilai POST (string, sudah di-trim). */
function input($key, $default = '')
{
    return (isset($_POST[$key]) && is_string($_POST[$key])) ? trim($_POST[$key]) : $default;
}

/** Ambil nilai GET (string, sudah di-trim). */
function get($key, $default = '')
{
    return (isset($_GET[$key]) && is_string($_GET[$key])) ? trim($_GET[$key]) : $default;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/* ------------------------------------------------------------------ */
/* Flash message & CSRF                                                */
/* ------------------------------------------------------------------ */

function set_flash($type, $msg)
{
    $_SESSION['flash'][] = array('type' => $type, 'msg' => $msg);
}

function get_flash()
{
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : array();
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $bytes = function_exists('openssl_random_pseudo_bytes')
            ? openssl_random_pseudo_bytes(16)
            : uniqid(mt_rand(), true);
        $_SESSION['csrf'] = bin2hex($bytes);
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid()
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

/* ------------------------------------------------------------------ */
/* Validasi                                                            */
/* ------------------------------------------------------------------ */

/** Tanggal format YYYY-MM-DD dan benar-benar ada di kalender. */
function is_valid_date($s)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/** Bilangan bulat >= 0 (tanpa tanda, tanpa desimal). */
function is_whole_number($s)
{
    return preg_match('/^\d{1,10}$/', $s) === 1;
}

/** Nominal uang: angka, maksimal 2 digit desimal (pemisah titik). */
function is_money($s)
{
    return preg_match('/^\d{1,13}(\.\d{1,2})?$/', $s) === 1;
}

function str_len($s)
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen(utf8_decode($s));
}

function field_error($errors, $field)
{
    return isset($errors[$field]) ? '<div class="field-error">' . e($errors[$field]) . '</div>' : '';
}

function err_class($errors, $field)
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

/* ------------------------------------------------------------------ */
/* Format tampilan                                                     */
/* ------------------------------------------------------------------ */

function rupiah($n)
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function angka($n)
{
    return number_format((float) $n, 0, ',', '.');
}

/** 11500000.00 -> "11500000", 1500.50 -> "1500.50" (untuk value input). */
function num_input($n)
{
    $n = (string) $n;
    return preg_match('/^(\d+)\.0+$/', $n, $m) ? $m[1] : $n;
}

function tgl_indo($tgl)
{
    if (!is_valid_date((string) $tgl)) {
        return '-';
    }
    $bulan = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
    list($y, $m, $d) = explode('-', $tgl);
    return (int) $d . ' ' . $bulan[(int) $m] . ' ' . $y;
}

/* ------------------------------------------------------------------ */
/* Data master & domain                                                */
/* ------------------------------------------------------------------ */

function get_kategori()
{
    return fetch_all('SELECT id_kategori, kode_kategori, nama_kategori FROM kategori ORDER BY nama_kategori');
}

function get_satuan()
{
    return fetch_all('SELECT id_satuan, kode_satuan, nama_satuan FROM satuan ORDER BY nama_satuan');
}

/** Status stok: habis / menipis / aman. */
function status_stok($stok, $minimum)
{
    if ((int) $stok === 0) {
        return array('habis', 'Habis');
    }
    if ((int) $stok <= (int) $minimum) {
        return array('menipis', 'Menipis');
    }
    return array('aman', 'Aman');
}

/**
 * Nomor transaksi berikutnya, mis. IN-20261116-0003.
 * Wajib dipanggil di dalam transaksi DB (memakai FOR UPDATE).
 */
function generate_no_transaksi($jenis, $tanggal)
{
    $prefix = ($jenis === 'masuk' ? 'IN' : 'OUT') . '-' . str_replace('-', '', $tanggal) . '-';
    $row = fetch_one("SELECT no_transaksi FROM transaksi
                      WHERE no_transaksi LIKE '" . esc_like($prefix) . "%'
                      ORDER BY no_transaksi DESC LIMIT 1 FOR UPDATE");
    $next = $row ? ((int) substr($row['no_transaksi'], strlen($prefix))) + 1 : 1;
    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}

/** Ambil input form barang dan normalisasi. */
function input_barang($dengan_stok_awal)
{
    $d = array(
        'kode_barang'   => strtoupper(input('kode_barang')),
        'nama_barang'   => preg_replace('/\s+/', ' ', input('nama_barang')),
        'id_kategori'   => input('id_kategori'),
        'id_satuan'     => input('id_satuan'),
        'stok_minimum'  => input('stok_minimum'),
        'harga_beli'    => input('harga_beli'),
        'harga_jual'    => input('harga_jual'),
        'lokasi_rak'    => strtoupper(input('lokasi_rak')),
        'tanggal_masuk' => input('tanggal_masuk'),
    );
    if ($dengan_stok_awal) {
        $d['stok_awal'] = input('stok_awal');
    }
    return $d;
}

/**
 * Validasi data barang. $id_barang = 0 untuk tambah, > 0 untuk edit.
 * Mengembalikan array error [field => pesan]; kosong berarti valid.
 */
function validasi_barang($d, $id_barang = 0)
{
    $id_barang = (int) $id_barang;
    $err = array();

    $wajib = array(
        'kode_barang'   => 'Kode barang',
        'nama_barang'   => 'Nama barang',
        'id_kategori'   => 'Kategori',
        'id_satuan'     => 'Satuan',
        'stok_awal'     => 'Stok awal',
        'stok_minimum'  => 'Stok minimum',
        'harga_beli'    => 'Harga beli',
        'harga_jual'    => 'Harga jual',
        'lokasi_rak'    => 'Lokasi rak',
        'tanggal_masuk' => 'Tanggal masuk',
    );
    foreach ($wajib as $f => $label) {
        if (array_key_exists($f, $d) && $d[$f] === '') {
            $err[$f] = $label . ' wajib diisi.';
        }
    }

    // Kode barang: format + unik
    if (!isset($err['kode_barang'])) {
        if (!preg_match('/^[A-Z0-9][A-Z0-9-]{2,19}$/', $d['kode_barang'])) {
            $err['kode_barang'] = 'Kode 3–20 karakter: huruf, angka, dan tanda minus (contoh: BRG-0011).';
        } elseif (fetch_one("SELECT id_barang FROM barang WHERE kode_barang = '" . esc($d['kode_barang']) . "' AND id_barang <> $id_barang")) {
            $err['kode_barang'] = 'Kode barang "' . $d['kode_barang'] . '" sudah digunakan.';
        }
    }

    // Nama barang: panjang + unik
    if (!isset($err['nama_barang'])) {
        $len = str_len($d['nama_barang']);
        if ($len < 3 || $len > 100) {
            $err['nama_barang'] = 'Nama barang 3–100 karakter.';
        } elseif (fetch_one("SELECT id_barang FROM barang WHERE nama_barang = '" . esc($d['nama_barang']) . "' AND id_barang <> $id_barang")) {
            $err['nama_barang'] = 'Nama barang sudah terdaftar.';
        }
    }

    // Kategori & satuan harus ada di tabel master
    if (!isset($err['id_kategori'])
        && (!ctype_digit($d['id_kategori']) || !fetch_one('SELECT id_kategori FROM kategori WHERE id_kategori = ' . (int) $d['id_kategori']))) {
        $err['id_kategori'] = 'Kategori tidak valid.';
    }
    if (!isset($err['id_satuan'])
        && (!ctype_digit($d['id_satuan']) || !fetch_one('SELECT id_satuan FROM satuan WHERE id_satuan = ' . (int) $d['id_satuan']))) {
        $err['id_satuan'] = 'Satuan tidak valid.';
    }

    // Angka bulat
    foreach (array('stok_awal' => 'Stok awal', 'stok_minimum' => 'Stok minimum') as $f => $label) {
        if (array_key_exists($f, $d) && !isset($err[$f])) {
            if (!is_whole_number($d[$f])) {
                $err[$f] = $label . ' harus berupa bilangan bulat ≥ 0 (tanpa titik/koma).';
            } elseif ((int) $d[$f] > 1000000) {
                $err[$f] = $label . ' maksimal 1.000.000.';
            }
        }
    }

    // Harga
    foreach (array('harga_beli' => 'Harga beli', 'harga_jual' => 'Harga jual') as $f => $label) {
        if (!isset($err[$f])) {
            if (!is_money($d[$f])) {
                $err[$f] = $label . ' harus berupa angka (tanpa titik ribuan), maks. 2 desimal.';
            } elseif ((float) $d[$f] <= 0) {
                $err[$f] = $label . ' harus lebih dari 0.';
            }
        }
    }
    if (!isset($err['harga_beli']) && !isset($err['harga_jual'])
        && (float) $d['harga_jual'] < (float) $d['harga_beli']) {
        $err['harga_jual'] = 'Harga jual tidak boleh lebih kecil dari harga beli.';
    }

    // Lokasi rak
    if (!isset($err['lokasi_rak']) && !preg_match('/^[A-Z0-9][A-Z0-9 .\/-]{0,19}$/', $d['lokasi_rak'])) {
        $err['lokasi_rak'] = 'Lokasi rak maks. 20 karakter: huruf, angka, spasi, titik, / atau - (contoh: A-01).';
    }

    // Tanggal
    if (!isset($err['tanggal_masuk'])) {
        if (!is_valid_date($d['tanggal_masuk'])) {
            $err['tanggal_masuk'] = 'Tanggal tidak valid (format YYYY-MM-DD).';
        } elseif ($d['tanggal_masuk'] > date('Y-m-d')) {
            $err['tanggal_masuk'] = 'Tanggal masuk tidak boleh melebihi hari ini.';
        } elseif ($d['tanggal_masuk'] < '2000-01-01') {
            $err['tanggal_masuk'] = 'Tanggal masuk minimal 1 Jan 2000.';
        } elseif ($id_barang > 0) {
            $min = fetch_one("SELECT MIN(tanggal) AS t FROM transaksi WHERE id_barang = $id_barang");
            if ($min && $min['t'] !== null && $d['tanggal_masuk'] > $min['t']) {
                $err['tanggal_masuk'] = 'Tanggal masuk tidak boleh setelah transaksi pertama barang ini (' . tgl_indo($min['t']) . ').';
            }
        }
    }

    return $err;
}
