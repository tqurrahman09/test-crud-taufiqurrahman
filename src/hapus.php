<?php
/**
 * Hapus barang. Hanya menerima POST (dari form dengan konfirmasi JavaScript confirm()).
 */
require __DIR__ . '/inc/functions.php';

if (!is_post()) {
    redirect('index.php');
}
if (!csrf_valid()) {
    set_flash('danger', 'Permintaan tidak valid (token kedaluwarsa). Silakan coba lagi.');
    redirect('index.php');
}

$id = input('id');
if (!ctype_digit($id)) {
    set_flash('danger', 'ID barang tidak valid.');
    redirect('index.php');
}
$id = (int) $id;

$barang = fetch_one("SELECT id_barang, kode_barang, nama_barang FROM barang WHERE id_barang = $id");
if (!$barang) {
    set_flash('danger', 'Data barang tidak ditemukan.');
    redirect('index.php');
}

// Barang yang sudah punya riwayat transaksi tidak boleh dihapus (menjaga histori stok)
$trx = fetch_one("SELECT COUNT(*) AS n FROM transaksi WHERE id_barang = $id");
if ((int) $trx['n'] > 0) {
    set_flash('danger', 'Barang "' . $barang['nama_barang'] . '" tidak dapat dihapus karena memiliki '
        . (int) $trx['n'] . ' riwayat transaksi. Hapus transaksinya terlebih dahulu.');
    redirect('index.php');
}

try {
    db_query("DELETE FROM barang WHERE id_barang = $id");
    set_flash('success', 'Barang "' . $barang['kode_barang'] . ' - ' . $barang['nama_barang'] . '" berhasil dihapus.');
} catch (Exception $ex) {
    // 1451 = gagal karena foreign key (mis. transaksi baru masuk bersamaan)
    set_flash('danger', $ex->getCode() == 1451
        ? 'Barang tidak dapat dihapus karena masih direferensikan oleh transaksi.'
        : 'Gagal menghapus data.');
}
redirect('index.php');
