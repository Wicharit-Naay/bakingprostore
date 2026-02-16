<?php require_once __DIR__ . '/../config/config.php'; ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>BakingProStore</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <a href="<?= BASE_URL ?>/public/index.php">หน้าร้าน</a> |
    <a href="<?= BASE_URL ?>/public/cart.php">ตะกร้า</a> |
    <?php if(isset($_SESSION['user'])): ?>
      <a href="<?= BASE_URL ?>/public/orders.php">ออเดอร์ของฉัน</a> |
      <a href="<?= BASE_URL ?>/public/profile.php">โปรไฟล์</a> |
      <a href="<?= BASE_URL ?>/public/logout.php">ออกจากระบบ</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/public/register.php">สมัครสมาชิก</a> |
      <a href="<?= BASE_URL ?>/public/login.php">เข้าสู่ระบบ</a>
    <?php endif; ?>
  </div>
  <hr>