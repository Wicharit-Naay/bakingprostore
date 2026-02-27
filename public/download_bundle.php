

<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ต้องล็อกอิน
if (empty($_SESSION['user']['id'])) {
  header('Location: ' . BASE_URL . '/public/login.php');
  exit;
}

$userId = (int)$_SESSION['user']['id'];
$orderId = (int)($_GET['order_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);

if ($orderId <= 0 || $productId <= 0) {
  http_response_code(400);
  echo 'Bad request';
  exit;
}

// 1) ตรวจว่าออเดอร์เป็นของ user นี้ และชำระเงินแล้ว
$o = $pdo->prepare("SELECT id, user_id, status FROM orders WHERE id=? LIMIT 1");
$o->execute([$orderId]);
$order = $o->fetch(PDO::FETCH_ASSOC);
if (!$order || (int)$order['user_id'] !== $userId) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}
if (($order['status'] ?? '') !== 'paid') {
  http_response_code(403);
  echo 'Order not paid';
  exit;
}

// 2) ตรวจว่าสินค้านี้อยู่ในออเดอร์จริง
$oi = $pdo->prepare("SELECT id FROM order_items WHERE order_id=? AND product_id=? LIMIT 1");
$oi->execute([$orderId, $productId]);
$hasItem = $oi->fetchColumn();
if (!$hasItem) {
  http_response_code(404);
  echo 'Item not found in this order';
  exit;
}

// 3) ดึงข้อมูล bundle ของสินค้า
$p = $pdo->prepare("SELECT id, name, is_bundle, bundle_link, bundle_type FROM products WHERE id=? LIMIT 1");
$p->execute([$productId]);
$product = $p->fetch(PDO::FETCH_ASSOC);

if (!$product || (int)($product['is_bundle'] ?? 0) !== 1) {
  http_response_code(404);
  echo 'This product has no bundle';
  exit;
}

$type = (string)($product['bundle_type'] ?? 'drive');
$link = trim((string)($product['bundle_link'] ?? ''));

if ($link === '') {
  http_response_code(404);
  echo 'Bundle link not set';
  exit;
}

// 4) ส่งตามประเภท
// - drive/video: redirect ออกไป
// - internal: เสิร์ฟไฟล์จากเซิร์ฟเวอร์ (ปลอดภัยกว่า ไม่เปิด path ตรง ๆ)
if ($type === 'internal') {
  // คาดว่าเก็บเป็น path เช่น uploads/bundles/xxx.pdf
  $rel = ltrim($link, '/');
  $full = realpath(__DIR__ . '/../' . $rel);
  $base = realpath(__DIR__ . '/../uploads');

  // กัน path traversal: ต้องอยู่ใต้โฟลเดอร์ uploads เท่านั้น
  if (!$full || !$base || strpos($full, $base) !== 0 || !is_file($full)) {
    http_response_code(404);
    echo 'File not found';
    exit;
  }

  $filename = basename($full);
  $mime = 'application/octet-stream';
  if (function_exists('mime_content_type')) {
    $m = @mime_content_type($full);
    if ($m) $mime = $m;
  }

  header('Content-Type: ' . $mime);
  header('Content-Disposition: inline; filename="' . $filename . '"');
  header('Content-Length: ' . filesize($full));
  header('X-Content-Type-Options: nosniff');

  readfile($full);
  exit;
}

// default: redirect
// หมายเหตุ: ถ้าต้องการ “ดาวน์โหลด” ให้ใช้ type=pdf/internal แล้วเสิร์ฟไฟล์ด้วย headers
header('Location: ' . $link);
exit;