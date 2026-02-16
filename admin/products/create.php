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

  if ($category_id <= 0) $err = "กรุณาเลือกประเภทสินค้า";
  elseif ($name === '') $err = "กรุณากรอกชื่อสินค้า";
  elseif ($price < 0) $err = "ราคาไม่ถูกต้อง";
  elseif ($stock < 0) $err = "สต๊อกไม่ถูกต้อง";

  // อัปโหลดรูปถ้ามี
  $imagePath = null;
  if (!$err && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imagePath = upload_image($_FILES['image'], $err);
  }

  if (!$err) {
    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, price, stock, description, image)
                           VALUES (?,?,?,?,?,?)");
    $stmt->execute([$category_id, $name, $price, $stock, $description, $imagePath]);

    $newId = (int)$pdo->lastInsertId();
    // บันทึกแล้วเด้งไปหน้าแก้ไขต่อได้เลย
    header("Location: " . BASE_URL . "/admin/products/edit.php?id=" . $newId);
    exit;
  }
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<h2>เพิ่มสินค้า</h2>

<div class="box">
  <?php if($err) echo "<p style='color:red;'>".h($err)."</p>"; ?>
  <?php if(!$cats): ?>
    <p style="color:red;">ยังไม่มีประเภทสินค้า กรุณาเพิ่มประเภทสินค้าก่อน</p>
    <a href="<?= BASE_URL ?>/admin/categories/create.php">ไปเพิ่มประเภทสินค้า</a>
  <?php else: ?>
    <form method="post" enctype="multipart/form-data">
      <label>ประเภทสินค้า</label>
      <select name="category_id">
        <option value="0">-- เลือกประเภทสินค้า --</option>
        <?php foreach($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)($_POST['category_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>>
            <?= h($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>ชื่อสินค้า</label>
      <input name="name" value="<?= h($_POST['name'] ?? '') ?>">

      <label>ราคา</label>
      <input type="number" step="0.01" name="price" value="<?= h($_POST['price'] ?? '0') ?>">

      <label>สต๊อก</label>
      <input type="number" name="stock" value="<?= h($_POST['stock'] ?? '0') ?>">

      <label>รายละเอียด</label>
      <textarea name="description" rows="4"><?= h($_POST['description'] ?? '') ?></textarea>

      <label>รูปสินค้า (รูปหลัก)</label>
      <input type="file" name="image" accept="image/*">

      <div style="margin-top:10px;">
        <button type="submit">บันทึก</button>
        <a href="<?= BASE_URL ?>/admin/products/index.php">กลับ</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>