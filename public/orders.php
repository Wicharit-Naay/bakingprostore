<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC");
$stmt->execute([$_SESSION['user']['id']]);
$orders = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';
?>
<h2>ประวัติการสั่งซื้อ</h2>

<table class="table">
  <tr><th>เลขออเดอร์</th><th>ยอดรวม</th><th>สถานะ</th><th>วันที่</th><th></th></tr>
  <?php foreach($orders as $o): ?>
    <tr>
      <td>#<?= (int)$o['id'] ?></td>
      <td><?= number_format((float)$o['total'],2) ?></td>
      <td><?= h($o['status']) ?></td>
      <td><?= h($o['created_at']) ?></td>
      <td><a href="<?= BASE_URL ?>/public/order_status.php?id=<?= (int)$o['id'] ?>">เช็คสถานะ</a></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php include __DIR__ . '/../templates/footer.php'; ?>