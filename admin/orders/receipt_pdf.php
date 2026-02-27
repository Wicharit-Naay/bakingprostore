

<?php
// admin/orders/receipt_pdf.php
// พิมพ์ใบเสร็จ (PDF) — ใช้ mPDF ถ้ามี ไม่มีก็ fallback เป็นหน้า HTML ให้กดพิมพ์ได้

require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/functions.php';

$authFile = __DIR__ . '/../../helpers/auth.php';
if (file_exists($authFile)) {
  require_once $authFile;
}

if (function_exists('require_admin')) {
  require_admin();
} elseif (function_exists('require_login')) {
  require_login();
  $role = $_SESSION['user']['role'] ?? ($_SESSION['role'] ?? '');
  if ($role !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
  }
}

function status_th_label(string $status): string {
  return match (strtolower(trim($status))) {
    'pending'   => 'รอดำเนินการ',
    'paid'      => 'ชำระแล้ว',
    'shipping'  => 'กำลังจัดส่ง',
    'completed' => 'สำเร็จ',
    'cancelled', 'canceled' => 'ยกเลิก',
    default => $status !== '' ? $status : '-',
  };
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  exit('ไม่พบออเดอร์');
}

// Load order + customer
$stmt = $pdo->prepare(
  "SELECT o.*, u.name AS customer_name, u.email AS customer_email,\n"
  . "       u.phone AS customer_phone, u.address AS customer_address\n"
  . "FROM orders o\n"
  . "JOIN users u ON u.id = o.user_id\n"
  . "WHERE o.id=?"
);
$stmt->execute([$id]);
$o = $stmt->fetch();
if (!$o) {
  http_response_code(404);
  exit('ไม่พบออเดอร์');
}

$itStmt = $pdo->prepare(
  "SELECT oi.*, p.name AS product_name\n"
  . "FROM order_items oi\n"
  . "JOIN products p ON p.id = oi.product_id\n"
  . "WHERE oi.order_id=?"
);
$itStmt->execute([$id]);
$items = $itStmt->fetchAll();

// Shipping (รองรับหลายชื่อคอลัมน์)
$orderCols = [];
try {
  $rs = $pdo->query("SHOW COLUMNS FROM orders");
  foreach ($rs->fetchAll() as $r) {
    if (!empty($r['Field'])) $orderCols[] = $r['Field'];
  }
} catch (Throwable $e) {}

$col = fn(string $name) => in_array($name, $orderCols, true);

$shipName = '';
$shipPhone = '';
$shipAddr = '';

if ($col('ship_name'))    $shipName  = (string)($o['ship_name'] ?? '');
if ($col('ship_phone'))   $shipPhone = (string)($o['ship_phone'] ?? '');
if ($col('ship_address')) $shipAddr  = (string)($o['ship_address'] ?? '');

if ($shipName === '' && $col('shipping_name'))    $shipName  = (string)($o['shipping_name'] ?? '');
if ($shipPhone === '' && $col('shipping_phone'))  $shipPhone = (string)($o['shipping_phone'] ?? '');
if ($shipAddr === '' && $col('shipping_address')) $shipAddr  = (string)($o['shipping_address'] ?? '');

$shipName  = $shipName  !== '' ? $shipName  : (string)($o['customer_name'] ?? '');
$shipPhone = $shipPhone !== '' ? $shipPhone : (string)($o['customer_phone'] ?? '');
$shipAddr  = $shipAddr  !== '' ? $shipAddr  : (string)($o['customer_address'] ?? '');

$paymentMethod = '';
if ($col('payment_method')) $paymentMethod = (string)($o['payment_method'] ?? '');
if ($paymentMethod === '' && $col('pay_method')) $paymentMethod = (string)($o['pay_method'] ?? '');

$paymentLabel = match ($paymentMethod) {
  'cod' => 'ชำระเงินปลายทาง (COD)',
  'bank_qr' => 'โอนผ่าน QR / แนบสลิป',
  '' => '-',
  default => $paymentMethod,
};

$createdAt = (string)($o['created_at'] ?? ($o['createdAt'] ?? ($o['created'] ?? '')));
$statusTh = status_th_label((string)($o['status'] ?? ''));

// คำนวณยอดรวมจาก items (เผื่อ schema ต่างกัน)
$subTotal = 0.0;
foreach ($items as $it) {
  $qty = (int)($it['qty'] ?? 0);
  $price = (float)($it['price'] ?? 0);
  $subTotal += ($qty * $price);
}
$grandTotal = (float)($o['total'] ?? $subTotal);

// HTML ใบเสร็จ
$logoFs = __DIR__ . '/../../assets/img/logo.png';
$logoDataUri = '';
if (is_file($logoFs)) {
  $ext = strtolower(pathinfo($logoFs, PATHINFO_EXTENSION));
  $mime = match ($ext) {
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    default => 'image/png',
  };
  $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($logoFs));
}

$shopName = 'BakingProStore';
$shopLine1 = 'ใบเสร็จรับเงิน / RECEIPT';

