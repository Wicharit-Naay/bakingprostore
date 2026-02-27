<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';


require_admin();

// Disable caching for admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Ensure PDO throws exceptions
if (isset($pdo) && $pdo instanceof PDO) {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ไม่พบสินค้า");

// โหลดหมวดหมู่
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// โหลดข้อมูลสินค้า
$stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) die("ไม่พบสินค้า");

$msg = '';
$err = '';

// อัปโหลดรูป (แบบง่าย เก็บใน /uploads/products/)
function upload_image($file, &$err) {
  if (!isset($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;

  $allow = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

  // Detect real mime type (more reliable than browser-provided type)
  $type = '';
  if (!empty($file['tmp_name']) && is_file($file['tmp_name']) && function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
      $type = (string)finfo_file($finfo, $file['tmp_name']);
      finfo_close($finfo);
    }
  }
  if ($type === '') {
    $type = (string)($file['type'] ?? '');
  }

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
    $err = "อัปโหลดรูปไม่สำเร็จ";
    return null;
  }
  return 'uploads/products/' . $name; // เก็บ path แบบ relative
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ===== Delete gallery image =====
  if (isset($_POST['delete_image_id'])) {
    $delId = (int)($_POST['delete_image_id'] ?? 0);
    if ($delId > 0) {
      try {
        // find file path first
        $q = $pdo->prepare('SELECT image FROM product_images WHERE id=? AND product_id=?');
        $q->execute([$delId, $id]);
        $imgPath = (string)($q->fetchColumn() ?: '');

        $d = $pdo->prepare('DELETE FROM product_images WHERE id=? AND product_id=?');
        $d->execute([$delId, $id]);

        // try to remove physical file (best-effort)
        if ($imgPath !== '') {
          $abs = __DIR__ . '/../../' . ltrim($imgPath, '/');
          if (is_file($abs)) { @unlink($abs); }
        }

        header('Location: ' . BASE_URL . '/admin/products/edit.php?id=' . $id . '&ok=1');
        exit;
      } catch (Throwable $e) {
        $err = 'ลบรูปไม่สำเร็จ: ' . $e->getMessage();
      }
    }
  }

  // Always take id from querystring for safety
  $category_id  = (int)($_POST['category_id'] ?? 0);
  $name         = trim((string)($_POST['name'] ?? ''));
  $priceRaw     = (string)($_POST['price'] ?? '0');
  $price        = (float)str_replace([',', ' '], '', $priceRaw);
  $stock        = (int)($_POST['stock'] ?? 0);
  $description  = trim((string)($_POST['description'] ?? ''));

  if ($category_id <= 0) $err = 'กรุณาเลือกประเภทสินค้า';
  elseif ($name === '') $err = 'กรุณากรอกชื่อสินค้า';
  elseif ($price < 0) $err = 'ราคาไม่ถูกต้อง';
  elseif ($stock < 0) $err = 'สต๊อกไม่ถูกต้อง';

  // Upload new image (optional)
  $newImage = null;
  if (!$err && isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $newImage = upload_image($_FILES['image'], $err);
  }

  if (!$err) {
    try {
      $imagePath = $newImage ? $newImage : ($p['image'] ?? null);

      $up = $pdo->prepare(
        'UPDATE products SET category_id=?, name=?, price=?, stock=?, description=?, image=? WHERE id=?'
      );
      $up->execute([$category_id, $name, $price, $stock, $description, $imagePath, $id]);

      // ===== Upload gallery images (multiple) =====
      if (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
        // current max sort_order
        $maxSort = 0;
        try {
          $mx = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id=?');
          $mx->execute([$id]);
          $maxSort = (int)($mx->fetchColumn() ?: 0);
        } catch (Throwable $e) {
          $maxSort = 0;
        }

        $names = $_FILES['images']['name'];
        $types = $_FILES['images']['type'];
        $tmps  = $_FILES['images']['tmp_name'];
        $errs  = $_FILES['images']['error'];
        $sizes = $_FILES['images']['size'];

        $count = count($names);
        for ($i = 0; $i < $count; $i++) {
          if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;

          $one = [
            'name' => $names[$i] ?? '',
            'type' => $types[$i] ?? '',
            'tmp_name' => $tmps[$i] ?? '',
            'error' => $errs[$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $sizes[$i] ?? 0,
          ];

          $fileErr = '';
          $img = upload_image($one, $fileErr);
          if ($img) {
            $maxSort++;
            $ins = $pdo->prepare('INSERT INTO product_images (product_id, image, sort_order) VALUES (?,?,?)');
            $ins->execute([$id, $img, $maxSort]);
          } else {
            // keep going; show the last meaningful error
            if ($fileErr !== '') $err = $fileErr;
          }
        }
      }

      // PRG: redirect to avoid stale POST and ensure fresh data
      header('Location: ' . BASE_URL . '/admin/products/edit.php?id=' . $id . '&ok=1');
      exit;
    } catch (Throwable $e) {
      $err = 'บันทึกไม่สำเร็จ: ' . $e->getMessage();
    }
  }
}

// Flash message via querystring
if (isset($_GET['ok']) && $_GET['ok'] === '1') {
  $msg = 'บันทึกแล้ว';
}

// Reload product (always show latest)
$stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) die('ไม่พบสินค้า');

