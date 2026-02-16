<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

$q = trim($_GET['q'] ?? '');
$products = [];

if ($q !== '') {
  $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name
                         FROM products p JOIN categories c ON c.id=p.category_id
                         WHERE p.name LIKE ?
                         ORDER BY p.id DESC");
  $stmt->execute(['%'.$q.'%']);
  $products = $stmt->fetchAll();
}

include __DIR__ . '/../templates/header.php';
?>
<h2>ค้นหาสินค้า</h2>
<div class="box">
  คำค้น: <b><?= h($q) ?></b> <span class="small">(ค้นจากชื่อสินค้า)</span>
</div>

<table class="table">
  <tr><th>สินค้า</th><th>ประเภท</th><th>ราคา</th></tr>
  <?php foreach($products as $p): ?>
    <tr>
      <td><a href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$p['id'] ?>"><?= h($p['name']) ?></a></td>
      <td><?= h($p['category_name']) ?></td>
      <td><?= number_format((float)$p['price'], 2) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php include __DIR__ . '/../templates/footer.php'; ?>