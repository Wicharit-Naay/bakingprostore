<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

include __DIR__ . '/../templates/header.php';

$popular = $pdo->query("
  SELECT p.id, p.name, p.price, SUM(oi.qty) AS sold
  FROM order_items oi
  JOIN products p ON p.id = oi.product_id
  GROUP BY p.id, p.name, p.price
  ORDER BY sold DESC
  LIMIT 5
")->fetchAll();

$products = $pdo->query("
  SELECT p.id, p.name, p.price, p.image, c.name AS category_name
  FROM products p
  JOIN categories c ON c.id = p.category_id
  ORDER BY p.id DESC
  LIMIT 10
")->fetchAll();
?>

<h2>หน้าร้าน</h2>

<div class="box">
  <form method="get" action="<?= BASE_URL ?>/public/search.php">
    <input type="text" name="q" placeholder="ค้นหาสินค้า...">
    <button type="submit">ค้นหา</button>
  </form>
</div>

<div class="box">
  <b>หมวดหมู่สินค้า</b><br>
  <?php foreach($cats as $c): ?>
    <a href="<?= BASE_URL ?>/public/category.php?id=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a>
    |
  <?php endforeach; ?>
</div>

<td style="width:90px;">
  <?php if(!empty($p['image'])): ?>
    <img
      src="<?= BASE_URL . '/' . ltrim(h($p['image']), '/') ?>"
      style="width:70px;height:70px;object-fit:cover;border:1px solid #ddd;"
      alt=""
    >
  <?php else: ?>
    <span class="small">ไม่มีรูป</span>
  <?php endif; ?>
</td>

<div class="box">
  <b>สินค้าแนะนำ</b>
  <table class="table">
    <tr><th>รูป</th><th>สินค้า</th><th>ประเภท</th><th>ราคา</th><th></th></tr>

    <?php foreach($products as $p): ?>
      <tr>
        <td style="width:90px;">
          <?php if(!empty($p['image'])): ?>
            <img
              src="<?= BASE_URL . '/' . ltrim(h($p['image']), '/') ?>"
              style="width:70px;height:70px;object-fit:cover;border:1px solid #ddd;"
              alt=""
            >
          <?php else: ?>
            <span class="small">ไม่มีรูป</span>
          <?php endif; ?>
        </td>

        <td><a href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$p['id'] ?>"><?= h($p['name']) ?></a></td>
        <td><?= h($p['category_name']) ?></td>
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
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>