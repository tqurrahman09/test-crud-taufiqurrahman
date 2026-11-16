<?php
require __DIR__ . '/inc/functions.php';

$page_title = 'Data Barang';
$active = 'barang';

// ---------- Parameter filter ----------
$q        = get('q');
$f_kat    = get('kategori');
$f_status = get('status');
$sort     = get('sort', 'kode');
$page     = max(1, (int) get('page', '1'));
$per_page = 10;

$sort_map = array(
    'kode'      => 'b.kode_barang ASC',
    'nama'      => 'b.nama_barang ASC',
    'stok_asc'  => 'b.stok ASC, b.kode_barang ASC',
    'stok_desc' => 'b.stok DESC, b.kode_barang ASC',
    'terbaru'   => 'b.tanggal_masuk DESC, b.id_barang DESC',
);
if (!isset($sort_map[$sort])) {
    $sort = 'kode';
}

$where = array('1=1');
if ($q !== '') {
    $like = esc_like($q);
    $where[] = "(b.kode_barang LIKE '%$like%' OR b.nama_barang LIKE '%$like%' OR b.lokasi_rak LIKE '%$like%')";
}
if ($f_kat !== '' && ctype_digit($f_kat)) {
    $where[] = 'b.id_kategori = ' . (int) $f_kat;
}
if ($f_status === 'habis') {
    $where[] = 'b.stok = 0';
} elseif ($f_status === 'menipis') {
    $where[] = 'b.stok > 0 AND b.stok <= b.stok_minimum';
} elseif ($f_status === 'aman') {
    $where[] = 'b.stok > b.stok_minimum';
}
$where_sql = implode(' AND ', $where);

