<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$pageTitle = 'จัดการประเภทสินค้า';
$q = trim($_GET['q'] ?? '');

if ($q !== '') {
  $stmt = $pdo->prepare(
    "SELECT c.*,
            (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count
     FROM categories c
     WHERE c.name LIKE ?
     ORDER BY c.id DESC"
  );
  $stmt->execute(['%' . $q . '%']);
  $cats = $stmt->fetchAll();
} else {
  $cats = $pdo->query(
    "SELECT c.*,
            (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count
     FROM categories c
     ORDER BY c.id DESC"
  )->fetchAll();
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-start align-items-md-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-1">จัดการประเภทสินค้า</h2>
    <div class="text-muted small">เพิ่ม/แก้ไข/ลบประเภท และดูจำนวนสินค้าที่อยู่ในแต่ละประเภท</div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/categories/create.php">
      <i class="bi bi-plus-lg me-1"></i>เพิ่มประเภท
    </a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/index.php">
      <i class="bi bi-arrow-left me-1"></i>กลับเมนู
    </a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-center">
      <div class="col-12 col-lg-7">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="ค้นหาประเภทสินค้า..." aria-label="ค้นหาประเภทสินค้า">
          <?php if ($q !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/categories/index.php" title="ล้างคำค้น">
              <i class="bi bi-x-lg"></i>
            </a>
          <?php endif; ?>
          <button class="btn btn-primary" type="submit">ค้นหา</button>
        </div>
      </div>

      <div class="col-12 col-lg-5 text-lg-end">
        <span class="badge text-bg-light border">
          <?= number_format(count($cats)) ?> ประเภท
          <?php if ($q !== ''): ?> (ผลการค้นหา)<?php endif; ?>
        </span>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table id="dt" class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:90px;">ID</th>
          <th>ชื่อประเภท</th>
          <th class="text-center" style="width:140px;">จำนวนสินค้า</th>
          <th style="width:190px;">วันที่สร้าง</th>
          <th class="text-end" style="width:160px;">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cats)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              <i class="bi bi-inboxes me-1"></i>
              <?= $q !== '' ? 'ไม่พบประเภทสินค้าที่ตรงกับคำค้น' : 'ยังไม่มีประเภทสินค้า' ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($cats as $c): ?>
            <?php $count = (int)($c['product_count'] ?? 0); ?>
            <tr>
              <td class="fw-semibold">#<?= (int)$c['id'] ?></td>
              <td>
                <div class="fw-semibold"><?= h($c['name']) ?></div>
                <div class="small text-muted">ใช้สำหรับจัดหมวดหมู่สินค้าในหน้าร้าน</div>
              </td>
              <td class="text-center">
                <span class="badge <?= $count > 0 ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                  <?= $count ?>
                </span>
              </td>
              <td class="text-muted small">
                <?= h($c['created_at'] ?? '-') ?>
              </td>
              <td class="text-end">
                <div class="btn-group-vertical" role="group" aria-label="จัดการประเภท" style="min-width:120px;">
                  <a class="btn btn-sm btn-outline-primary w-100" href="<?= BASE_URL ?>/admin/categories/edit.php?id=<?= (int)$c['id'] ?>">
                    <i class="bi bi-pencil me-1"></i>แก้ไข
                  </a>
                  <a class="btn btn-sm btn-outline-danger w-100"
                     href="<?= BASE_URL ?>/admin/categories/delete.php?id=<?= (int)$c['id'] ?>"
                     onclick="return confirm('ลบประเภทนี้? ถ้ามีสินค้าอยู่จะลบไม่ได้');">
                    <i class="bi bi-trash me-1"></i>ลบ
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../admin_footer.php'; ?>