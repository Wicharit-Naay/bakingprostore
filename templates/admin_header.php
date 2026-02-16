<?php
require_once __DIR__ . '/../config/config.php';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>Admin</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">

  <!-- DataTables (CDN) -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <a href="<?= BASE_URL ?>/admin/index.php">เมนูหลังร้าน</a> |
    <a href="<?= BASE_URL ?>/admin/products/index.php">สินค้า</a> |
    <a href="<?= BASE_URL ?>/admin/categories/index.php">ประเภท</a> |
    <a href="<?= BASE_URL ?>/admin/customers/index.php">ลูกค้า</a> |
    <a href="<?= BASE_URL ?>/admin/orders/index.php">ออเดอร์</a> |
    <a href="<?= BASE_URL ?>/admin/logout.php">ออกจากระบบ</a>
  </div>
  <hr>

<script>
$(function(){
  if ($('#dt').length) $('#dt').DataTable({ pageLength: 10 });
});
</script>