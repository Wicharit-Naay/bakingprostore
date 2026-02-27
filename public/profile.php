<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

$uid = (int)$_SESSION['user']['id'];
$msg = '';

$stmt = $pdo->prepare("SELECT name,email,phone,address FROM users WHERE id=?");
$stmt->execute([$uid]);
$u = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');

  $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=?");
  $stmt->execute([$name,$phone,$address,$uid]);

  $_SESSION['user']['name'] = $name;
  $msg = 'บันทึกแล้ว';

  $stmt = $pdo->prepare("SELECT name,email,phone,address FROM users WHERE id=?");
  $stmt->execute([$uid]);
  $u = $stmt->fetch();
}

include __DIR__ . '/../templates/header.php';
?>

<div class="container py-4">
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h2 class="h4 mb-1">ข้อมูลส่วนตัว</h2>
      <div class="text-muted small">จัดการข้อมูลผู้ใช้และที่อยู่สำหรับการจัดส่ง</div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/public/orders.php">
        <i class="bi bi-receipt me-1"></i>ออเดอร์ของฉัน
      </a>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
      <i class="bi bi-check-circle me-2"></i>
      <div><?= h($msg) ?></div>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <div class="fw-semibold"><i class="bi bi-person-lines-fill me-1"></i>ข้อมูลผู้ใช้งาน</div>
      <span class="badge text-bg-light border">แก้ไขได้</span>
    </div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">ชื่อ</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input class="form-control" name="name" value="<?= h($u['name'] ?? '') ?>" placeholder="ชื่อ-นามสกุล" required>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">อีเมล</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input class="form-control" value="<?= h($u['email'] ?? '') ?>" disabled>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">เบอร์โทร</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input class="form-control" name="phone" value="<?= h($u['phone'] ?? '') ?>" placeholder="เช่น 098xxxxxxx" inputmode="tel">
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">ที่อยู่จัดส่ง</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
            <textarea class="form-control" name="address" rows="4" placeholder="บ้านเลขที่ / หมู่ / ถนน / ตำบล / อำเภอ / จังหวัด / รหัสไปรษณีย์"><?= h($u['address'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-save me-1"></i>บันทึกข้อมูล
            </button>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/public/index.php">
              <i class="bi bi-arrow-left me-1"></i>กลับหน้าร้าน
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm mt-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="text-muted small">
        <i class="bi bi-shield-check me-1"></i>ข้อมูลของคุณจะถูกเก็บไว้เพื่อความสะดวกในการสั่งซื้อครั้งถัดไป
      </div>
      <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/public/cart.php">
        <i class="bi bi-cart me-1"></i>ไปที่ตะกร้า
      </a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>