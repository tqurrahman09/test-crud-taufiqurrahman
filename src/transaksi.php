<?php
require __DIR__ . '/inc/functions.php';

$page_title = 'Daftar Transaksi';
$active = 'transaksi';

// ---------- Filter ----------
$dari     = get('dari');
$sampai   = get('sampai');
$f_jenis  = get('jenis');
$f_barang = get('id_barang');
$q        = get('q');
$filter_error = array();

if ($dari !== '' && !is_valid_date($dari)) {
    $filter_error[] = 'Tanggal "dari" tidak valid, filter diabaikan.';
    $dari = '';
}
if ($sampai !== '' && !is_valid_date($sampai)) {
    $filter_error[] = 'Tanggal "sampai" tidak valid, filter diabaikan.';
    $sampai = '';
}
if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
    $filter_error[] = 'Tanggal "dari" lebih besar dari "sampai" — rentang ditukar otomatis.';
    $tmp = $dari; $dari = $sampai; $sampai = $tmp;
}

$where = array('1=1');
if ($dari !== '') {
    $where[] = "t.tanggal >= '" . esc($dari) . "'";
}
if ($sampai !== '') {
    $where[] = "t.tanggal <= '" . esc($sampai) . "'";
}
if (in_array($f_jenis, array('masuk', 'keluar'), true)) {
    $where[] = "t.jenis = '" . esc($f_jenis) . "'";
} else {
    $f_jenis = '';
}
if ($f_barang !== '' && ctype_digit($f_barang)) {
    $where[] = 't.id_barang = ' . (int) $f_barang;
} else {
    $f_barang = '';
}
if ($q !== '') {
    $like = esc_like($q);
    $where[] = "(t.no_transaksi LIKE '%$like%' OR t.keterangan LIKE '%$like%' OR b.nama_barang LIKE '%$like%' OR b.kode_barang LIKE '%$like%')";
}
$where_sql = implode(' AND ', $where);

$from_sql = "
    FROM transaksi t
    INNER JOIN barang   b ON b.id_barang   = t.id_barang
    INNER JOIN satuan   s ON s.id_satuan   = b.id_satuan
    INNER JOIN kategori k ON k.id_kategori = b.id_kategori
    WHERE $where_sql
";

