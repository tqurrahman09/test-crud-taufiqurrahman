<?php
/**
 * Hapus transaksi + kembalikan stok (reversal). Hanya POST, dengan confirm() di sisi klien.
 */
require __DIR__ . '/inc/functions.php';

if (!is_post()) {
    redirect('transaksi.php');
}
if (!csrf_valid()) {
    set_flash('danger', 'Permintaan tidak valid (token kedaluwarsa). Silakan coba lagi.');
    redirect('transaksi.php');
}

$id = input('id');
if (!ctype_digit($id)) {
    set_flash('danger', 'ID transaksi tidak valid.');
    redirect('transaksi.php');
}
$id = (int) $id;

try {
    db_begin();
    $t = fetch_one("SELECT id_transaksi, no_transaksi, id_barang, jenis, jumlah FROM transaksi WHERE id_transaksi = $id FOR UPDATE");
    if (!$t) {
        db_rollback();
        set_flash('danger', 'Transaksi tidak ditemukan.');
        redirect('transaksi.php');
    }

    $id_barang = (int) $t['id_barang'];
    $jumlah    = (int) $t['jumlah'];
    $b = fetch_one("SELECT stok, nama_barang FROM barang WHERE id_barang = $id_barang FOR UPDATE");

    // Membatalkan barang masuk = stok dikurangi; tidak boleh membuat stok negatif
    if ($t['jenis'] === 'masuk' && (int) $b['stok'] < $jumlah) {
        db_rollback();
        set_flash('danger', 'Transaksi ' . $t['no_transaksi'] . ' tidak dapat dihapus: stok "' . $b['nama_barang']
            . '" saat ini ' . angka($b['stok']) . ', lebih kecil dari jumlah yang akan dikurangi (' . angka($jumlah) . ').');
        redirect('transaksi.php');
    }

    $op = $t['jenis'] === 'masuk' ? '-' : '+';
    db_query("UPDATE barang SET stok = stok $op $jumlah WHERE id_barang = $id_barang");
    db_query("DELETE FROM transaksi WHERE id_transaksi = $id");
    db_commit();

    set_flash('success', 'Transaksi ' . $t['no_transaksi'] . ' dihapus. Stok "' . $b['nama_barang'] . '" '
        . ($op === '+' ? 'ditambah' : 'dikurangi') . ' ' . angka($jumlah) . '.');
} catch (Exception $ex) {
    db_rollback();
    set_flash('danger', 'Gagal menghapus transaksi.');
}
redirect('transaksi.php');
