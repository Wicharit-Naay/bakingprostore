<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ไม่พบประเภทสินค้า");

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id=?");
$stmt->execute([$id]);
$cat = $stmt->fetch();
if (!$cat) die("ไม่พบประเภทสินค้า");

$err = '';
$msg = '';
$name = $cat['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');

  if ($name === '') {
    $err = "กรุณากรอกชื่อประเภทสินค้า";
  } else {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name=? AND id<>?");
    $chk->execute([$name, $id]);
    if ((int)$chk->fetchColumn() > 0) {
      $err = "มีชื่อประเภทนี้อยู่แล้ว";
    } else {
      $up = $pdo->prepare("UPDATE categories SET name=? WHERE id=?");
      $up->execute([$name, $id]);
      $msg = "บันทึกแล้ว";
    }
  }
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">แก้ไขประเภทสินค้า</h2>
    <div class="text-muted small">ปรับชื่อประเภทสินค้า และตรวจสอบไม่ให้ซ้ำกับรายการเดิม</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/categories/index.php">
      <i class="bi bi-arrow-left me-1"></i>กลับ
    </a>
  </div>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <div><?= h($err) ?></div>
  </div>
<?php endif; ?>

<?php if ($msg): ?>
  <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
    <i class="bi bi-check-circle"></i>
    <div><?= h($msg) ?></div>
  </div>
<?php endif; ?>

<div class="card shadow-sm admin-card">
  <div class="card-body">
    <form method="post" class="row g-3" autocomplete="off">
      <div class="col-12">
        <label class="form-label">ชื่อประเภทสินค้า</label>
        <input
          name="name"
          class="form-control"
          value="<?= h($name) ?>"
          placeholder="เช่น แก้ว, วัตถุดิบ, ภาชนะ"
          required
        >
      </div>

      <div class="col-12 d-flex flex-wrap gap-2 pt-1">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>บันทึก
        </button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/categories/index.php">
          ยกเลิก
        </a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>