<?php
$page_title = isset($page_title) ? $page_title : 'Inventaris Gudang';
$active = isset($active) ? $active : '';
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> · Inventaris Gudang</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="index.php"><span class="brand-logo">▦</span> Inventaris Gudang</a>
        <nav class="nav">
            <a href="index.php" class="<?= $active === 'barang' ? 'active' : '' ?>">Data Barang</a>
            <a href="transaksi.php" class="<?= $active === 'transaksi' ? 'active' : '' ?>">Transaksi</a>
            <a href="transaksi_tambah.php" class="btn btn-sm btn-light">+ Transaksi</a>
        </nav>
    </div>
</header>
<main class="container">
<?php foreach (get_flash() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
