<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass = $_POST['password'] ?? '';

  if ($name==='' || $email==='' || $pass==='') {
    $msg = 'กรอกข้อมูลให้ครบ';
  } else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    try {
      $stmt = $pdo->prepare("INSERT INTO users(role,name,email,password_hash) VALUES('customer',?,?,?)");
      $stmt->execute([$name,$email,$hash]);
      redirect('/public/login.php');
    } catch(Exception $e) {
      $msg = 'อีเมลซ้ำ หรือเกิดข้อผิดพลาด';
    }
  }
}

include __DIR__ . '/../templates/header.php';
?>
<h2>สมัครสมาชิก</h2>
<div class="box">
  <?php if($msg) echo "<p style='color:red;'>".h($msg)."</p>"; ?>
  <form method="post">
    ชื่อ: <input name="name">
    อีเมล: <input name="email">
    รหัสผ่าน: <input type="password" name="password">
    <button type="submit">สมัคร</button>
  </form>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>