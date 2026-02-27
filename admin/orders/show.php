<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
// DEBUG (ปิดได้ตอนขึ้นโปรดักชัน)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ถ้า connectdb.php ไม่ได้ตั้งไว้ ให้เปิด exception สำหรับ PDO
if (isset($pdo) && $pdo instanceof PDO) {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  die('ไม่พบออเดอร์');
}

// ===== Helpers =====
function order_status_badge(string $status): array {
  $s = strtolower(trim($status));
  return match ($s) {
    'pending'   => ['รอดำเนินการ', 'secondary', 'bi-hourglass-split'],
    'paid'      => ['ชำระแล้ว', 'primary', 'bi-check2-circle'],
    'shipping'  => ['กำลังจัดส่ง', 'warning', 'bi-truck'],
    'completed' => ['สำเร็จ', 'success', 'bi-bag-check'],
    'cancelled', 'canceled' => ['ยกเลิก', 'danger', 'bi-x-circle'],
    default     => [$status !== '' ? $status : 'ไม่ทราบสถานะ', 'light', 'bi-question-circle'],
  };
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

function col_exists(array $cols, string $name): bool {
  return in_array($name, $cols, true);
}

function get_table_columns(PDO $pdo, string $table): array {
  $cols = [];
  try {
    $rs = $pdo->query("SHOW COLUMNS FROM {$table}");
    foreach ($rs->fetchAll() as $r) {
      if (!empty($r['Field'])) $cols[] = $r['Field'];
    }
  } catch (Throwable $e) {
    // ignore
  }
  return $cols;
}

// ===== Detect columns =====
$orderCols = get_table_columns($pdo, 'orders');

// ===== Load order + customer =====
try {
  $stmt = $pdo->prepare(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email,\n"
    . "       u.phone AS customer_phone, u.address AS customer_address\n"
    . "FROM orders o\n"
    . "JOIN users u ON u.id = o.user_id\n"
    . "WHERE o.id=?"
  );
  $stmt->execute([$id]);
  $o = $stmt->fetch();
} catch (Throwable $e) {
  http_response_code(500);
  echo '<pre style="padding:12px;background:#fff3cd;border:1px solid #ffe69c;border-radius:10px;">';
  echo 'Query error (orders/users): ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  echo '</pre>';
  exit;
}
if (!$o) {
  die('ไม่พบออเดอร์');
}

// ===== Items =====
try {
  $itStmt = $pdo->prepare(
    "SELECT oi.*, p.name AS product_name, p.image AS product_image\n"
    . "FROM order_items oi\n"
    . "JOIN products p ON p.id = oi.product_id\n"
    . "WHERE oi.order_id=?"
  );
  $itStmt->execute([$id]);
  $items = $itStmt->fetchAll();
} catch (Throwable $e) {
  http_response_code(500);
  echo '<pre style="padding:12px;background:#fff3cd;border:1px solid #ffe69c;border-radius:10px;">';
  echo 'Query error (order_items/products): ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  echo '</pre>';
  exit;
}

// ===== Status Logs (optional) =====
$logs = [];
try {
  $chk = $pdo->query("SHOW TABLES LIKE 'order_status_logs'")->fetch();
  if ($chk) {
    $lg = $pdo->prepare("SELECT * FROM order_status_logs WHERE order_id=? ORDER BY id DESC");
    $lg->execute([$id]);
    $logs = $lg->fetchAll();
  }
} catch (Throwable $e) {
  // ignore (table may not exist)
}

// ===== Shipping (prefer order columns, fallback to user) =====
$shipName = '';
$shipPhone = '';
$shipAddr = '';

if (col_exists($orderCols, 'ship_name'))    $shipName  = (string)($o['ship_name'] ?? '');
if (col_exists($orderCols, 'ship_phone'))   $shipPhone = (string)($o['ship_phone'] ?? '');
if (col_exists($orderCols, 'ship_address')) $shipAddr  = (string)($o['ship_address'] ?? '');

if ($shipName === '' && col_exists($orderCols, 'shipping_name'))    $shipName  = (string)($o['shipping_name'] ?? '');
if ($shipPhone === '' && col_exists($orderCols, 'shipping_phone'))  $shipPhone = (string)($o['shipping_phone'] ?? '');
if ($shipAddr === '' && col_exists($orderCols, 'shipping_address')) $shipAddr  = (string)($o['shipping_address'] ?? '');

$shipName  = $shipName  !== '' ? $shipName  : (string)($o['customer_name'] ?? '');
$shipPhone = $shipPhone !== '' ? $shipPhone : (string)($o['customer_phone'] ?? '');
$shipAddr  = $shipAddr  !== '' ? $shipAddr  : (string)($o['customer_address'] ?? '');

// ===== Payment (optional) =====
$paymentMethod = '';
$paymentSlip = '';
if (col_exists($orderCols, 'payment_method')) $paymentMethod = (string)($o['payment_method'] ?? '');
if (col_exists($orderCols, 'payment_slip'))   $paymentSlip   = (string)($o['payment_slip'] ?? '');
if ($paymentMethod === '' && col_exists($orderCols, 'pay_method')) $paymentMethod = (string)($o['pay_method'] ?? '');
if ($paymentSlip === '' && col_exists($orderCols, 'pay_slip'))     $paymentSlip   = (string)($o['pay_slip'] ?? '');

$paymentLabel = match ($paymentMethod) {
  'cod' => 'ชำระเงินปลายทาง (COD)',
  'bank_qr' => 'โอนผ่าน QR / แนบสลิป',
  '' => '-',
  default => $paymentMethod,
};

$createdAt = $o['created_at'] ?? ($o['createdAt'] ?? ($o['created'] ?? ''));
[$stLabel, $stColor, $stIcon] = order_status_badge((string)($o['status'] ?? ''));

$pageTitle = 'รายละเอียดออเดอร์ #' . (int)$o['id'];
require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">รายละเอียดออเดอร์ #<?= (int)$o['id'] ?></h2>
    <div class="text-muted small">ดูข้อมูลลูกค้า, ที่อยู่จัดส่ง, รายการสินค้า และอัปเดตสถานะ</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/orders/index.php">
      <i class="bi bi-arrow-left me-1"></i>กลับรายการ
    </a>
    <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/orders/receipt_pdf.php?id=<?= (int)$o['id'] ?>" target="_blank" rel="noopener">
      <i class="bi bi-printer me-1"></i>พิมพ์ใบเสร็จ
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-8">

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="text-muted small">สถานะปัจจุบัน</div>
            <span class="badge text-bg-<?= h($stColor) ?>">
              <i class="bi <?= h($stIcon) ?> me-1"></i><?= h($stLabel) ?>
            </span>
          </div>
          <div class="text-end">
            <div class="text-muted small">ยอดรวม</div>
            <div class="fs-4 fw-semibold"><?= number_format((float)($o['total'] ?? 0), 2) ?> ฿</div>
          </div>
        </div>

        <hr class="my-3">

        <div class="row g-2">
          <div class="col-12 col-md-6">
            <div class="text-muted small">ลูกค้า</div>
            <div class="fw-semibold"><?= h($o['customer_name'] ?? '') ?></div>
            <div class="small text-muted"><?= h($o['customer_email'] ?? '') ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">วันที่สั่ง</div>
            <div class="fw-semibold"><?= h((string)$createdAt ?: '-') ?></div>
            <div class="text-muted small">วิธีชำระเงิน: <span class="fw-semibold text-dark"><?= h($paymentLabel) ?></span></div>
          </div>
        </div>

        <?php if ($paymentSlip !== ''): ?>
          <hr class="my-3">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <div class="fw-semibold"><i class="bi bi-receipt-cutoff me-1"></i>สลิปการโอน</div>
              <div class="text-muted small">ไฟล์: <?= h($paymentSlip) ?></div>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL . '/' . ltrim(h($paymentSlip), '/') ?>" target="_blank" rel="noopener">
              <i class="bi bi-box-arrow-up-right me-1"></i>เปิดสลิป
            </a>
          </div>
          <div class="mt-2 ratio ratio-4x3 border rounded-3 overflow-hidden bg-light">
            <img src="<?= BASE_URL . '/' . ltrim(h($paymentSlip), '/') ?>" alt="slip" style="object-fit:contain" loading="lazy">
          </div>
        <?php endif; ?>

      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-diagram-3 me-1"></i>ไทม์ไลน์สถานะ</div>
        <span class="badge text-bg-light border">ปัจจุบัน: <?= h(status_th_label((string)($o['status'] ?? ''))) ?></span>
      </div>
      <div class="card-body">
        <?php
          $cur = strtolower(trim((string)($o['status'] ?? 'pending')));
          $steps = [
            'pending' => ['รอดำเนินการ', 'bi-hourglass-split'],
            'paid' => ['ชำระแล้ว', 'bi-check2-circle'],
            'shipping' => ['กำลังจัดส่ง', 'bi-truck'],
            'completed' => ['สำเร็จ', 'bi-bag-check'],
          ];
          $orderIndex = array_keys($steps);
          $curPos = array_search($cur, $orderIndex, true);
          if ($curPos === false) $curPos = 0;
        ?>

        <div class="row g-2">
          <?php $i = 0; foreach ($steps as $key => [$label, $icon]): ?>
            <?php
              $state = 'upcoming';
              if ($i < $curPos) $state = 'done';
              if ($i === $curPos) $state = 'current';
              $dotClass = $state === 'done' ? 'bg-success' : ($state === 'current' ? 'bg-primary' : 'bg-secondary');
              $textClass = $state === 'upcoming' ? 'text-muted' : 'text-dark';
              $borderClass = $state === 'current' ? 'border-primary' : 'border-light';
            ?>
            <div class="col-12 col-md-3">
              <div class="border rounded-3 p-3 h-100 <?= h($borderClass) ?>" style="background:#fff;">
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-circle d-inline-flex align-items-center justify-content-center <?= h($dotClass) ?>" style="width:34px;height:34px;color:#fff;">
                    <i class="bi <?= h($icon) ?>"></i>
                  </span>
                  <div>
                    <div class="fw-semibold <?= h($textClass) ?>" style="line-height:1.1;"><?= h($label) ?></div>
                    <div class="small text-muted" style="line-height:1.1;">
                      <?= $state === 'done' ? 'เสร็จแล้ว' : ($state === 'current' ? 'กำลังอยู่ขั้นนี้' : 'รอดำเนินการ') ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php $i++; endforeach; ?>
        </div>

        <?php if (in_array($cur, ['cancelled','canceled'], true)): ?>
          <div class="alert alert-danger small mt-3 mb-0">
            <i class="bi bi-x-circle me-1"></i>ออเดอร์นี้ถูกยกเลิก
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-clock-history me-1"></i>ประวัติการเปลี่ยนสถานะ</div>
        <span class="badge text-bg-light border"><?= count($logs) ?> รายการ</span>
      </div>
      <div class="card-body">
        <?php if (empty($logs)): ?>
          <div class="text-muted small">ยังไม่มีประวัติการเปลี่ยนสถานะ</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($logs as $lg): ?>
              <?php
                $old = (string)($lg['old_status'] ?? '');
                $new = (string)($lg['new_status'] ?? '');
                $who = (string)($lg['admin_name'] ?? ($lg['admin_id'] ?? ''));
                if ($who === '') $who = 'Admin';
                $when = (string)($lg['created_at'] ?? '');
              ?>
              <div class="list-group-item px-0">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                  <div>
                    <div class="fw-semibold" style="line-height:1.2;">
                      <?= h(status_th_label($old)) ?>
                      <i class="bi bi-arrow-right mx-1"></i>
                      <?= h(status_th_label($new)) ?>
                    </div>
                    <div class="small text-muted" style="line-height:1.2;">
                      เปลี่ยนโดย: <span class="fw-semibold text-dark"><?= h((string)$who) ?></span>
                    </div>
                  </div>
                  <div class="small text-muted" style="white-space:nowrap;">
                    <i class="bi bi-calendar3 me-1"></i><?= h($when !== '' ? $when : '-') ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-header bg-white">
        <div class="fw-semibold"><i class="bi bi-truck me-1"></i>ที่อยู่จัดส่ง</div>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-12 col-md-6">
            <div class="text-muted small">ชื่อผู้รับ</div>
            <div class="fw-semibold"><?= h($shipName) ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">เบอร์โทร</div>
            <div class="fw-semibold"><?= h($shipPhone) ?></div>
          </div>
          <div class="col-12">
            <div class="text-muted small">ที่อยู่</div>
            <div class="border rounded-3 p-3 bg-light" style="white-space:pre-wrap;">
              <?= h($shipAddr) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-box-seam me-1"></i>รายการสินค้า</div>
        <span class="badge text-bg-light border"><?= count($items) ?> รายการ</span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:70px;">รูป</th>
              <th>สินค้า</th>
              <th class="text-end" style="width:120px;">ราคา</th>
              <th class="text-center" style="width:110px;">จำนวน</th>
              <th class="text-end" style="width:120px;">รวม</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $itemsTotal = 0.0;
              foreach ($items as $it):
                $qty = (int)($it['qty'] ?? 0);
                $price = (float)($it['price'] ?? 0);
                $line = $qty * $price;
                $itemsTotal += $line;
                $img = !empty($it['product_image']) ? (BASE_URL . '/' . ltrim(h($it['product_image']), '/')) : '';
            ?>
              <tr>
                <td>
                  <div class="ratio ratio-1x1 rounded border bg-light overflow-hidden" style="width:54px;">
                    <?php if ($img): ?>
                      <img src="<?= $img ?>" alt="" style="object-fit:cover" loading="lazy">
                    <?php else: ?>
                      <div class="d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="fw-semibold"><?= h($it['product_name'] ?? '') ?></div>
                  <div class="small text-muted">สินค้า #<?= (int)($it['product_id'] ?? 0) ?></div>
                </td>
                <td class="text-end"><?= number_format($price, 2) ?></td>
                <td class="text-center fw-semibold"><?= $qty ?></td>
                <td class="text-end fw-semibold"><?= number_format($line, 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <th colspan="4" class="text-end">ยอดรวมสินค้า</th>
              <th class="text-end"><?= number_format($itemsTotal, 2) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

  </div>

  <div class="col-12 col-lg-4">
    <div class="card shadow-sm position-sticky" style="top: 92px;">
      <div class="card-header bg-white">
        <div class="fw-semibold"><i class="bi bi-arrow-repeat me-1"></i>อัปเดตสถานะ</div>
      </div>
      <div class="card-body">
        <form method="post" action="<?= BASE_URL ?>/admin/orders/update_status.php">
          <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">

          <label class="form-label">เลือกสถานะใหม่</label>
          <select class="form-select" name="status" required>
            <?php foreach(['pending','paid','shipping','completed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= ((string)($o['status'] ?? '')) === $s ? 'selected' : '' ?>>
                <?= h(status_th_label($s)) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-save me-1"></i>อัปเดต
            </button>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/orders/index.php">กลับ</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>