<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/cart.php';

require_login();
cart_init();

$cart = $_SESSION['cart'];
if (!$cart) die("ไม่มีสินค้าในตะกร้า");

$ids = array_keys($cart);
$in = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
$stmt->execute($ids);
$products = $stmt->fetchAll();

$total = 0;
foreach($products as $p){
  $qty = (int)$cart[$p['id']];
  $total += $qty * (float)$p['price'];
}

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO orders(user_id,total,status) VALUES(?,?, 'pending')");
    $stmt->execute([$_SESSION['user']['id'], $total]);
    $order_id = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO order_items(order_id,product_id,qty,price) VALUES(?,?,?,?)");
    $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?");

    foreach($products as $p){
      $qty = (int)$cart[$p['id']];
      $price = (float)$p['price'];

      $itemStmt->execute([$order_id, $p['id'], $qty, $price]);

      $stockStmt->execute([$qty, $p['id'], $qty]);
      if ($stockStmt->rowCount() === 0) {
        throw new Exception("สต็อกไม่พอ: ".$p['name']);
      }
    }

    $pdo->commit();
    cart_clear();
    redirect('/public/orders.php');
  } catch(Exception $e) {
    $pdo->rollBack();
    $msg = 'สั่งซื้อไม่สำเร็จ: '.$e->getMessage();
  }
}

include __DIR__ . '/../templates/header.php';
?>
<h2>สั่งซื้อสินค้า</h2>

<div class="box">
  <?php if($msg) echo "<p style='color:red;'>".h($msg)."</p>"; ?>
  ยอดรวม: <b><?= number_format((float)$total,2) ?></b><br><br>
  <form method="post">
    <button type="submit">ยืนยันสั่งซื้อ</button>
  </form>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>