<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';

require_admin();

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
    $err = "อัปโหลดรูปไม่สำเร็จ";
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

  if ($category_id <= 0) $err = "กรุณาเลือกประเภทสินค้า";
  elseif ($name === '') $err = "กรุณากรอกชื่อสินค้า";
  elseif ($price < 0) $err = "ราคาไม่ถูกต้อง";
  elseif ($stock < 0) $err = "สต๊อกไม่ถูกต้อง";

  // อัปโหลดรูปถ้ามี
  $newImage = null;
  if (!$err && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $newImage = upload_image($_FILES['image'], $err);
  }

  if (!$err) {
    // ถ้าอัปโหลดรูปใหม่ → ใช้รูปใหม่ ไม่งั้นใช้ของเดิม
    $imagePath = $newImage ? $newImage : $p['image'];

    $up = $pdo->prepare("UPDATE products
                         SET category_id=?, name=?, price=?, stock=?, description=?, image=?
                         WHERE id=?");
    $up->execute([$category_id, $name, $price, $stock, $description, $imagePath, $id]);

    $msg = "บันทึกแล้ว";

    // โหลดข้อมูลใหม่หลังบันทึก
    $stmt->execute([$id]);
    $p = $stmt->fetch();
  }
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<h2>แก้ไขสินค้า</h2>

<div class="box">
  <?php if($err) echo "<p style='color:red;'>".h($err)."</p>"; ?>
  <?php if($msg) echo "<p style='color:green;'>".h($msg)."</p>"; ?>

  <form method="post" enctype="multipart/form-data">
    <label>ประเภทสินค้า</label>
    <select name="category_id">
      <option value="0">-- เลือกประเภทสินค้า --</option>
      <?php foreach($cats as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)$p['category_id']===(int)$c['id'])?'selected':'' ?>>
          <?= h($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>ชื่อสินค้า</label>
    <input name="name" value="<?= h($p['name']) ?>">

    <label>ราคา</label>
    <input type="number" step="0.01" name="price" value="<?= h($p['price']) ?>">

    <label>สต๊อก</label>
    <input type="number" name="stock" value="<?= h($p['stock']) ?>">

    <label>รายละเอียด</label>
    <textarea name="description" rows="4"><?= h($p['description']) ?></textarea>

    <label>รูปสินค้า (รูปหลัก)</label>
    <input type="file" name="image" accept="image/*">

    <?php if(!empty($p['image'])): ?>
      <p class="small">รูปปัจจุบัน:</p>
      <img src="<?= BASE_URL . '/' . h($p['image']) ?>" alt="" style="max-width:220px;border:1px solid #ddd;">
    <?php else: ?>
      <p class="small">ยังไม่มีรูป</p>
    <?php endif; ?>

    <div style="margin-top:10px;">
      <button type="submit">บันทึก</button>
      <a href="<?= BASE_URL ?>/admin/products/index.php">กลับ</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>