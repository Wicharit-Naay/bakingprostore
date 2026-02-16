<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth.php';
require_admin();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin</title></head><body>
<h2>หลังร้าน</h2>
<p>สวัสดี, <?= htmlspecialchars($_SESSION['user']['name']) ?></p>
<ul>
  <li><a href="<?= BASE_URL ?>/admin/products/index.php">จัดการสินค้า</a></li>
  <li><a href="<?= BASE_URL ?>/admin/categories/index.php">จัดการประเภทสินค้า</a></li>
  <li><a href="<?= BASE_URL ?>/admin/customers/index.php">แสดงข้อมูลลูกค้า</a></li>
  <li><a href="<?= BASE_URL ?>/public/logout.php">ออกจากระบบ</a></li>
</ul>
</body></html>