// note: ใช้ output buffer เพื่อส่งให้ mPDF
ob_start();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Receipt #<?= (int)$o['id'] ?></title>
  <style>
    @page { margin: 14mm; }
    body{ font-family: sans-serif; color:#111; font-size: 12px; }
    .row{ display:flex; justify-content:space-between; gap:12px; }
    .muted{ color:#6b7280; }
    .h1{ font-size: 18px; font-weight: 700; margin: 0; }
    .h2{ font-size: 13px; font-weight: 700; margin: 0; }
    .card{ border:1px solid #e5e7eb; border-radius: 10px; padding: 10px; }
    table{ width:100%; border-collapse: collapse; }
    th,td{ border-bottom:1px solid #e5e7eb; padding: 8px 6px; vertical-align: top; }
    th{ text-align:left; background:#f9fafb; font-weight:700; }
    .right{ text-align:right; }
    .center{ text-align:center; }
    .badge{ display:inline-block; padding: 3px 8px; border-radius: 999px; background:#eef2ff; border:1px solid #e5e7eb; font-size: 11px; }
    .totalBox{ margin-top: 10px; }
    .totalRow{ display:flex; justify-content:space-between; padding: 6px 0; }
    .totalRow strong{ font-size: 14px; }
    .small{ font-size: 11px; }
    .hr{ height:1px; background:#e5e7eb; margin: 10px 0; }
    .printOnlyNote{ display:none; }
    @media print{
      .noPrint{ display:none !important; }
      .printOnlyNote{ display:block; }
    }
  </style>
</head>
<body>

<div class="noPrint" style="margin-bottom:10px;">
  <button onclick="window.print()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; background:#fff; cursor:pointer;">พิมพ์ / Print</button>
</div>

<div class="row" style="align-items:flex-start;">
  <div style="flex:1;">
    <div class="row" style="align-items:center; justify-content:flex-start; gap:10px;">
      <?php if ($logoDataUri): ?>
        <img src="<?= htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8') ?>" alt="logo" style="width:42px;height:42px;object-fit:contain;border:1px solid #eee;border-radius:10px;">
      <?php endif; ?>
      <div>
        <div class="h1"><?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="muted small"><?= htmlspecialchars($shopLine1, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>

  <div style="width: 280px;" class="card">
    <div class="row"><div class="muted">เลขที่ออเดอร์</div><div><b>#<?= (int)$o['id'] ?></b></div></div>
    <div class="row"><div class="muted">วันที่</div><div><?= htmlspecialchars($createdAt ?: '-', ENT_QUOTES, 'UTF-8') ?></div></div>
    <div class="row"><div class="muted">สถานะ</div><div><span class="badge"><?= htmlspecialchars($statusTh, ENT_QUOTES, 'UTF-8') ?></span></div></div>
    <div class="row"><div class="muted">ชำระเงิน</div><div><?= htmlspecialchars($paymentLabel, ENT_QUOTES, 'UTF-8') ?></div></div>
  </div>
</div>

<div class="hr"></div>

<div class="row" style="gap:12px;">
  <div class="card" style="flex:1;">
    <div class="h2">ข้อมูลลูกค้า</div>
    <div class="small muted">Customer</div>
    <div style="margin-top:6px;"><b><?= htmlspecialchars((string)($o['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></b></div>
    <div class="muted small"><?= htmlspecialchars((string)($o['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="muted small"><?= htmlspecialchars((string)($o['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
  </div>

  <div class="card" style="flex:1;">
    <div class="h2">ที่อยู่จัดส่ง</div>
    <div class="small muted">Shipping</div>
    <div style="margin-top:6px;"><b><?= htmlspecialchars($shipName, ENT_QUOTES, 'UTF-8') ?></b></div>
    <div class="muted small"><?= htmlspecialchars($shipPhone, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="small" style="white-space:pre-wrap; margin-top:6px;">
      <?= htmlspecialchars($shipAddr ?: '-', ENT_QUOTES, 'UTF-8') ?>
    </div>
  </div>
</div>

<div class="hr"></div>

<div class="card">
  <div class="h2">รายการสินค้า</div>
  <div class="small muted">Items</div>

  <table style="margin-top:8px;">
    <thead>
      <tr>
        <th style="width:38px;">#</th>
        <th>สินค้า</th>
        <th class="right" style="width:90px;">ราคา</th>
        <th class="center" style="width:70px;">จำนวน</th>
        <th class="right" style="width:110px;">รวม</th>
      </tr>
    </thead>
    <tbody>
      <?php $i=1; foreach($items as $it):
        $name = (string)($it['product_name'] ?? '');
        $qty = (int)($it['qty'] ?? 0);
        $price = (float)($it['price'] ?? 0);
        $line = $qty * $price;
      ?>
        <tr>
          <td class="center"><?= $i ?></td>
          <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
          <td class="right"><?= number_format($price, 2) ?></td>
          <td class="center"><?= $qty ?></td>
          <td class="right"><?= number_format($line, 2) ?></td>
        </tr>
      <?php $i++; endforeach; ?>
    </tbody>
  </table>

  <div class="totalBox">
    <div class="totalRow"><div class="muted">ยอดรวมสินค้า</div><div><?= number_format($subTotal, 2) ?> ฿</div></div>
    <div class="totalRow"><strong>ยอดรวมทั้งสิ้น</strong><strong><?= number_format($grandTotal, 2) ?> ฿</strong></div>
  </div>
</div>

<div style="margin-top:10px;" class="small muted printOnlyNote">
  เอกสารนี้ออกโดยระบบ BakingProStore
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ถ้ามี mPDF ให้สร้างเป็น PDF
$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($vendorAutoload)) {
  require_once $vendorAutoload;

  try {
    // mPDF
    $mpdf = new \Mpdf\Mpdf([
      'mode' => 'utf-8',
      'format' => 'A4',
      'margin_left' => 14,
      'margin_right' => 14,
      'margin_top' => 14,
      'margin_bottom' => 14,
    ]);

    $mpdf->SetTitle('Receipt #' . (int)$o['id']);
    $mpdf->WriteHTML($html);

    // Inline view
    $filename = 'receipt_' . (int)$o['id'] . '.pdf';
    $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
    exit;
  } catch (Throwable $e) {
    // fallback to HTML
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
  }
}

header('Content-Type: text/html; charset=utf-8');
echo $html;