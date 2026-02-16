<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
  SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone, u.address AS customer_address
  FROM orders o
  JOIN users u ON u.id = o.user_id
  WHERE o.id=?
");
$stmt->execute([$id]);
$o = $stmt->fetch();
if(!$o) die("ไม่พบออเดอร์");

$items = $pdo->prepare("
  SELECT oi.*, p.name AS product_name
  FROM order_items oi
  JOIN products p ON p.id = oi.product_id
  WHERE oi.order_id=?
");
$items->execute([$id]);
$items = $items->fetchAll();

require_once __DIR__ . '/../../templates/admin_header.php';
?>
<h2>รายละเอียดออเดอร์ #<?= (int)$o['id'] ?></h2>

<div class="box">
  ลูกค้า: <b><?= h($o['customer_name']) ?></b> (<?= h($o['customer_email']) ?>)<br>
  ยอดรวม: <?= number_format((float)$o['total'],2) ?><br>
  สถานะปัจจุบัน: <b><?= h($o['status']) ?></b><br>
  วันที่สั่ง: <?= h($o['created_at']) ?><br>
</div>

<div class="box">
  <b>ที่อยู่จัดส่ง</b><br>
  <?php
    // ถ้ามี shipping_* ให้ใช้ก่อน ไม่งั้น fallback ไป user.address
    $shipName = $o['shipping_name'] ?: $o['customer_name'];
    $shipPhone = $o['shipping_phone'] ?: $o['customer_phone'];
    $shipAddr = $o['shipping_address'] ?: $o['customer_address'];
  ?>
  ชื่อผู้รับ: <?= h($shipName) ?><br>
  เบอร์: <?= h($shipPhone) ?><br>
  ที่อยู่: <pre style="margin:0;white-space:pre-wrap;"><?= h($shipAddr) ?></pre>
</div>

<div class="box">
  <b>รายการสินค้า</b>
  <table class="table">
    <tr><th>สินค้า</th><th>ราคา</th><th>จำนวน</th><th>รวม</th></tr>
    <?php foreach($items as $it): ?>
      <tr>
        <td><?= h($it['product_name']) ?></td>
        <td><?= number_format((float)$it['price'],2) ?></td>
        <td><?= (int)$it['qty'] ?></td>
        <td><?= number_format((float)$it['qty']*(float)$it['price'],2) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="box">
  <form method="post" action="<?= BASE_URL ?>/admin/orders/update_status.php">
    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
    เปลี่ยนสถานะ:
    <select name="status">
      <?php foreach(['pending','paid','shipping','completed','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">อัปเดต</button>
    <a href="<?= BASE_URL ?>/admin/orders/index.php">กลับ</a>
  </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>