<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
  $stmt = $pdo->prepare("SELECT id,name,email,phone,created_at
                         FROM users
                         WHERE role='customer'
                         AND (name LIKE ? OR email LIKE ?)
                         ORDER BY id DESC");
  $stmt->execute(['%'.$q.'%','%'.$q.'%']);
  $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $customers = $pdo->query("SELECT id,name,email,phone,created_at
                            FROM users
                            WHERE role='customer'
                            ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'ลูกค้า';
require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">จัดการลูกค้า</h2>
    <div class="text-muted small">ค้นหา/ดูรายชื่อลูกค้า และจัดการข้อมูลลูกค้า</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/index.php">
      <i class="bi bi-arrow-left"></i> กลับเมนู
    </a>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-8">
        <label class="form-label">ค้นหา</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="ค้นหา ชื่อ/อีเมล">
          <button class="btn btn-primary" type="submit">ค้นหา</button>
        </div>
        <div class="form-text">พิมพ์ชื่อหรืออีเมล แล้วกดค้นหา</div>
      </div>
      <div class="col-12 col-md-4 d-flex gap-2">
        <a class="btn btn-outline-secondary flex-fill" href="<?= BASE_URL ?>/admin/customers/index.php">
          <i class="bi bi-arrow-counterclockwise"></i> รีเซ็ต
        </a>
        <span class="btn btn-outline-dark flex-fill" style="pointer-events:none;">
          <i class="bi bi-people"></i> <?= number_format(count($customers)) ?> คน
        </span>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <div class="fw-semibold"><i class="bi bi-people me-1"></i>รายการลูกค้า</div>
    <span class="badge text-bg-light border"><?= number_format(count($customers)) ?> รายการ</span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:80px;">ID</th>
          <th>ชื่อ</th>
          <th style="min-width:220px;">อีเมล</th>
          <th style="width:160px;">เบอร์</th>
          <th style="width:190px;">สมัครเมื่อ</th>
          <th class="text-end" style="width:170px;">จัดการ</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$customers): ?>
        <tr>
          <td colspan="6" class="text-center text-muted py-5">
            <div class="mb-2"><i class="bi bi-inbox" style="font-size:28px;"></i></div>
            ไม่พบข้อมูลลูกค้า
          </td>
        </tr>
      <?php else: foreach($customers as $c): ?>
        <tr>
          <td class="fw-semibold">#<?= (int)$c['id'] ?></td>
          <td>
            <div class="fw-semibold"><?= h($c['name'] ?? '-') ?></div>
            <div class="small text-muted">ลูกค้า</div>
          </td>
          <td>
            <a class="text-decoration-none" href="mailto:<?= h($c['email'] ?? '') ?>">
              <?= h($c['email'] ?? '-') ?>
            </a>
          </td>
          <td>
            <?php $phone = trim((string)($c['phone'] ?? '')); ?>
            <?php if ($phone !== ''): ?>
              <a class="text-decoration-none" href="tel:<?= h($phone) ?>"><?= h($phone) ?></a>
            <?php else: ?>
              <span class="text-muted">-</span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= h($c['created_at'] ?? '-') ?></td>
          <td class="text-end">
            <div class="btn-group" role="group" aria-label="Actions">
              <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/admin/customers/edit.php?id=<?= (int)$c['id'] ?>">
                <i class="bi bi-pencil"></i> แก้ไข
              </a>
              <a class="btn btn-sm btn-outline-danger" href="<?= BASE_URL ?>/admin/customers/delete.php?id=<?= (int)$c['id'] ?>"
                 onclick="return confirm('ลบลูกค้าคนนี้?')">
                <i class="bi bi-trash"></i>
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>