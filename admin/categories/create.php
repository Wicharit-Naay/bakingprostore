<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$err = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');

  if ($name === '') {
    $err = "กรุณากรอกชื่อประเภทสินค้า";
  } else {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name=?");
    $chk->execute([$name]);
    if ((int)$chk->fetchColumn() > 0) {
      $err = "มีชื่อประเภทนี้อยู่แล้ว";
    } else {
      $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
      $stmt->execute([$name]);
      header("Location: " . BASE_URL . "/admin/categories/index.php");
      exit;
    }
  }
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">เพิ่มประเภทสินค้า</h2>
    <div class="text-muted small">เพิ่มหมวดหมู่เพื่อใช้จัดกลุ่มสินค้าในหน้าร้าน</div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/categories/index.php">
    <i class="bi bi-arrow-left me-1"></i>กลับ
  </a>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <div><?= h($err) ?></div>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" class="row g-3" autocomplete="off">
      <div class="col-12">
        <label class="form-label">ชื่อประเภทสินค้า</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-tag"></i></span>
          <input
            class="form-control"
            name="name"
            value="<?= h($name) ?>"
            placeholder="เช่น วัตถุดิบ, แก้ว, ภาชนะ"
            required
          >
        </div>
      </div>

      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check2-circle me-1"></i>บันทึก
        </button>
        <a class="btn btn-light border" href="<?= BASE_URL ?>/admin/categories/index.php">
          ยกเลิก
        </a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>