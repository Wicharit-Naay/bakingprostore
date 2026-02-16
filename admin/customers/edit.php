<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id,name,email,phone,address FROM users WHERE id=? AND role='customer'");
$stmt->execute([$id]);
$u = $stmt->fetch();
if(!$u) die("ไม่พบลูกค้า");

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');

  $up = $pdo->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=? AND role='customer'");
  $up->execute([$name,$phone,$address,$id]);
  $msg = 'บันทึกแล้ว';

  $stmt->execute([$id]);
  $u = $stmt->fetch();
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>
<h2>แก้ไขข้อมูลลูกค้า</h2>

<div class="box">
  <?php if($msg) echo "<p style='color:green;'>".h($msg)."</p>"; ?>
  <form method="post">
    ชื่อ: <input name="name" value="<?= h($u['name']) ?>">
    อีเมล: <input value="<?= h($u['email']) ?>" disabled>
    เบอร์: <input name="phone" value="<?= h($u['phone']) ?>">
    ที่อยู่จัดส่ง: <textarea name="address"><?= h($u['address']) ?></textarea>
    <button type="submit">บันทึก</button>
    <a href="<?= BASE_URL ?>/admin/customers/index.php">กลับ</a>
  </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>