<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->execute([$id, $_SESSION['user']['id']]);
$o = $stmt->fetch();
if(!$o) die("ไม่พบออเดอร์");

include __DIR__ . '/../templates/header.php';
?>
<h2>สถานะออเดอร์ #<?= (int)$o['id'] ?></h2>

<div class="box">
  สถานะ: <b><?= h($o['status']) ?></b><br>
  ยอดรวม: <?= number_format((float)$o['total'],2) ?><br>
  วันที่สั่ง: <?= h($o['created_at']) ?><br>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>