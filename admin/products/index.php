<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';

require_admin();

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
  $stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.name LIKE ?
     ORDER BY p.id DESC"
  );
  $stmt->execute(['%' . $q . '%']);
  $products = $stmt->fetchAll();
} else {
  $products = $pdo->query(
    "SELECT p.*, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     ORDER BY p.id DESC"
  )->fetchAll();
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-1">จัดการสินค้า</h1>
    <div class="text-secondary small">เพิ่ม/แก้ไข/ลบสินค้า และค้นหาตามชื่อ</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/products/create.php">
      <i class="bi bi-plus-lg me-1"></i>เพิ่มสินค้า
    </a>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/index.php">กลับเมนู</a>
  </div>
</div>

<form class="row g-2 align-items-center mb-3" method="get">
  <div class="col-12 col-md-6 col-lg-5">
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="ค้นหาสินค้า...">
      <button class="btn btn-outline-primary" type="submit">ค้นหา</button>
    </div>
  </div>
  <?php if ($q !== ''): ?>
    <div class="col-12 col-md-auto">
      <a class="btn btn-link" href="<?= BASE_URL ?>/admin/products/index.php">ล้างตัวกรอง</a>
    </div>
  <?php endif; ?>
</form>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:90px;">ID</th>
            <th style="width:72px;"></th>
            <th>ชื่อสินค้า</th>
            <th style="width:180px;">ประเภท</th>
            <th class="text-end" style="width:140px;">ราคา</th>
            <th class="text-end" style="width:120px;">สต็อก</th>
            <th class="text-end" style="width:180px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$products): ?>
          <tr>
            <td colspan="7" class="text-center text-secondary py-4">ไม่พบรายการสินค้า</td>
          </tr>
        <?php else: ?>
          <?php foreach($products as $p): ?>
          <tr>
            <td class="fw-semibold">#<?= (int)$p['id'] ?></td>
            <td>
              <?php $img = trim((string)($p['image'] ?? '')); ?>
              <?php if ($img !== ''): ?>
                <img
                  src="<?= BASE_URL ?>/<?= h($img) ?>"
                  alt="<?= h($p['name']) ?>"
                  width="52" height="52"
                  style="object-fit: cover; border-radius: 12px; border: 1px solid rgba(16,24,40,.10); background:#fff;"
                  loading="lazy"
                  onerror="this.style.display='none'; this.parentElement.querySelector('.thumb-fallback').style.display='flex';"
                >
              <?php endif; ?>
              <div class="thumb-fallback" style="width:52px;height:52px;border-radius:12px;border:1px solid rgba(16,24,40,.10);background:#fff;display:<?= ($img !== '') ? 'none' : 'flex' ?>;align-items:center;justify-content:center;color:rgba(16,24,40,.55);">
                <i class="bi bi-image"></i>
              </div>
            </td>
            <td>
              <div class="fw-semibold"><?= h($p['name']) ?></div>
              <div class="text-secondary small">แก้ไขรายละเอียด/รูป/สต็อกได้จากปุ่มดินสอ</div>
            </td>
            <td><span class="badge text-bg-light border"><?= h($p['category_name']) ?></span></td>
            <td class="text-end"><?= number_format((float)$p['price'], 2) ?></td>
            <td class="text-end">
              <?php $stock = (int)$p['stock']; ?>
              <span class="badge <?= $stock <= 0 ? 'text-bg-danger' : ($stock <= 5 ? 'text-bg-warning' : 'text-bg-success') ?>">
                <?= $stock ?>
              </span>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/products/edit.php?id=<?= (int)$p['id'] ?>" title="แก้ไข">
                <i class="bi bi-pencil-square"></i>
              </a>
              <a class="btn btn-sm btn-outline-danger" href="<?= BASE_URL ?>/admin/products/delete.php?id=<?= (int)$p['id'] ?>" onclick="return confirm('ยืนยันลบสินค้านี้?')" title="ลบ">
                <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>