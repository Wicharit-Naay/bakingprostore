<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id,name,email,phone,address FROM users WHERE id=? AND role='customer'");
$stmt->execute([$id]);
$u = $stmt->fetch();
if(!$u) die("ไม่พบลูกค้า");

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');

  $up = $pdo->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=? AND role='customer'");
  $up->execute([$name,$phone,$address,$id]);
  $msg = 'บันทึกแล้ว';

  $stmt->execute([$id]);
  $u = $stmt->fetch();
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">แก้ไขข้อมูลลูกค้า</h2>
    <div class="text-muted small">ปรับปรุงข้อมูลติดต่อและที่อยู่สำหรับจัดส่ง</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/customers/index.php">
      <i class="bi bi-arrow-left me-1"></i>กลับ
    </a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-check-circle"></i>
    <div><?= h($msg) ?></div>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <div class="fw-semibold"><i class="bi bi-person-gear me-1"></i>ข้อมูลลูกค้า</div>
    <span class="badge text-bg-light border">ID: #<?= (int)$u['id'] ?></span>
  </div>
  <div class="card-body">
    <form method="post" class="row g-3" autocomplete="off">
      <div class="col-12 col-md-6">
        <label class="form-label">ชื่อ</label>
        <input class="form-control" name="name" value="<?= h($u['name']) ?>" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">อีเมล</label>
        <input class="form-control" value="<?= h($u['email']) ?>" readonly>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">เบอร์โทร</label>
        <input class="form-control" name="phone" value="<?= h($u['phone']) ?>" placeholder="เช่น 0981234567">
      </div>

      <div class="col-12">
        <label class="form-label">ที่อยู่จัดส่ง</label>
        <textarea class="form-control" name="address" rows="4" placeholder="บ้านเลขที่ / หมู่ / ถนน / ตำบล / อำเภอ / จังหวัด / รหัสไปรษณีย์"><?= h($u['address']) ?></textarea>
      </div>

      <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>บันทึก
          </button>
          <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/customers/index.php">
            <i class="bi bi-x-circle me-1"></i>ยกเลิก
          </a>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>