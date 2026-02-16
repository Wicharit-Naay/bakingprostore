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

<h2>แก้ไขประเภทสินค้า</h2>

<div class="box">
  <?php if($err) echo "<p style='color:red;'>".h($err)."</p>"; ?>
  <?php if($msg) echo "<p style='color:green;'>".h($msg)."</p>"; ?>

  <form method="post">
    <label>ชื่อประเภทสินค้า</label>
    <input name="name" value="<?= h($name) ?>">

    <div style="margin-top:10px;">
      <button type="submit">บันทึก</button>
      <a href="<?= BASE_URL ?>/admin/categories/index.php">กลับ</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>