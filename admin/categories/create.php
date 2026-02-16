<?php
require_once __DIR__ . '/../../config/db.php';
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

<h2>เพิ่มประเภทสินค้า</h2>

<div class="box">
  <?php if($err) echo "<p style='color:red;'>".h($err)."</p>"; ?>

  <form method="post">
    <label>ชื่อประเภทสินค้า</label>
    <input name="name" value="<?= h($name) ?>" placeholder="เช่น วัตถุดิบ, แก้ว, ภาชนะ">

    <div style="margin-top:10px;">
      <button type="submit">บันทึก</button>
      <a href="<?= BASE_URL ?>/admin/categories/index.php">กลับ</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>