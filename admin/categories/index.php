<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';

require_admin();

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
  $stmt = $pdo->prepare("SELECT c.*,
                                (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count
                         FROM categories c
                         WHERE c.name LIKE ?
                         ORDER BY c.id DESC");
  $stmt->execute(['%'.$q.'%']);
  $cats = $stmt->fetchAll();
} else {
  $cats = $pdo->query("SELECT c.*,
                              (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count
                       FROM categories c
                       ORDER BY c.id DESC")->fetchAll();
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<h2>จัดการประเภทสินค้า</h2>

<div class="box">
  <form method="get" style="display:flex;gap:8px;align-items:center;">
    <input name="q" value="<?= h($q) ?>" placeholder="ค้นหาประเภทสินค้า..." style="flex:1;">
    <button type="submit">ค้นหา</button>
    <a href="<?= BASE_URL ?>/admin/categories/create.php">+ เพิ่มประเภท</a>
    <a href="<?= BASE_URL ?>/admin/index.php">กลับเมนู</a>
  </form>
</div>

<table id="dt" class="table">
  <thead>
    <tr>
      <th>ID</th>
      <th>ชื่อประเภท</th>
      <th>จำนวนสินค้า</th>
      <th>วันที่สร้าง</th>
      <th>จัดการ</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($cats as $c): ?>
      <tr>
        <td><?= (int)$c['id'] ?></td>
        <td><?= h($c['name']) ?></td>
        <td><?= (int)$c['product_count'] ?></td>
        <td><?= h($c['created_at'] ?? '') ?></td>
        <td>
          <a href="<?= BASE_URL ?>/admin/categories/edit.php?id=<?= (int)$c['id'] ?>">แก้ไข</a> |
          <a href="<?= BASE_URL ?>/admin/categories/delete.php?id=<?= (int)$c['id'] ?>"
             onclick="return confirm('ลบประเภทนี้? ถ้ามีสินค้าอยู่จะลบไม่ได้');">
             ลบ
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>