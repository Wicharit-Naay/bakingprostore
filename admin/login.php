<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../templates/admin_header.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role='admin'");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if ($u && password_verify($pass, $u['password_hash'])) {
    $_SESSION['user'] = ['id'=>$u['id'],'name'=>$u['name'],'role'=>$u['role']];
    redirect('/admin/index.php');
  } else {
    $msg = 'เข้าสู่ระบบไม่สำเร็จ';
  }
}

?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin Login</title></head><body>
<h3>หลังร้าน - เข้าสู่ระบบ</h3>
<?php if($msg) echo "<p style='color:red;'>".h($msg)."</p>"; ?>
<form method="post">
  Email: <input name="email"><br>
  Pass: <input type="password" name="password"><br>
  <button>Login</button>
</form>
</body></html>