// ---------- Data (JOIN 4 tabel) ----------
$rows = fetch_all("
    SELECT t.id_transaksi, t.no_transaksi, t.jenis, t.tanggal, t.jumlah, t.harga_satuan, t.keterangan,
           (t.jumlah * t.harga_satuan) AS total,
           b.kode_barang, b.nama_barang, s.nama_satuan, k.nama_kategori
    $from_sql
    ORDER BY t.tanggal DESC, t.id_transaksi DESC
");

// ---------- Rekap total ----------
$rekap = fetch_one("
    SELECT COUNT(*) AS jml,
           COALESCE(SUM(t.jenis = 'masuk'), 0)  AS jml_masuk,
           COALESCE(SUM(t.jenis = 'keluar'), 0) AS jml_keluar,
           COALESCE(SUM(IF(t.jenis = 'masuk',  t.jumlah, 0)), 0) AS qty_masuk,
           COALESCE(SUM(IF(t.jenis = 'keluar', t.jumlah, 0)), 0) AS qty_keluar,
           COALESCE(SUM(IF(t.jenis = 'masuk',  t.jumlah * t.harga_satuan, 0)), 0) AS nilai_masuk,
           COALESCE(SUM(IF(t.jenis = 'keluar', t.jumlah * t.harga_satuan, 0)), 0) AS nilai_keluar
    $from_sql
");

// ---------- Rekap per barang ----------
$rekap_barang = fetch_all("
    SELECT b.kode_barang, b.nama_barang, s.nama_satuan,
           COUNT(*) AS jml,
           SUM(IF(t.jenis = 'masuk',  t.jumlah, 0)) AS qty_masuk,
           SUM(IF(t.jenis = 'keluar', t.jumlah, 0)) AS qty_keluar,
           SUM(IF(t.jenis = 'masuk',  t.jumlah * t.harga_satuan, 0)) AS nilai_masuk,
           SUM(IF(t.jenis = 'keluar', t.jumlah * t.harga_satuan, 0)) AS nilai_keluar
    $from_sql
    GROUP BY b.id_barang, b.kode_barang, b.nama_barang, s.nama_satuan
    ORDER BY b.kode_barang
");

$barang_opsi = fetch_all('SELECT id_barang, kode_barang, nama_barang FROM barang ORDER BY kode_barang');

// Rentang cepat
$today = date('Y-m-d');
$quick = array(
    'Hari ini'   => array($today, $today),
    '7 hari'     => array(date('Y-m-d', strtotime('-6 days')), $today),
    'Bulan ini'  => array(date('Y-m-01'), $today),
    'Bulan lalu' => array(date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))),
);

$periode = ($dari === '' && $sampai === '') ? 'Semua tanggal'
    : (($dari !== '' ? tgl_indo($dari) : 'awal') . ' s/d ' . ($sampai !== '' ? tgl_indo($sampai) : 'sekarang'));

include __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div>
        <h1>Daftar Transaksi</h1>
        <p class="muted">Riwayat barang masuk &amp; keluar. Periode: <strong><?= e($periode) ?></strong></p>
    </div>
    <div class="head-actions no-print">
        <button type="button" class="btn btn-ghost" onclick="window.print()">Cetak</button>
        <a href="transaksi_tambah.php" class="btn btn-primary">+ Input Transaksi</a>
    </div>
</div>

<?php foreach ($filter_error as $fe): ?>
    <div class="alert alert-warning"><?= e($fe) ?></div>
<?php endforeach; ?>

<form class="filters card no-print" method="get" action="transaksi.php">
    <div class="form-group">
        <label for="dari">Dari tanggal</label>
        <input type="date" id="dari" name="dari" value="<?= e($dari) ?>">
    </div>
    <div class="form-group">
        <label for="sampai">Sampai tanggal</label>
        <input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>">
    </div>
    <div class="form-group">
        <label for="jenis">Jenis</label>
        <select id="jenis" name="jenis">
            <option value="">Semua</option>
            <option value="masuk" <?= $f_jenis === 'masuk' ? 'selected' : '' ?>>Masuk</option>
            <option value="keluar" <?= $f_jenis === 'keluar' ? 'selected' : '' ?>>Keluar</option>
        </select>
    </div>
    <div class="form-group">
        <label for="id_barang">Barang</label>
        <select id="id_barang" name="id_barang">
            <option value="">Semua barang</option>
            <?php foreach ($barang_opsi as $b): ?>
                <option value="<?= (int) $b['id_barang'] ?>" <?= $f_barang === (string) $b['id_barang'] ? 'selected' : '' ?>><?= e($b['kode_barang'] . ' — ' . $b['nama_barang']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group grow">
        <label for="q">Cari</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="No. transaksi / keterangan…">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="transaksi.php" class="btn btn-ghost">Reset</a>
    </div>
    <div class="quick-range">
        <span class="muted">Rentang cepat:</span>
        <?php foreach ($quick as $label => $r): ?>
            <a class="chip<?= ($dari === $r[0] && $sampai === $r[1]) ? ' active' : '' ?>"
               href="transaksi.php?<?= e(http_build_query(array_filter(array('dari' => $r[0], 'sampai' => $r[1], 'jenis' => $f_jenis, 'id_barang' => $f_barang, 'q' => $q), 'strlen'))) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</form>

<div class="card table-card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th class="num">No</th>
                <th>No. Transaksi</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Barang</th>
                <th>Kategori</th>
                <th class="num">Jumlah</th>
                <th class="num">Harga Satuan</th>
                <th class="num">Total</th>
                <th>Keterangan</th>
                <th class="center no-print">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="11" class="empty">Tidak ada transaksi pada filter ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td class="num muted"><?= $i + 1 ?></td>
                    <td><code><?= e($r['no_transaksi']) ?></code></td>
                    <td class="nowrap"><?= tgl_indo($r['tanggal']) ?></td>
                    <td><span class="badge badge-<?= $r['jenis'] === 'masuk' ? 'in' : 'out' ?>"><?= $r['jenis'] === 'masuk' ? '▼ Masuk' : '▲ Keluar' ?></span></td>
                    <td><span class="strong"><?= e($r['nama_barang']) ?></span><br><small class="muted"><?= e($r['kode_barang']) ?></small></td>
                    <td><?= e($r['nama_kategori']) ?></td>
                    <td class="num nowrap"><?= ($r['jenis'] === 'masuk' ? '+' : '−') . angka($r['jumlah']) ?> <?= e($r['nama_satuan']) ?></td>
                    <td class="num"><?= rupiah($r['harga_satuan']) ?></td>
                    <td class="num strong"><?= rupiah($r['total']) ?></td>
                    <td><?= e($r['keterangan']) ?></td>
                    <td class="actions no-print">
                        <form method="post" action="transaksi_hapus.php" class="inline"
                              onsubmit="return confirm(<?= e(json_encode('Hapus transaksi ' . $r['no_transaksi'] . '?' . "\n" . 'Stok barang akan dikembalikan (' . ($r['jenis'] === 'masuk' ? 'dikurangi' : 'ditambah') . ' ' . $r['jumlah'] . ').')) ?>);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id_transaksi'] ?>">
                            <button type="submit" class="btn btn-xs btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($rows): ?>
            <tfoot>
            <tr>
                <td colspan="6" class="right">Total barang masuk (<?= angka($rekap['jml_masuk']) ?> transaksi)</td>
                <td class="num">+<?= angka($rekap['qty_masuk']) ?></td>
                <td></td>
                <td class="num"><?= rupiah($rekap['nilai_masuk']) ?></td>
                <td colspan="2" class="no-print-cell"></td>
            </tr>
            <tr>
                <td colspan="6" class="right">Total barang keluar (<?= angka($rekap['jml_keluar']) ?> transaksi)</td>
                <td class="num">−<?= angka($rekap['qty_keluar']) ?></td>
                <td></td>
                <td class="num"><?= rupiah($rekap['nilai_keluar']) ?></td>
                <td colspan="2"></td>
            </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<h2 class="section-title">Rekap Periode</h2>
<div class="stats">
    <div class="stat"><span class="stat-label">Jumlah Transaksi</span><span class="stat-value"><?= angka($rekap['jml']) ?></span>
        <span class="stat-sub"><?= angka($rekap['jml_masuk']) ?> masuk · <?= angka($rekap['jml_keluar']) ?> keluar</span></div>
    <div class="stat stat-in"><span class="stat-label">Total Nilai Masuk</span><span class="stat-value"><?= rupiah($rekap['nilai_masuk']) ?></span>
        <span class="stat-sub"><?= angka($rekap['qty_masuk']) ?> unit</span></div>
    <div class="stat stat-out"><span class="stat-label">Total Nilai Keluar</span><span class="stat-value"><?= rupiah($rekap['nilai_keluar']) ?></span>
        <span class="stat-sub"><?= angka($rekap['qty_keluar']) ?> unit</span></div>
    <div class="stat"><span class="stat-label">Selisih Unit (Masuk − Keluar)</span><span class="stat-value"><?= angka($rekap['qty_masuk'] - $rekap['qty_keluar']) ?></span>
        <span class="stat-sub">pergerakan stok bersih</span></div>
</div>

<?php if ($rekap_barang): ?>
<div class="card table-card">
    <div class="card-title">Rekap per Barang</div>
    <div class="table-wrap">
        <table class="table table-compact">
            <thead>
            <tr>
                <th>Barang</th>
                <th class="num">Transaksi</th>
                <th class="num">Qty Masuk</th>
                <th class="num">Qty Keluar</th>
                <th class="num">Nilai Masuk</th>
                <th class="num">Nilai Keluar</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rekap_barang as $rb): ?>
                <tr>
                    <td><code><?= e($rb['kode_barang']) ?></code> <?= e($rb['nama_barang']) ?></td>
                    <td class="num"><?= angka($rb['jml']) ?></td>
                    <td class="num text-in"><?= angka($rb['qty_masuk']) ?> <?= e($rb['nama_satuan']) ?></td>
                    <td class="num text-out"><?= angka($rb['qty_keluar']) ?> <?= e($rb['nama_satuan']) ?></td>
                    <td class="num"><?= rupiah($rb['nilai_masuk']) ?></td>
                    <td class="num"><?= rupiah($rb['nilai_keluar']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
