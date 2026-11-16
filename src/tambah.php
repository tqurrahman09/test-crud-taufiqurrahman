<?php
require __DIR__ . '/inc/functions.php';

$page_title = 'Tambah Barang';
$active = 'barang';
$mode = 'tambah';

$kategori = get_kategori();
$satuan   = get_satuan();
$errors   = array();
$data = array(
    'kode_barang' => '', 'nama_barang' => '', 'id_kategori' => '', 'id_satuan' => '',
    'stok_awal' => '0', 'stok_minimum' => '0', 'harga_beli' => '', 'harga_jual' => '',
    'lokasi_rak' => '', 'tanggal_masuk' => date('Y-m-d'),
);

if (is_post()) {
    if (!csrf_valid()) {
        $errors['_'] = 'Sesi formulir kedaluwarsa, silakan kirim ulang.';
    } else {
        $data   = input_barang(true);
        $errors = validasi_barang($data, 0);
    }

    if (!$errors) {
        try {
            db_begin();
            db_query("
                INSERT INTO barang (kode_barang, nama_barang, id_kategori, id_satuan, stok, stok_minimum,
                                    harga_beli, harga_jual, lokasi_rak, tanggal_masuk)
                VALUES ('" . esc($data['kode_barang']) . "',
                        '" . esc($data['nama_barang']) . "',
                        " . (int) $data['id_kategori'] . ",
                        " . (int) $data['id_satuan'] . ",
                        " . (int) $data['stok_awal'] . ",
                        " . (int) $data['stok_minimum'] . ",
                        '" . esc($data['harga_beli']) . "',
                        '" . esc($data['harga_jual']) . "',
                        '" . esc($data['lokasi_rak']) . "',
                        '" . esc($data['tanggal_masuk']) . "')
            ");
            $id_barang = (int) db_insert_id();

            // Stok awal dicatat sebagai transaksi masuk agar riwayat stok konsisten
            if ((int) $data['stok_awal'] > 0) {
                $no = generate_no_transaksi('masuk', $data['tanggal_masuk']);
                db_query("
                    INSERT INTO transaksi (no_transaksi, id_barang, jenis, tanggal, jumlah, harga_satuan, keterangan)
                    VALUES ('" . esc($no) . "', $id_barang, 'masuk', '" . esc($data['tanggal_masuk']) . "',
                            " . (int) $data['stok_awal'] . ", '" . esc($data['harga_beli']) . "', 'Stok awal')
                ");
            }
            db_commit();

            set_flash('success', 'Barang "' . $data['nama_barang'] . '" berhasil ditambahkan.');
            redirect('index.php');
        } catch (Exception $ex) {
            db_rollback();
            $errors['_'] = $ex->getCode() == 1062
                ? 'Kode atau nama barang sudah digunakan.'
                : 'Gagal menyimpan data. Silakan coba lagi.';
        }
    }
}

include __DIR__ . '/inc/header.php';
?>
<div class="page-head">
    <div>
        <h1>Tambah Barang</h1>
        <p class="muted">Kolom bertanda <span class="req">*</span> wajib diisi.</p>
    </div>
    <a href="index.php" class="btn btn-ghost">&larr; Kembali</a>
</div>

<?php include __DIR__ . '/inc/form_barang.php'; ?>
<?php include __DIR__ . '/inc/footer.php'; ?>
