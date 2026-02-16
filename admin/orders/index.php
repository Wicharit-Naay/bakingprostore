<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$orders = $pdo->query("
  SELECT o.*, u.name AS customer_name, u.email AS customer_email
  FROM orders o
  JOIN users u ON u.id = o.user_id
  ORDER BY o.id DESC
")->fetchAll();

require_once __DIR__ . '/../../templates/admin_header.php';
?>
<h2>จัดการออเดอร์</h2>
<p><a href="<?= BASE_URL ?>/admin/index.php">กลับเมนู</a></p>

<table id="dt" class="table">
  <thead>
    <tr>
      <th>Order</th><th>ลูกค้า</th><th>ยอดรวม</th><th>สถานะ</th><th>วันที่</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach($orders as $o): ?>
    <tr>
      <td>#<?= (int)$o['id'] ?></td>
      <td><?= h($o['customer_name']) ?> <span class="small">(<?= h($o['customer_email']) ?>)</span></td>
      <td><?= number_format((float)$o['total'],2) ?></td>
      <td><?= h($o['status']) ?></td>
      <td><?= h($o['created_at']) ?></td>
      <td><a href="<?= BASE_URL ?>/admin/orders/show.php?id=<?= (int)$o['id'] ?>">ดูรายละเอียด</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>