// ---------- Pagination ----------
$cnt = fetch_one("SELECT COUNT(*) AS n FROM barang b WHERE $where_sql");
$total_rows  = (int) $cnt['n'];
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$page   = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// ---------- Data (JOIN ke tabel master) ----------
$rows = fetch_all("
    SELECT b.id_barang, b.kode_barang, b.nama_barang, b.stok, b.stok_minimum,
           b.harga_beli, b.harga_jual, b.lokasi_rak, b.tanggal_masuk,
           k.nama_kategori, s.nama_satuan
    FROM barang b
    INNER JOIN kategori k ON k.id_kategori = b.id_kategori
    INNER JOIN satuan   s ON s.id_satuan   = b.id_satuan
    WHERE $where_sql
    ORDER BY {$sort_map[$sort]}
    LIMIT $offset, $per_page
");

// ---------- Ringkasan ----------
$stat = fetch_one("
    SELECT COUNT(*) AS jenis,
           COALESCE(SUM(stok), 0) AS unit,
           COALESCE(SUM(stok * harga_beli), 0) AS nilai,
           COALESCE(SUM(stok > 0 AND stok <= stok_minimum), 0) AS menipis,
           COALESCE(SUM(stok = 0), 0) AS habis
    FROM barang
");

$kategori = get_kategori();

function page_url($p)
{
    $params = array(
        'q' => get('q'), 'kategori' => get('kategori'), 'status' => get('status'),
        'sort' => get('sort'), 'page' => $p,
    );
    return 'index.php?' . http_build_query(array_filter($params, 'strlen'));
}

include __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div>
        <h1>Data Barang</h1>
        <p class="muted">Daftar inventaris barang gudang beserta stok terkini.</p>
    </div>
    <a href="tambah.php" class="btn btn-primary">+ Tambah Barang</a>
</div>

<div class="stats">
    <div class="stat"><span class="stat-label">Jenis Barang</span><span class="stat-value"><?= angka($stat['jenis']) ?></span></div>
    <div class="stat"><span class="stat-label">Total Unit Stok</span><span class="stat-value"><?= angka($stat['unit']) ?></span></div>
    <div class="stat"><span class="stat-label">Nilai Persediaan (harga beli)</span><span class="stat-value"><?= rupiah($stat['nilai']) ?></span></div>
    <a class="stat stat-warn" href="index.php?status=menipis"><span class="stat-label">Stok Menipis</span><span class="stat-value"><?= angka($stat['menipis']) ?></span></a>
    <a class="stat stat-danger" href="index.php?status=habis"><span class="stat-label">Stok Habis</span><span class="stat-value"><?= angka($stat['habis']) ?></span></a>
</div>

<form class="filters card" method="get" action="index.php">
    <div class="form-group grow">
        <label for="q">Cari</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Kode, nama, atau lokasi rak…">
    </div>
    <div class="form-group">
        <label for="kategori">Kategori</label>
        <select id="kategori" name="kategori">
            <option value="">Semua kategori</option>
            <?php foreach ($kategori as $k): ?>
                <option value="<?= (int) $k['id_kategori'] ?>" <?= $f_kat === (string) $k['id_kategori'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="status">Status stok</label>
        <select id="status" name="status">
            <option value="">Semua status</option>
            <?php foreach (array('aman' => 'Aman', 'menipis' => 'Menipis', 'habis' => 'Habis') as $v => $l): ?>
                <option value="<?= $v ?>" <?= $f_status === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="sort">Urutkan</label>
        <select id="sort" name="sort">
            <?php foreach (array('kode' => 'Kode', 'nama' => 'Nama (A–Z)', 'stok_asc' => 'Stok terkecil', 'stok_desc' => 'Stok terbesar', 'terbaru' => 'Terbaru masuk') as $v => $l): ?>
                <option value="<?= $v ?>" <?= $sort === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a href="index.php" class="btn btn-ghost">Reset</a>
    </div>
</form>

<div class="card table-card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th class="num">No</th>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th class="num">Stok</th>
                <th>Satuan</th>
                <th class="num">Harga Beli</th>
                <th class="num">Harga Jual</th>
                <th>Rak</th>
                <th>Tgl Masuk</th>
                <th class="center">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="11" class="empty">Tidak ada data barang yang cocok.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r): list($st_class, $st_label) = status_stok($r['stok'], $r['stok_minimum']); ?>
                <tr>
                    <td class="num muted"><?= $offset + $i + 1 ?></td>
                    <td><code><?= e($r['kode_barang']) ?></code></td>
                    <td class="strong"><?= e($r['nama_barang']) ?></td>
                    <td><?= e($r['nama_kategori']) ?></td>
                    <td class="num">
                        <span class="strong"><?= angka($r['stok']) ?></span>
                        <span class="badge badge-<?= $st_class ?>" title="Stok minimum: <?= angka($r['stok_minimum']) ?>"><?= $st_label ?></span>
                    </td>
                    <td><?= e($r['nama_satuan']) ?></td>
                    <td class="num"><?= rupiah($r['harga_beli']) ?></td>
                    <td class="num"><?= rupiah($r['harga_jual']) ?></td>
                    <td><?= e($r['lokasi_rak']) ?></td>
                    <td class="nowrap"><?= tgl_indo($r['tanggal_masuk']) ?></td>
                    <td class="actions">
                        <a href="transaksi.php?id_barang=<?= (int) $r['id_barang'] ?>" class="btn btn-xs btn-ghost" title="Riwayat transaksi">Riwayat</a>
                        <a href="edit.php?id=<?= (int) $r['id_barang'] ?>" class="btn btn-xs btn-warning">Edit</a>
                        <form method="post" action="hapus.php" class="inline"
                              onsubmit="return confirm(<?= e(json_encode('Yakin ingin menghapus barang "' . $r['kode_barang'] . ' - ' . $r['nama_barang'] . '"?')) ?>);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id_barang'] ?>">
                            <button type="submit" class="btn btn-xs btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="table-foot">
        <span class="muted">Menampilkan <?= $total_rows ? ($offset + 1) . '–' . ($offset + count($rows)) : 0 ?> dari <?= angka($total_rows) ?> barang</span>
        <?php if ($total_pages > 1): ?>
            <nav class="pagination">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="<?= e(page_url($p)) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
