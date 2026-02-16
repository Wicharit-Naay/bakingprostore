<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
  $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name
                         FROM products p JOIN categories c ON c.id=p.category_id
                         WHERE p.name LIKE ?
                         ORDER BY p.id DESC");
  $stmt->execute(['%'.$q.'%']);
  $products = $stmt->fetchAll();
} else {
  $products = $pdo->query("SELECT p.*, c.name AS category_name
                           FROM products p JOIN categories c ON c.id=p.category_id
                           ORDER BY p.id DESC")->fetchAll();
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin Products</title></head><body>
<h2>จัดการสินค้า</h2>
<form method="get">
  <input name="q" value="<?= h($q) ?>" placeholder="ค้นหาสินค้า...">
  <button>ค้นหา</button>
</form>
<p><a href="<?= BASE_URL ?>/admin/products/create.php">+ เพิ่มสินค้า</a> | <a href="<?= BASE_URL ?>/admin/index.php">กลับเมนู</a></p>

<table border="1" cellpadding="6">
<tr><th>ID</th><th>ชื่อ</th><th>ประเภท</th><th>ราคา</th><th>สต็อก</th><th>จัดการ</th></tr>
<?php foreach($products as $p): ?>
<tr>
  <td><?= (int)$p['id'] ?></td>
  <td><?= h($p['name']) ?></td>
  <td><?= h($p['category_name']) ?></td>
  <td><?= number_format((float)$p['price'],2) ?></td>
  <td><?= (int)$p['stock'] ?></td>
  <td>
    <a href="<?= BASE_URL ?>/admin/products/edit.php?id=<?= (int)$p['id'] ?>">แก้ไข</a> |
    <a href="<?= BASE_URL ?>/admin/products/delete.php?id=<?= (int)$p['id'] ?>" onclick="return confirm('ลบ?')">ลบ</a>
  </td>
</tr>
<?php endforeach; ?>
</table>
</body></html>