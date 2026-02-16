<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/cart.php';

cart_init();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['add_id'])) {
    cart_add($_POST['add_id'], $_POST['qty'] ?? 1);
  }
  if (isset($_POST['remove_id'])) {
    cart_remove($_POST['remove_id']);
  }
  if (isset($_POST['clear'])) {
    cart_clear();
  }
  redirect('/public/cart.php');
}

$ids = array_keys($_SESSION['cart']);
$items = [];
$total = 0;

if (count($ids) > 0) {
  $in = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
  $stmt->execute($ids);
  $items = $stmt->fetchAll();

  foreach ($items as &$p) {
    $qty = $_SESSION['cart'][$p['id']] ?? 0;
    $p['qty'] = $qty;
    $p['sub'] = $qty * (float)$p['price'];
    $total += $p['sub'];
  }
}

include __DIR__ . '/../templates/header.php';
?>
<h2>ตะกร้าสินค้า</h2>

<div class="box">
  <?php if(!$items): ?>
    ไม่มีสินค้าในตะกร้า
  <?php else: ?>
    <table class="table">
      <tr><th>สินค้า</th><th>ราคา</th><th>จำนวน</th><th>รวม</th><th></th></tr>
      <?php foreach($items as $p): ?>
        <tr>
          <td><?= h($p['name']) ?></td>
          <td><?= number_format((float)$p['price'],2) ?></td>
          <td><?= (int)$p['qty'] ?></td>
          <td><?= number_format((float)$p['sub'],2) ?></td>
          <td>
            <form method="post">
              <input type="hidden" name="remove_id" value="<?= (int)$p['id'] ?>">
              <button type="submit">ลบ</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <tr><th colspan="3">ยอดรวม</th><th><?= number_format((float)$total,2) ?></th><th></th></tr>
    </table>

    <form method="post" style="margin-top:10px;">
      <button name="clear" value="1">ล้างตะกร้า</button>
      <a href="<?= BASE_URL ?>/public/checkout.php">ไปหน้าสั่งซื้อ</a>
    </form>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>