<?php
require __DIR__ . '/inc/functions.php';

$page_title = 'Edit Barang';
$active = 'barang';
$mode = 'edit';

$id = get('id');
if (!ctype_digit($id)) {
    set_flash('danger', 'ID barang tidak valid.');
    redirect('index.php');
}
$id = (int) $id;

$barang = fetch_one("SELECT * FROM barang WHERE id_barang = $id");
if (!$barang) {
    set_flash('danger', 'Data barang tidak ditemukan.');
    redirect('index.php');
}

$kategori = get_kategori();
$satuan   = get_satuan();
$errors   = array();
$data = array(
    'kode_barang'   => $barang['kode_barang'],
    'nama_barang'   => $barang['nama_barang'],
    'id_kategori'   => $barang['id_kategori'],
    'id_satuan'     => $barang['id_satuan'],
    'stok_minimum'  => $barang['stok_minimum'],
    'harga_beli'    => num_input($barang['harga_beli']),
    'harga_jual'    => num_input($barang['harga_jual']),
    'lokasi_rak'    => $barang['lokasi_rak'],
    'tanggal_masuk' => $barang['tanggal_masuk'],
);

if (is_post()) {
    if (!csrf_valid()) {
        $errors['_'] = 'Sesi formulir kedaluwarsa, silakan kirim ulang.';
    } else {
        $data   = input_barang(false);
        $errors = validasi_barang($data, $id);
    }

    if (!$errors) {
        try {
            db_query("
                UPDATE barang SET
                    kode_barang   = '" . esc($data['kode_barang']) . "',
                    nama_barang   = '" . esc($data['nama_barang']) . "',
                    id_kategori   = " . (int) $data['id_kategori'] . ",
                    id_satuan     = " . (int) $data['id_satuan'] . ",
                    stok_minimum  = " . (int) $data['stok_minimum'] . ",
                    harga_beli    = '" . esc($data['harga_beli']) . "',
                    harga_jual    = '" . esc($data['harga_jual']) . "',
                    lokasi_rak    = '" . esc($data['lokasi_rak']) . "',
                    tanggal_masuk = '" . esc($data['tanggal_masuk']) . "'
                WHERE id_barang = $id
            ");
            set_flash('success', 'Barang "' . $data['nama_barang'] . '" berhasil diperbarui.');
            redirect('index.php');
        } catch (Exception $ex) {
            $errors['_'] = $ex->getCode() == 1062
                ? 'Kode atau nama barang sudah digunakan.'
                : 'Gagal menyimpan perubahan. Silakan coba lagi.';
        }
    }
}

include __DIR__ . '/inc/header.php';
?>
<div class="page-head">
    <div>
        <h1>Edit Barang</h1>
        <p class="muted"><code><?= e($barang['kode_barang']) ?></code> &middot; <?= e($barang['nama_barang']) ?></p>
    </div>
    <a href="index.php" class="btn btn-ghost">&larr; Kembali</a>
</div>

<?php include __DIR__ . '/inc/form_barang.php'; ?>
<?php include __DIR__ . '/inc/footer.php'; ?>
