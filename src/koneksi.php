<?php
/**
 * Koneksi database memakai ekstensi mysql_* (PHP 5.6).
 * Semua query aplikasi melewati fungsi di file ini.
 */
error_reporting(E_ALL & ~E_DEPRECATED); // mysql_* deprecated sejak PHP 5.5

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'db_gudang';

$koneksi = @mysql_connect($db_host, $db_user, $db_pass);
if (!$koneksi) {
    die('Koneksi ke MySQL gagal: ' . htmlspecialchars(mysql_error(), ENT_QUOTES, 'UTF-8'));
}
if (!mysql_select_db($db_name, $koneksi)) {
    die('Database tidak ditemukan: ' . htmlspecialchars(mysql_error($koneksi), ENT_QUOTES, 'UTF-8'));
}
mysql_set_charset('utf8mb4', $koneksi);
mysql_query("SET time_zone = '+07:00'", $koneksi);

/** Escape string untuk dipakai di dalam tanda kutip SQL (anti SQL Injection). */
function esc($nilai)
{
    global $koneksi;
    return mysql_real_escape_string((string) $nilai, $koneksi);
}

/** Escape untuk pola LIKE: juga meng-escape wildcard % dan _. */
function esc_like($nilai)
{
    return addcslashes(esc($nilai), '%_');
}

/** Jalankan query; lempar Exception (dengan kode errno MySQL) bila gagal. */
function db_query($sql)
{
    global $koneksi;
    $hasil = mysql_query($sql, $koneksi);
    if ($hasil === false) {
        $errno = mysql_errno($koneksi);
        error_log('[SQL ' . $errno . '] ' . mysql_error($koneksi) . ' | ' . $sql);
        throw new Exception('Terjadi kesalahan pada database.', $errno);
    }
    return $hasil;
}

/** Ambil semua baris sebagai array asosiatif. */
function fetch_all($sql)
{
    $hasil = db_query($sql);
    $rows = array();
    while ($row = mysql_fetch_array($hasil, MYSQL_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

/** Ambil satu baris (atau null). */
function fetch_one($sql)
{
    $hasil = db_query($sql);
    $row = mysql_fetch_array($hasil, MYSQL_ASSOC);
    return $row ? $row : null;
}

function db_insert_id()
{
    global $koneksi;
    return mysql_insert_id($koneksi);
}

function db_affected_rows()
{
    global $koneksi;
    return mysql_affected_rows($koneksi);
}

function db_begin()
{
    db_query('START TRANSACTION');
}

function db_commit()
{
    db_query('COMMIT');
}

function db_rollback()
{
    global $koneksi;
    @mysql_query('ROLLBACK', $koneksi);
}
