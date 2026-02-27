<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

// โหลดหมวดหมู่
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$msg = '';
$err = '';

// อัปโหลดรูป (แบบง่าย เก็บใน /uploads/products/)
function upload_image($file, &$err) {
  if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

  $allow = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
  $type = $file['type'] ?? '';
  if (!isset($allow[$type])) {
    $err = "ไฟล์รูปต้องเป็น JPG/PNG/WEBP";
    return null;
  }

  $ext = $allow[$type];
  $dir = __DIR__ . '/../../uploads/products';
  if (!is_dir($dir)) @mkdir($dir, 0777, true);

  $name = 'p_' . time() . '_' . rand(1000,9999) . '.' . $ext;
  $path = $dir . '/' . $name;

  if (!move_uploaded_file($file['tmp_name'], $path)) {
    $err = "อัปโหลดรูปไม่สำเร็จ (tmp=".$file['tmp_name'].", dest=".$path.")";
    return null;
  }
  return 'uploads/products/' . $name; // เก็บ path แบบ relative
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $category_id  = (int)($_POST['category_id'] ?? 0);
  $name         = trim($_POST['name'] ?? '');
  $price        = (float)($_POST['price'] ?? 0);
  $stock        = (int)($_POST['stock'] ?? 0);
  $description  = trim($_POST['description'] ?? '');

  // Bundle (ชุดสูตร/ไฟล์หลังซื้อ)
  $is_bundle    = isset($_POST['is_bundle']) ? 1 : 0;
  $bundle_type  = trim((string)($_POST['bundle_type'] ?? 'drive'));
  $bundle_link  = trim((string)($_POST['bundle_link'] ?? ''));

  // allowlist type
  $allowedBundleTypes = ['pdf','video','drive','internal'];
  if (!in_array($bundle_type, $allowedBundleTypes, true)) {
    $bundle_type = 'drive';
  }

  if ($category_id <= 0) $err = "กรุณาเลือกประเภทสินค้า";
  elseif ($name === '') $err = "กรุณากรอกชื่อสินค้า";
  elseif ($price < 0) $err = "ราคาไม่ถูกต้อง";
  elseif ($stock < 0) $err = "สต๊อกไม่ถูกต้อง";

  // ถ้าเป็นชุดสูตร ต้องมีลิงก์ (ยกเว้นชนิด internal ที่จะไปเสิร์ฟไฟล์เองทีหลัง)
  if (!$err && $is_bundle === 1) {
    if ($bundle_type !== 'internal' && $bundle_link === '') {
      $err = "กรุณากรอกลิงก์สำหรับชุดสูตร/ไฟล์หลังซื้อ";
    }
  }

  // อัปโหลดรูปถ้ามี
  $imagePath = null;
  if (!$err && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imagePath = upload_image($_FILES['image'], $err);
  }

  if (!$err) {
    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, price, stock, description, image, is_bundle, bundle_type, bundle_link)
                           VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$category_id, $name, $price, $stock, $description, $imagePath, $is_bundle, $bundle_type, ($bundle_link !== '' ? $bundle_link : null)]);

    $newId = (int)$pdo->lastInsertId();
    // บันทึกแล้วเด้งไปหน้าแก้ไขต่อได้เลย
    header("Location: " . BASE_URL . "/admin/products/edit.php?id=" . $newId);
    exit;
  }
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-1">เพิ่มสินค้า</h2>
    <div class="text-muted small">กรอกข้อมูลสินค้า เลือกหมวดหมู่ และอัปโหลดรูปสินค้า</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/products/index.php">
      <i class="bi bi-arrow-left me-1"></i>กลับรายการ
    </a>
  </div>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <div><?= h($err) ?></div>
  </div>
<?php endif; ?>

