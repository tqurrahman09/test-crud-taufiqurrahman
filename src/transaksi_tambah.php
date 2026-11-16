<?php
require __DIR__ . '/inc/functions.php';

$page_title = 'Input Transaksi';
$active = 'transaksi';

$barang_list = fetch_all("
    SELECT b.id_barang, b.kode_barang, b.nama_barang, b.stok, b.harga_beli, b.harga_jual, b.tanggal_masuk,
           s.nama_satuan
    FROM barang b
    INNER JOIN satuan s ON s.id_satuan = b.id_satuan
    ORDER BY b.nama_barang
");

$errors = array();
$data = array(
    'id_barang'    => ctype_digit(get('id_barang')) ? get('id_barang') : '',
    'jenis'        => in_array(get('jenis'), array('masuk', 'keluar'), true) ? get('jenis') : 'masuk',
    'tanggal'      => date('Y-m-d'),
    'jumlah'       => '',
    'harga_satuan' => '',
    'keterangan'   => '',
);

if (is_post()) {
    $data = array(
        'id_barang'    => input('id_barang'),
        'jenis'        => input('jenis'),
        'tanggal'      => input('tanggal'),
        'jumlah'       => input('jumlah'),
        'harga_satuan' => input('harga_satuan'),
        'keterangan'   => preg_replace('/\s+/', ' ', input('keterangan')),
    );

    if (!csrf_valid()) {
        $errors['_'] = 'Sesi formulir kedaluwarsa, silakan kirim ulang.';
    }

    // ----- Validasi format -----
    $label = array('id_barang' => 'Barang', 'jenis' => 'Jenis transaksi', 'tanggal' => 'Tanggal',
                   'jumlah' => 'Jumlah', 'harga_satuan' => 'Harga satuan', 'keterangan' => 'Keterangan');
    foreach ($label as $f => $l) {
        if ($data[$f] === '') {
            $errors[$f] = $l . ' wajib diisi.';
        }
    }
    if (!isset($errors['id_barang']) && !ctype_digit($data['id_barang'])) {
        $errors['id_barang'] = 'Barang tidak valid.';
    }
    if (!isset($errors['jenis']) && !in_array($data['jenis'], array('masuk', 'keluar'), true)) {
        $errors['jenis'] = 'Jenis transaksi harus "masuk" atau "keluar".';
    }
    if (!isset($errors['tanggal'])) {
        if (!is_valid_date($data['tanggal'])) {
            $errors['tanggal'] = 'Tanggal tidak valid (format YYYY-MM-DD).';
        } elseif ($data['tanggal'] > date('Y-m-d')) {
            $errors['tanggal'] = 'Tanggal transaksi tidak boleh melebihi hari ini.';
        }
    }
    if (!isset($errors['jumlah'])) {
        if (!is_whole_number($data['jumlah'])) {
            $errors['jumlah'] = 'Jumlah harus bilangan bulat (tanpa titik/koma).';
        } elseif ((int) $data['jumlah'] < 1) {
            $errors['jumlah'] = 'Jumlah minimal 1.';
        } elseif ((int) $data['jumlah'] > 1000000) {
            $errors['jumlah'] = 'Jumlah maksimal 1.000.000 per transaksi.';
        }
    }
    if (!isset($errors['harga_satuan'])) {
        if (!is_money($data['harga_satuan'])) {
            $errors['harga_satuan'] = 'Harga harus berupa angka (tanpa titik ribuan), maks. 2 desimal.';
        } elseif ((float) $data['harga_satuan'] <= 0) {
            $errors['harga_satuan'] = 'Harga harus lebih dari 0.';
        }
    }
    if (!isset($errors['keterangan'])) {
        $len = str_len($data['keterangan']);
        if ($len < 3 || $len > 255) {
            $errors['keterangan'] = 'Keterangan 3–255 karakter.';
        }
    }

    // ----- Simpan (atomik: kunci baris barang, cek stok, update stok, insert transaksi) -----
    if (!$errors) {
        $id_barang = (int) $data['id_barang'];
        $jumlah    = (int) $data['jumlah'];
        try {
            db_begin();
            $b = fetch_one("SELECT id_barang, nama_barang, stok, tanggal_masuk FROM barang WHERE id_barang = $id_barang FOR UPDATE");

            if (!$b) {
                $errors['id_barang'] = 'Barang tidak ditemukan.';
            } elseif ($data['tanggal'] < $b['tanggal_masuk']) {
                $errors['tanggal'] = 'Tanggal transaksi tidak boleh sebelum tanggal barang terdaftar (' . tgl_indo($b['tanggal_masuk']) . ').';
            } elseif ($data['jenis'] === 'keluar' && $jumlah > (int) $b['stok']) {
                $errors['jumlah'] = 'Stok tidak mencukupi. Stok tersedia: ' . angka($b['stok']) . '.';
            }

            if ($errors) {
                db_rollback();
            } else {
                $op = $data['jenis'] === 'masuk' ? '+' : '-';
                db_query("UPDATE barang SET stok = stok $op $jumlah WHERE id_barang = $id_barang");

                $no = generate_no_transaksi($data['jenis'], $data['tanggal']);
                db_query("
                    INSERT INTO transaksi (no_transaksi, id_barang, jenis, tanggal, jumlah, harga_satuan, keterangan)
                    VALUES ('" . esc($no) . "', $id_barang, '" . esc($data['jenis']) . "',
                            '" . esc($data['tanggal']) . "', $jumlah,
                            '" . esc($data['harga_satuan']) . "', '" . esc($data['keterangan']) . "')
                ");
                db_commit();

                set_flash('success', 'Transaksi ' . $no . ' tersimpan. Stok "' . $b['nama_barang'] . '" '
                    . ($op === '+' ? 'bertambah' : 'berkurang') . ' ' . angka($jumlah) . '.');
                redirect('transaksi.php');
            }
        } catch (Exception $ex) {
            db_rollback();
            $errors['_'] = 'Gagal menyimpan transaksi. Silakan coba lagi.';
        }
    }
}

include __DIR__ . '/inc/header.php';
?>
<div class="page-head">
    <div>
        <h1>Input Transaksi</h1>
        <p class="muted">Catat barang masuk / keluar. Stok barang akan diperbarui otomatis.</p>
    </div>
    <a href="transaksi.php" class="btn btn-ghost">&larr; Daftar Transaksi</a>
</div>

<?php if (isset($errors['_'])): ?>
    <div class="alert alert-danger"><?= e($errors['_']) ?></div>
<?php elseif ($errors): ?>
    <div class="alert alert-danger">Data belum valid. Periksa kembali isian yang ditandai merah.</div>
<?php endif; ?>

<form method="post" class="card form" novalidate id="form-transaksi">
    <?= csrf_field() ?>

    <div class="form-group">
        <label>Jenis Transaksi <span class="req">*</span></label>
        <div class="segmented<?= err_class($errors, 'jenis') ?>">
            <label class="seg seg-in">
                <input type="radio" name="jenis" value="masuk" <?= $data['jenis'] === 'masuk' ? 'checked' : '' ?>>
                <span>▼ Barang Masuk</span>
            </label>
            <label class="seg seg-out">
                <input type="radio" name="jenis" value="keluar" <?= $data['jenis'] === 'keluar' ? 'checked' : '' ?>>
                <span>▲ Barang Keluar</span>
            </label>
        </div>
        <?= field_error($errors, 'jenis') ?>
    </div>

    <div class="form-grid">
        <div class="form-group span-2">
            <label for="id_barang">Barang <span class="req">*</span></label>
            <select id="id_barang" name="id_barang" required class="<?= err_class($errors, 'id_barang') ?>">
                <option value="">— Pilih barang —</option>
                <?php foreach ($barang_list as $b): ?>
                    <option value="<?= (int) $b['id_barang'] ?>"
                            data-stok="<?= (int) $b['stok'] ?>"
                            data-satuan="<?= e($b['nama_satuan']) ?>"
                            data-beli="<?= e(num_input($b['harga_beli'])) ?>"
                            data-jual="<?= e(num_input($b['harga_jual'])) ?>"
                            data-tglmasuk="<?= e($b['tanggal_masuk']) ?>"
                        <?= (string) $data['id_barang'] === (string) $b['id_barang'] ? 'selected' : '' ?>>
                        <?= e($b['kode_barang'] . ' — ' . $b['nama_barang']) ?> (stok: <?= angka($b['stok']) ?> <?= e($b['nama_satuan']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint" id="info-stok"></div>
            <?= field_error($errors, 'id_barang') ?>
        </div>

        <div class="form-group">
            <label for="tanggal">Tanggal <span class="req">*</span></label>
            <input type="date" id="tanggal" name="tanggal" max="<?= date('Y-m-d') ?>" required
                   class="<?= err_class($errors, 'tanggal') ?>" value="<?= e($data['tanggal']) ?>">
            <?= field_error($errors, 'tanggal') ?>
        </div>

        <div class="form-group">
            <label for="jumlah">Jumlah <span class="req">*</span></label>
            <input type="number" id="jumlah" name="jumlah" min="1" max="1000000" step="1" required
                   class="<?= err_class($errors, 'jumlah') ?>" value="<?= e($data['jumlah']) ?>">
            <?= field_error($errors, 'jumlah') ?>
        </div>

        <div class="form-group">
            <label for="harga_satuan">Harga Satuan (Rp) <span class="req">*</span></label>
            <input type="number" id="harga_satuan" name="harga_satuan" min="0" step="0.01" required
                   class="<?= err_class($errors, 'harga_satuan') ?>" value="<?= e($data['harga_satuan']) ?>">
            <div class="hint">Otomatis terisi harga beli (masuk) / harga jual (keluar), bisa diubah.</div>
            <?= field_error($errors, 'harga_satuan') ?>
        </div>

        <div class="form-group">
            <label>Total Nilai</label>
            <input type="text" id="total" value="Rp 0" disabled>
        </div>

        <div class="form-group span-2">
            <label for="keterangan">Keterangan <span class="req">*</span></label>
            <input type="text" id="keterangan" name="keterangan" maxlength="255" required
                   class="<?= err_class($errors, 'keterangan') ?>" value="<?= e($data['keterangan']) ?>"
                   placeholder="Nama supplier (masuk) / tujuan pengiriman (keluar)">
            <?= field_error($errors, 'keterangan') ?>
        </div>
    </div>

    <div class="form-footer">
        <a href="transaksi.php" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan Transaksi</button>
    </div>
</form>

<script>
(function () {
    var form   = document.getElementById('form-transaksi');
    var barang = document.getElementById('id_barang');
    var jumlah = document.getElementById('jumlah');
    var harga  = document.getElementById('harga_satuan');
    var total  = document.getElementById('total');
    var info   = document.getElementById('info-stok');
    var hargaDiubahManual = harga.value !== '';

    function jenis() {
        var r = form.querySelector('input[name="jenis"]:checked');
        return r ? r.value : 'masuk';
    }
    function rupiah(n) {
        return 'Rp ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function hitungTotal() {
        var t = (parseFloat(jumlah.value) || 0) * (parseFloat(harga.value) || 0);
        total.value = rupiah(t);
    }
    function sinkron(isiHarga) {
        var opt = barang.options[barang.selectedIndex];
        if (!opt || !opt.value) { info.textContent = ''; jumlah.removeAttribute('max'); hitungTotal(); return; }
        var stok = parseInt(opt.getAttribute('data-stok'), 10);
        info.textContent = 'Stok saat ini: ' + stok.toLocaleString('id-ID') + ' ' + opt.getAttribute('data-satuan')
            + (jenis() === 'keluar' ? ' — maksimal keluar ' + stok.toLocaleString('id-ID') : '');
        info.className = 'hint' + (jenis() === 'keluar' && stok === 0 ? ' text-danger' : '');
        jumlah.max = jenis() === 'keluar' ? stok : 1000000;
        if (isiHarga) {
            harga.value = opt.getAttribute(jenis() === 'masuk' ? 'data-beli' : 'data-jual');
        }
        hitungTotal();
    }

    barang.addEventListener('change', function () { sinkron(true); });
    Array.prototype.forEach.call(form.querySelectorAll('input[name="jenis"]'), function (r) {
        r.addEventListener('change', function () { sinkron(true); });
    });
    jumlah.addEventListener('input', hitungTotal);
    harga.addEventListener('input', hitungTotal);
    sinkron(!hargaDiubahManual);
})();
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
