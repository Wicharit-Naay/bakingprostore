<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  // ดึง user ได้ทั้ง admin และ customer
  $stmt = $pdo->prepare("SELECT id, role, name, email, password_hash FROM users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if ($u && password_verify($pass, $u['password_hash'])) {

    // เก็บ session เดิม
    $_SESSION['user'] = [
      'id'   => (int)$u['id'],
      'name' => $u['name'],
      'role' => $u['role'],
      'email'=> $u['email'],
    ];

    if ($u['role'] === 'admin') {
      redirect('/admin/index.php');   // เข้าเมนูหลังร้าน
    } else {
      redirect('/public/index.php');  // เข้าหน้าร้าน
    }

  } else {
    $msg = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
  }
}

include __DIR__ . '/../templates/header.php';
?>
<h2>เข้าสู่ระบบ</h2>

<div class="box">
  <?php if($msg) echo "<p style='color:red;'>".h($msg)."</p>"; ?>
  <form method="post">
    อีเมล:
    <input name="email" autocomplete="username">

    รหัสผ่าน:
    <input type="password" name="password" autocomplete="current-password">

    <button type="submit">เข้าสู่ระบบ</button>
  </form>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>