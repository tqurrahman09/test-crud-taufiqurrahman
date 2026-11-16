<?php
/**
 * Partial form barang.
 * Variabel: $data, $errors, $kategori, $satuan, $mode ('tambah'|'edit'), $barang (mode edit)
 */
$is_edit = ($mode === 'edit');
?>
<?php if (isset($errors['_'])): ?>
    <div class="alert alert-danger"><?= e($errors['_']) ?></div>
<?php elseif ($errors): ?>
    <div class="alert alert-danger">Data belum valid. Periksa kembali isian yang ditandai merah.</div>
<?php endif; ?>

<form method="post" class="card form" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="form-group">
            <label for="kode_barang">Kode Barang <span class="req">*</span></label>
            <input type="text" id="kode_barang" name="kode_barang" maxlength="20" required
                   class="upper<?= err_class($errors, 'kode_barang') ?>" value="<?= e($data['kode_barang']) ?>" placeholder="BRG-0011">
            <?= field_error($errors, 'kode_barang') ?>
        </div>

        <div class="form-group">
            <label for="nama_barang">Nama Barang <span class="req">*</span></label>
            <input type="text" id="nama_barang" name="nama_barang" maxlength="100" required
                   class="<?= err_class($errors, 'nama_barang') ?>" value="<?= e($data['nama_barang']) ?>" placeholder="Nama & spesifikasi barang">
            <?= field_error($errors, 'nama_barang') ?>
        </div>

        <div class="form-group">
            <label for="id_kategori">Kategori <span class="req">*</span></label>
            <select id="id_kategori" name="id_kategori" required class="<?= err_class($errors, 'id_kategori') ?>">
                <option value="">— Pilih kategori —</option>
                <?php foreach ($kategori as $k): ?>
                    <option value="<?= (int) $k['id_kategori'] ?>" <?= (string) $data['id_kategori'] === (string) $k['id_kategori'] ? 'selected' : '' ?>>
                        <?= e($k['nama_kategori']) ?> (<?= e($k['kode_kategori']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'id_kategori') ?>
        </div>

        <div class="form-group">
            <label for="id_satuan">Satuan <span class="req">*</span></label>
            <select id="id_satuan" name="id_satuan" required class="<?= err_class($errors, 'id_satuan') ?>">
                <option value="">— Pilih satuan —</option>
                <?php foreach ($satuan as $s): ?>
                    <option value="<?= (int) $s['id_satuan'] ?>" <?= (string) $data['id_satuan'] === (string) $s['id_satuan'] ? 'selected' : '' ?>>
                        <?= e($s['nama_satuan']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'id_satuan') ?>
        </div>

        <?php if ($is_edit): ?>
            <div class="form-group">
                <label>Stok Saat Ini</label>
                <input type="text" value="<?= angka($barang['stok']) ?>" disabled>
                <div class="hint">Stok hanya berubah lewat <a href="transaksi_tambah.php?id_barang=<?= (int) $barang['id_barang'] ?>">transaksi masuk/keluar</a>.</div>
            </div>
        <?php else: ?>
            <div class="form-group">
                <label for="stok_awal">Stok Awal <span class="req">*</span></label>
                <input type="number" id="stok_awal" name="stok_awal" min="0" max="1000000" step="1" required
                       class="<?= err_class($errors, 'stok_awal') ?>" value="<?= e($data['stok_awal']) ?>">
                <div class="hint">Jika &gt; 0, otomatis dicatat sebagai transaksi masuk "Stok awal".</div>
                <?= field_error($errors, 'stok_awal') ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="stok_minimum">Stok Minimum <span class="req">*</span></label>
            <input type="number" id="stok_minimum" name="stok_minimum" min="0" max="1000000" step="1" required
                   class="<?= err_class($errors, 'stok_minimum') ?>" value="<?= e($data['stok_minimum']) ?>">
            <div class="hint">Batas peringatan stok menipis.</div>
            <?= field_error($errors, 'stok_minimum') ?>
        </div>

        <div class="form-group">
            <label for="harga_beli">Harga Beli (Rp) <span class="req">*</span></label>
            <input type="number" id="harga_beli" name="harga_beli" min="0" step="0.01" required
                   class="<?= err_class($errors, 'harga_beli') ?>" value="<?= e($data['harga_beli']) ?>" placeholder="contoh: 45000">
            <?= field_error($errors, 'harga_beli') ?>
        </div>

        <div class="form-group">
            <label for="harga_jual">Harga Jual (Rp) <span class="req">*</span></label>
            <input type="number" id="harga_jual" name="harga_jual" min="0" step="0.01" required
                   class="<?= err_class($errors, 'harga_jual') ?>" value="<?= e($data['harga_jual']) ?>" placeholder="contoh: 52000">
            <?= field_error($errors, 'harga_jual') ?>
        </div>

        <div class="form-group">
            <label for="lokasi_rak">Lokasi Rak <span class="req">*</span></label>
            <input type="text" id="lokasi_rak" name="lokasi_rak" maxlength="20" required
                   class="upper<?= err_class($errors, 'lokasi_rak') ?>" value="<?= e($data['lokasi_rak']) ?>" placeholder="A-01">
            <?= field_error($errors, 'lokasi_rak') ?>
        </div>

        <div class="form-group">
            <label for="tanggal_masuk">Tanggal Masuk <span class="req">*</span></label>
            <input type="date" id="tanggal_masuk" name="tanggal_masuk" max="<?= date('Y-m-d') ?>" required
                   class="<?= err_class($errors, 'tanggal_masuk') ?>" value="<?= e($data['tanggal_masuk']) ?>">
            <?= field_error($errors, 'tanggal_masuk') ?>
        </div>
    </div>

    <div class="form-footer">
        <a href="index.php" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Simpan Perubahan' : 'Simpan Barang' ?></button>
    </div>
</form>
