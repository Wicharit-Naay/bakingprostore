<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name
                       FROM products p JOIN categories c ON c.id=p.category_id
                       WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if(!$p) die("ไม่พบสินค้า");

include __DIR__ . '/../templates/header.php';
?>
<h2>รายละเอียดสินค้า</h2>

<div class="box">
  <b><?= h($p['name']) ?></b><br>
  ประเภท: <?= h($p['category_name']) ?><br>
  ราคา: <?= number_format((float)$p['price'], 2) ?><br>
  คงเหลือ: <?= (int)$p['stock'] ?><br>
  <p><?= nl2br(h($p['description'])) ?></p>

  <form method="post" action="<?= BASE_URL ?>/public/cart.php">
    <input type="hidden" name="add_id" value="<?= (int)$p['id'] ?>">
    <input type="number" name="qty" value="1" min="1">
    <button type="submit">หยิบลงตะกร้า</button>
  </form>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>