<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

$uid = (int)$_SESSION['user']['id'];
$msg = '';

$stmt = $pdo->prepare("SELECT name,email,phone,address FROM users WHERE id=?");
$stmt->execute([$uid]);
$u = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');

  $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=?");
  $stmt->execute([$name,$phone,$address,$uid]);

  $_SESSION['user']['name'] = $name;
  $msg = 'บันทึกแล้ว';

  $stmt = $pdo->prepare("SELECT name,email,phone,address FROM users WHERE id=?");
  $stmt->execute([$uid]);
  $u = $stmt->fetch();
}

include __DIR__ . '/../templates/header.php';
?>
<h2>ข้อมูลส่วนตัว</h2>
<div class="box">
  <?php if($msg) echo "<p style='color:green;'>".h($msg)."</p>"; ?>
  <form method="post">
    ชื่อ: <input name="name" value="<?= h($u['name']) ?>">
    อีเมล: <input value="<?= h($u['email']) ?>" disabled>
    เบอร์: <input name="phone" value="<?= h($u['phone']) ?>">
    ที่อยู่: <textarea name="address"><?= h($u['address']) ?></textarea>
    <button type="submit">บันทึก</button>
  </form>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>