// ===== Product gallery (multiple images) =====
$gallery = [];
try {
  $gi = $pdo->prepare('SELECT id, image, sort_order FROM product_images WHERE product_id=? ORDER BY sort_order ASC, id ASC');
  $gi->execute([$id]);
  $gallery = $gi->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $gallery = [];
}

require_once __DIR__ . '/../../templates/admin_header.php';

// Preview image url
$currentImg = !empty($p['image']) ? (BASE_URL . '/' . ltrim(h($p['image']), '/')) : '';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-1">แก้ไขสินค้า</h1>
    <div class="text-muted small">ปรับข้อมูลสินค้า ราคา สต๊อก และรูปสินค้า</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/products/index.php">
      <i class="bi bi-arrow-left me-1"></i>กลับรายการ
    </a>
  </div>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger d-flex align-items-start gap-2">
    <i class="bi bi-exclamation-triangle"></i>
    <div><?= h($err) ?></div>
  </div>
<?php endif; ?>

<?php if ($msg): ?>
  <div class="alert alert-success d-flex align-items-start gap-2">
    <i class="bi bi-check-circle"></i>
    <div><?= h($msg) ?></div>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <div class="col-12 col-lg-7">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">ประเภทสินค้า</label>
            <select name="category_id" class="form-select" required>
              <option value="0">-- เลือกประเภทสินค้า --</option>
              <?php foreach($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ((int)$p['category_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                  <?= h($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">ชื่อสินค้า</label>
            <input name="name" class="form-control" value="<?= h($p['name']) ?>" required>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">ราคา</label>
            <div class="input-group">
              <span class="input-group-text">฿</span>
              <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= h($p['price']) ?>" required>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">สต๊อก</label>
            <input type="number" min="0" name="stock" class="form-control" value="<?= h($p['stock']) ?>" required>
          </div>

          <div class="col-12">
            <label class="form-label">รายละเอียด</label>
            <textarea name="description" rows="5" class="form-control" placeholder="รายละเอียดสินค้า..."><?= h($p['description']) ?></textarea>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="border rounded-3 p-3 bg-light">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold"><i class="bi bi-image me-1"></i>รูปสินค้า (รูปหลัก)</div>
            <?php if ($currentImg): ?>
              <span class="badge text-bg-light border">มีรูปแล้ว</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">ยังไม่มีรูป</span>
            <?php endif; ?>
          </div>

          <div class="ratio ratio-1x1 rounded-3 overflow-hidden border bg-white mb-3">
            <?php if ($currentImg): ?>
              <img id="imgPreview" src="<?= $currentImg ?>" alt="product" style="object-fit:cover" loading="lazy">
            <?php else: ?>
              <div id="imgPreviewEmpty" class="d-flex align-items-center justify-content-center text-muted">
                <div class="text-center">
                  <i class="bi bi-image" style="font-size:34px;"></i>
                  <div class="small mt-1">ยังไม่มีรูป</div>
                </div>
              </div>
              <img id="imgPreview" src="" alt="product" style="display:none; object-fit:cover" loading="lazy">
            <?php endif; ?>
          </div>

          <label class="form-label">เปลี่ยนรูป (ไม่บังคับ)</label>
          <input id="imageInput" class="form-control" type="file" name="image" accept="image/*">
          <div class="form-text">รองรับ JPG/PNG/WEBP</div>

          <hr class="my-3">

          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold"><i class="bi bi-images me-1"></i>รูปเพิ่มเติม (หลายมุม)</div>
            <span class="badge text-bg-light border"><?= count($gallery) ?> รูป</span>
          </div>

          <label class="form-label mt-2">รูปเพิ่มเติม (หลายมุม)</label>
          <input id="galleryInput" class="form-control" type="file" name="images[]" accept="image/*" multiple>
          <div class="form-text">เลือกได้หลายไฟล์ • รองรับ JPG/PNG/WEBP</div>

          <div id="galleryPreview" class="row g-2 mt-2"></div>
          <label class="form-label">ลิงก์สูตร / วิดีโอ (สำหรับลูกค้าที่ซื้อ)</label>
          <input type="url" name="bundle_link" class="form-control" 
                value="<?= h($p['bundle_link'] ?? '') ?>">
          <div class="form-text">จะแสดงเฉพาะลูกค้าที่ซื้อสินค้า</div>

          <?php if ($gallery): ?>
            <div class="row g-2 mt-2">
              <?php foreach ($gallery as $g):
                $gUrl = BASE_URL . '/' . ltrim((string)$g['image'], '/');
              ?>
                <div class="col-4">
                  <div class="border rounded-3 overflow-hidden bg-white position-relative" style="aspect-ratio: 1 / 1;">
                    <img src="<?= h($gUrl) ?>" alt="gallery" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                    <button
                      type="submit"
                      name="delete_image_id"
                      value="<?= (int)$g['id'] ?>"
                      class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1"
                      onclick="return confirm('ลบรูปนี้?')"
                      aria-label="ลบรูปนี้"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="small text-muted mt-2">ยังไม่มีรูปเพิ่มเติม</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12">
        <div class="d-flex flex-wrap gap-2 justify-content-end">
          <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/products/index.php">
            ยกเลิก
          </a>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-1"></i>บันทึกการแก้ไข
          </button>
        </div>
      </div>

    </form>
  </div>
</div>

<script>
  (function(){
    // main image preview
    const input = document.getElementById('imageInput');
    const img = document.getElementById('imgPreview');
    const empty = document.getElementById('imgPreviewEmpty');
    if (input && img) {
      input.addEventListener('change', function(){
        const f = this.files && this.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        img.src = url;
        img.style.display = 'block';
        if (empty) empty.style.display = 'none';
        img.onload = function(){
          try { URL.revokeObjectURL(url); } catch(e) {}
        };
      });
    }

    // gallery preview (multiple)
    const gInput = document.getElementById('galleryInput');
    const gWrap = document.getElementById('galleryPreview');
    if (gInput && gWrap) {
      gInput.addEventListener('change', function(){
        gWrap.innerHTML = '';
        const files = Array.from(this.files || []);
        if (!files.length) return;

        files.slice(0, 12).forEach((f) => {
          const col = document.createElement('div');
          col.className = 'col-4';

          const box = document.createElement('div');
          box.className = 'border rounded-3 overflow-hidden bg-white';
          box.style.aspectRatio = '1 / 1';

          const im = document.createElement('img');
          im.style.width = '100%';
          im.style.height = '100%';
          im.style.objectFit = 'cover';
          im.alt = 'preview';

          const url = URL.createObjectURL(f);
          im.src = url;
          im.onload = () => { try { URL.revokeObjectURL(url); } catch(e) {} };

          box.appendChild(im);
          col.appendChild(box);
          gWrap.appendChild(col);
        });

        if (files.length > 12) {
          const more = document.createElement('div');
          more.className = 'col-12 small text-muted';
          more.textContent = 'แสดงพรีวิวสูงสุด 12 รูป (ที่เหลือจะอัปโหลดตามที่เลือก)';
          gWrap.appendChild(more);
        }
      });
    }
  })();
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>