<?php if (!$cats): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex align-items-start gap-3">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning" style="width:46px;height:46px;">
          <i class="bi bi-info-circle"></i>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold">ยังไม่มีประเภทสินค้า</div>
          <div class="text-muted">กรุณาเพิ่มประเภทสินค้าก่อน เพื่อให้สามารถสร้างสินค้าได้</div>
          <div class="mt-3">
            <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/categories/create.php">
              <i class="bi bi-plus-lg me-1"></i>ไปเพิ่มประเภทสินค้า
            </a>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/products/index.php">กลับ</a>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="row g-3" novalidate>

        <div class="col-12 col-lg-6">
          <label class="form-label">ประเภทสินค้า <span class="text-danger">*</span></label>
          <select class="form-select" name="category_id" required>
            <option value="0">-- เลือกประเภทสินค้า --</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)($_POST['category_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>>
                <?= h($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">เลือกหมวดหมู่ที่เกี่ยวข้องกับสินค้า</div>
        </div>

        <div class="col-12 col-lg-6">
          <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
          <input class="form-control" name="name" value="<?= h($_POST['name'] ?? '') ?>" placeholder="เช่น ครัวซองต์เนยสด" required>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label">ราคา (บาท) <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text">฿</span>
            <input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= h($_POST['price'] ?? '0') ?>" required>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label">สต๊อก <span class="text-danger">*</span></label>
          <input class="form-control" type="number" min="0" name="stock" value="<?= h($_POST['stock'] ?? '0') ?>" required>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label">สถานะเริ่มต้น</label>
          <div class="form-control bg-light" style="pointer-events:none;">
            <i class="bi bi-check-circle text-success me-1"></i>พร้อมใช้งาน (แก้ได้ในหน้าแก้ไข)
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">รายละเอียด</label>
          <textarea class="form-control" name="description" rows="5" placeholder="รายละเอียดสินค้า เช่น วัตถุดิบ/รสชาติ/วิธีเก็บรักษา..."><?= h($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">รูปสินค้า (รูปหลัก)</label>
          <input class="form-control" type="file" name="image" id="imageInput" accept="image/*">
          <div class="form-text">รองรับ JPG/PNG/WEBP (แนะนำภาพสัดส่วน 1:1 หรือ 4:3)</div>

          <div class="mt-3" id="previewWrap" style="display:none;">
            <div class="small text-muted mb-1">ตัวอย่างรูป</div>
            <div class="ratio ratio-1x1 rounded-3 overflow-hidden border bg-light" style="max-width:220px;">
              <img id="imagePreview" alt="preview" style="object-fit:cover" loading="lazy">
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 bg-light">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                  <div class="fw-semibold">ขายเป็นชุดสูตร/ไฟล์หลังซื้อ (Bundle)</div>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" role="switch" id="isBundle" name="is_bundle" value="1" <?= !empty($_POST['is_bundle']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="isBundle">เปิด</label>
                </div>
              </div>

              <div id="bundleFields" class="row g-3 mt-2" style="display:none;">
                <div class="col-12 col-lg-4">
                  <label class="form-label">ชนิดไฟล์/ลิงก์</label>
                  <?php $bt = (string)($_POST['bundle_type'] ?? 'drive'); ?>
                  <select class="form-select" name="bundle_type" id="bundleType">
                    <option value="pdf" <?= $bt==='pdf'?'selected':'' ?>>PDF</option>
                    <option value="video" <?= $bt==='video'?'selected':'' ?>>วิดีโอ</option>
                    <option value="drive" <?= $bt==='drive'?'selected':'' ?>>Google Drive/ลิงก์ภายนอก</option>
                    <option value="internal" <?= $bt==='internal'?'selected':'' ?>>ไฟล์ในระบบ (internal)</option>
                  </select>
                  <div class="form-text">เลือกชนิดให้ตรงกับลิงก์/ไฟล์ที่จะปล่อยหลังชำระเงิน</div>
                </div>

                <div class="col-12 col-lg-8">
                  <label class="form-label">ลิงก์ชุดสูตร/ไฟล์</label>
                  <input class="form-control" name="bundle_link" id="bundleLink" value="<?= h($_POST['bundle_link'] ?? '') ?>" placeholder="เช่น https://drive.google.com/... หรือ https://youtube.com/...">
                  <div class="form-text">ถ้าเลือก <b>internal</b> สามารถเว้นว่างไว้ได้ (ไว้ไปกำหนดไฟล์ในระบบทีหลัง)</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <hr class="my-2">
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-save me-1"></i>บันทึก
            </button>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/products/index.php">ยกเลิก</a>
          </div>
        </div>

      </form>
    </div>
  </div>

  <script>
    (function(){
      const input = document.getElementById('imageInput');
      const wrap  = document.getElementById('previewWrap');
      const img   = document.getElementById('imagePreview');
      if (input && wrap && img) {
        input.addEventListener('change', function(){
          const f = input.files && input.files[0];
          if (!f) {
            wrap.style.display = 'none';
            img.removeAttribute('src');
            return;
          }
          if (!f.type || !f.type.startsWith('image/')) {
            wrap.style.display = 'none';
            img.removeAttribute('src');
            return;
          }
          const url = URL.createObjectURL(f);
          img.src = url;
          wrap.style.display = '';
          img.onload = () => URL.revokeObjectURL(url);
        });
      }

      // bundle toggle
      const isBundle = document.getElementById('isBundle');
      const bundleFields = document.getElementById('bundleFields');
      const bundleType = document.getElementById('bundleType');
      const bundleLink = document.getElementById('bundleLink');

      function syncBundleUI(){
        if (!isBundle || !bundleFields) return;
        const on = isBundle.checked;
        bundleFields.style.display = on ? '' : 'none';

        // if internal, link optional; otherwise suggest required
        if (bundleType && bundleLink) {
          const isInternal = (bundleType.value === 'internal');
          bundleLink.placeholder = isInternal
            ? 'internal: เว้นว่างได้ หรือใส่ path/slug ภายในระบบ'
            : 'เช่น https://drive.google.com/... หรือ https://youtube.com/...';
        }
      }

      if (isBundle) {
        isBundle.addEventListener('change', syncBundleUI);
      }
      if (bundleType) {
        bundleType.addEventListener('change', syncBundleUI);
      }
      // initial
      syncBundleUI();
    })();
  </script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>