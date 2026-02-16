<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';

$cid = (int)($_GET['id'] ?? 0);
$cat = $pdo->prepare("SELECT * FROM categories WHERE id=?");
$cat->execute([$cid]);
$cat = $cat->fetch();
if(!$cat) die("ไม่พบประเภทสินค้า");

$stmt = $pdo->prepare("SELECT * FROM products WHERE category_id=? ORDER BY id DESC");
$stmt->execute([$cid]);
$products = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';
?>
<h2>ประเภทสินค้า: <?= h($cat['name']) ?></h2>

<table class="table">
  <tr><th>สินค้า</th><th>ราคา</th><th></th></tr>
  <?php foreach($products as $p): ?>
    <tr>
      <td><a href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$p['id'] ?>"><?= h($p['name']) ?></a></td>
      <td><?= number_format((float)$p['price'], 2) ?></td>
      <td>
        <form method="post" action="<?= BASE_URL ?>/public/cart.php">
          <input type="hidden" name="add_id" value="<?= (int)$p['id'] ?>">
          <button type="submit">หยิบลงตะกร้า</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php include __DIR__ . '/../templates/footer.php'; ?>