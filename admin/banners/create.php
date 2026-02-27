<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/functions.php';
$authFile = __DIR__ . '/../../helpers/auth.php';
if (file_exists($authFile)) require_once $authFile;

if (function_exists('require_admin')) {
  require_admin();
} elseif (function_exists('require_login')) {
  require_login();
}

require_once __DIR__ . '/../../templates/admin_header.php';

$msg = '';
$title = '';
$subtitle = '';
$link_url = '';
$sort_order = 0;
$is_active = 1;

function ensure_banner_dir(): string {
  $dir = __DIR__ . '/../../uploads/banners';
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  return $dir;
}

function save_upload_banner(string $field): ?string {
  if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;

  $tmp = $_FILES[$field]['tmp_name'];
  $name = $_FILES[$field]['name'] ?? 'banner';
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  $allow = ['jpg','jpeg','png','webp'];
  if (!in_array($ext, $allow, true)) {
    throw new RuntimeException('ไฟล์รูปต้องเป็น jpg, png หรือ webp เท่านั้น');
  }

  $bannerDir = ensure_banner_dir();
  $newName = 'bn_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = $bannerDir . '/' . $newName;

  if (!move_uploaded_file($tmp, $dest)) {
    throw new RuntimeException('ย้ายไฟล์ไม่สำเร็จ (permission/uploads)');
  }

  // path ที่เก็บลง DB (อ้างจาก root โปรเจกต์)
  return 'uploads/banners/' . $newName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $subtitle = trim($_POST['subtitle'] ?? '');
  $link_url = trim($_POST['link_url'] ?? '');
  $sort_order = (int)($_POST['sort_order'] ?? 0);
  $is_active = !empty($_POST['is_active']) ? 1 : 0;

  try {
    if ($title === '') throw new RuntimeException('กรุณากรอกชื่อแบนเนอร์');

    $imagePath = save_upload_banner('image');
    if (!$imagePath) throw new RuntimeException('กรุณาอัปโหลดรูปแบนเนอร์');

    $stmt = $pdo->prepare("INSERT INTO site_banners(title, subtitle, image, link_url, sort_order, is_active) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$title, $subtitle ?: null, $imagePath, $link_url ?: null, $sort_order, $is_active]);

    header('Location: ' . BASE_URL . '/admin/banners/index.php');
    exit;
  } catch (Throwable $e) {
    $msg = $e->getMessage();
  }
}
?>

<div class="container py-4" style="max-width:960px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">เพิ่มแบนเนอร์</h3>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/banners/index.php">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-danger"><?= h($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-12 col-md-8">
          <label class="form-label">ชื่อแบนเนอร์</label>
          <input class="form-control" name="title" value="<?= h($title) ?>" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">ลำดับการแสดง (sort_order)</label>
          <input class="form-control" type="number" name="sort_order" value="<?= (int)$sort_order ?>">
        </div>

        <div class="col-12">
          <label class="form-label">คำอธิบายย่อย (ไม่บังคับ)</label>
          <input class="form-control" name="subtitle" value="<?= h($subtitle) ?>">
        </div>

        <div class="col-12">
          <label class="form-label">ลิงก์เมื่อคลิกแบนเนอร์ (ไม่บังคับ)</label>
          <input class="form-control" name="link_url" value="<?= h($link_url) ?>" placeholder="เช่น <?= BASE_URL ?>/public/category.php?id=1">
        </div>

        <div class="col-12">
          <label class="form-label">รูปแบนเนอร์ (แนะนำ 1600x600 หรือ 1920x720)</label>
          <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required>
          <div class="form-text">รองรับ jpg/png/webp</div>
        </div>

        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="active" <?= $is_active ? 'checked' : '' ?>>
            <label class="form-check-label" for="active">Active (แสดงหน้าแรก)</label>
          </div>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button>
          <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/banners/index.php">ยกเลิก</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>