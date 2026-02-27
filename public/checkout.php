<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/cart.php';

require_login();
cart_init();

// ===== Load user profile (for prefilling checkout fields) =====
$uid = (int)($_SESSION['user']['id'] ?? 0);
$userProfile = [
  'name' => (string)($_SESSION['user']['name'] ?? ''),
  'phone' => (string)($_SESSION['user']['phone'] ?? ''),
  'address' => (string)($_SESSION['user']['address'] ?? ''),
];

if ($uid > 0) {
  try {
    $uStmt = $pdo->prepare("SELECT name, phone, address FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$uid]);
    $row = $uStmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (!empty($row['name'])) $userProfile['name'] = (string)$row['name'];
      $userProfile['phone'] = (string)($row['phone'] ?? '');
      $userProfile['address'] = (string)($row['address'] ?? '');

      // sync เข้า session ไว้ใช้หน้าอื่นด้วย
      $_SESSION['user']['name'] = $userProfile['name'];
      $_SESSION['user']['phone'] = $userProfile['phone'];
      $_SESSION['user']['address'] = $userProfile['address'];
    }
  } catch (Throwable $e) {
    // ignore
  }
}
$cart = (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];
if (!$cart) {
  // ใช้ UI สวย ๆ แทน die
  $pageTitle = 'สั่งซื้อสินค้า - BakingProStore';
  include __DIR__ . '/../templates/header.php';
  ?>
  <div class="card shadow-sm">
    <div class="card-body py-5 text-center">
      <div class="text-muted mb-2"><i class="bi bi-cart3" style="font-size:2.25rem;"></i></div>
      <div class="fw-semibold">ไม่มีสินค้าในตะกร้า</div>
      <div class="text-muted small mt-1">กรุณาเลือกสินค้าก่อนทำรายการสั่งซื้อ</div>
      <a class="btn btn-primary mt-3" href="<?= BASE_URL ?>/public/index.php"><i class="bi bi-shop me-1"></i>ไปหน้าร้าน</a>
      <a class="btn btn-outline-secondary mt-3 ms-2" href="<?= BASE_URL ?>/public/cart.php"><i class="bi bi-bag me-1"></i>กลับไปตะกร้า</a>
    </div>
  </div>
  <?php
  include __DIR__ . '/../templates/footer.php';
  exit;
}

// ===== Load products =====
$ids = array_keys($cart);
$in = implode(',', array_fill(0, count($ids), '?'));
$orderBy = 'ORDER BY FIELD(id,' . implode(',', array_map('intval', $ids)) . ')';

$stmt = $pdo->prepare("SELECT id,name,price,image,stock FROM products WHERE id IN ($in) $orderBy");
$stmt->execute($ids);
$products = $stmt->fetchAll();

// ===== Totals =====
$subtotal = 0.0;
foreach ($products as &$p) {
  $id = (int)$p['id'];
  $qty = (int)($cart[$id] ?? 0);
  $price = (float)($p['price'] ?? 0);
  $stock = isset($p['stock']) ? (int)$p['stock'] : 0;

  // clamp qty to stock (if stock is used)
  if ($stock > 0 && $qty > $stock) {
    $qty = $stock;
    $_SESSION['cart'][$id] = $qty;
  }

  $p['qty'] = $qty;
  $p['sub'] = $qty * $price;
  $subtotal += $p['sub'];
}
unset($p);

$shipping = ($subtotal >= 500) ? 0.0 : 40.0;
$grandTotal = $subtotal + $shipping;

// ===== Helpers: dynamic insert to orders (avoid schema mismatch) =====
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

function save_upload_slip(array $file, string $baseDir, string $subDir = 'payments'): array {
  // returns [ok(bool), path(string|null), err(string|null)]
  if (empty($file) || empty($file['name'])) return [true, null, null];
  if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return [false, null, 'อัปโหลดไฟล์ไม่สำเร็จ'];

  $allowed = ['jpg','jpeg','png','webp'];
  $name = (string)($file['name'] ?? '');
  $tmp  = (string)($file['tmp_name'] ?? '');
  $size = (int)($file['size'] ?? 0);

  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowed, true)) return [false, null, 'รองรับไฟล์ jpg, png, webp เท่านั้น'];
  if ($size > 5 * 1024 * 1024) return [false, null, 'ไฟล์ใหญ่เกิน 5MB'];

  $dirFs = rtrim($baseDir, '/') . '/uploads/' . $subDir;
  if (!is_dir($dirFs)) {
    @mkdir($dirFs, 0777, true);
  }

  $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($name, PATHINFO_FILENAME));
  $newName = 'slip_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safe . '.' . $ext;
  $destFs = $dirFs . '/' . $newName;

  if (!@move_uploaded_file($tmp, $destFs)) {
    return [false, null, 'ย้ายไฟล์ไม่สำเร็จ (permission)'];
  }

  // store relative path
  $rel = 'uploads/' . $subDir . '/' . $newName;
  return [true, $rel, null];
}

$msg = '';
$ok = '';

// Prefill from user profile (POST has priority so customer can edit per-order)
$ship_name  = trim((string)($_POST['ship_name']  ?? ($userProfile['name'] ?? '')));
$ship_phone = trim((string)($_POST['ship_phone'] ?? ($userProfile['phone'] ?? '')));
$ship_addr  = trim((string)($_POST['ship_address'] ?? ($userProfile['address'] ?? '')));
$note       = trim((string)($_POST['note'] ?? ''));
$pay_method = trim((string)($_POST['payment_method'] ?? 'cod'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // basic validation
  if ($ship_name === '' || mb_strlen($ship_name) < 2) {
    $msg = 'กรุณากรอกชื่อผู้รับ';
  } elseif ($ship_phone === '' || mb_strlen($ship_phone) < 8) {
    $msg = 'กรุณากรอกเบอร์โทรให้ถูกต้อง';
  } elseif ($ship_addr === '' || mb_strlen($ship_addr) < 10) {
    $msg = 'กรุณากรอกที่อยู่จัดส่ง (อย่างน้อย 10 ตัวอักษร)';
  } elseif (!in_array($pay_method, ['cod','bank_qr'], true)) {
    $msg = 'วิธีชำระเงินไม่ถูกต้อง';
  }

  // upload slip if needed
  $slipPath = null;
  if ($msg === '' && $pay_method === 'bank_qr') {
    [$upOk, $slipPath, $upErr] = save_upload_slip($_FILES['payment_slip'] ?? [], dirname(__DIR__));
    if (!$upOk) $msg = $upErr ?: 'อัปโหลดสลิปไม่สำเร็จ';
  }

  if ($msg === '') {
    try {
      $pdo->beginTransaction();

      // Detect columns
      $orderCols = get_table_columns($pdo, 'orders');

      // Build dynamic insert
      $fields = [];
      $placeholders = [];
      $values = [];

      // required
      if (in_array('user_id', $orderCols, true)) {
        $fields[] = 'user_id';
        $placeholders[] = '?';
        $values[] = (int)($_SESSION['user']['id'] ?? 0);
      }
      if (in_array('total', $orderCols, true)) {
        $fields[] = 'total';
        $placeholders[] = '?';
        $values[] = $grandTotal;
      }

      // status
      $statusValue = 'pending';
      if (in_array('status', $orderCols, true)) {
        $fields[] = 'status';
        $placeholders[] = '?';
        $values[] = $statusValue;
      }

      // optional columns (only if exist)
      $mapOptional = [
        'ship_name'      => $ship_name,
        'ship_phone'     => $ship_phone,
        'ship_address'   => $ship_addr,
        'note'           => $note,
        'payment_method' => $pay_method,
        'payment_slip'   => $slipPath,
        'shipping_fee'   => $shipping,
        'subtotal'       => $subtotal,
      ];

      foreach ($mapOptional as $col => $val) {
        if (in_array($col, $orderCols, true)) {
          $fields[] = $col;
          $placeholders[] = '?';
          $values[] = $val;
        }
      }

      // Fallback if schema minimal
      if (empty($fields)) {
        throw new Exception('ไม่พบโครงสร้างตาราง orders (columns)');
      }

      $sql = "INSERT INTO orders(" . implode(',', $fields) . ") VALUES(" . implode(',', $placeholders) . ")";
      $ins = $pdo->prepare($sql);
      $ins->execute($values);
      $order_id = (int)$pdo->lastInsertId();

      // order_items + stock
      $itemStmt = $pdo->prepare("INSERT INTO order_items(order_id,product_id,qty,price) VALUES(?,?,?,?)");
      $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?");

      foreach ($products as $p) {
        $pid = (int)$p['id'];
        $qty = (int)($cart[$pid] ?? 0);
        $price = (float)$p['price'];

        if ($qty <= 0) continue;

        $itemStmt->execute([$order_id, $pid, $qty, $price]);

        // only enforce stock if stock column exists
        $stock = isset($p['stock']) ? (int)$p['stock'] : 0;
        if ($stock > 0) {
          $stockStmt->execute([$qty, $pid, $qty]);
          if ($stockStmt->rowCount() === 0) {
            throw new Exception('สต็อกไม่พอ: ' . (string)$p['name']);
          }
        }
      }

      $pdo->commit();
      cart_clear();

      redirect('/public/orders.php');
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $msg = 'สั่งซื้อไม่สำเร็จ: ' . $e->getMessage();

      // remove uploaded slip if order failed
      if (!empty($slipPath)) {
        $fs = dirname(__DIR__) . '/' . ltrim($slipPath, '/');
        if (is_file($fs)) @unlink($fs);
      }
    }
  }
}

$pageTitle = 'สั่งซื้อสินค้า - BakingProStore';
include __DIR__ . '/../templates/header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">สั่งซื้อสินค้า</h2>
    <div class="text-muted small">กรอกข้อมูลจัดส่งและเลือกวิธีชำระเงิน</div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/public/cart.php"><i class="bi bi-arrow-left me-1"></i>กลับไปตะกร้า</a>
</div>

<?php if ($msg): ?>
  <div class="alert alert-warning py-2">
    <i class="bi bi-exclamation-triangle me-1"></i><?= h($msg) ?>
  </div>
<?php endif; ?>

<div class="row g-3">
  <!-- Form -->
  <div class="col-12 col-lg-8">
    <form method="post" enctype="multipart/form-data">
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <div class="fw-semibold"><i class="bi bi-truck me-1"></i>ข้อมูลจัดส่ง</div>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-12 col-md-6">
              <label class="form-label">ชื่อผู้รับ</label>
              <input class="form-control" name="ship_name" value="<?= h($ship_name) ?>" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">เบอร์โทร</label>
              <input class="form-control" name="ship_phone" value="<?= h($ship_phone) ?>" inputmode="tel" required>
            </div>
            <div class="col-12">
              <label class="form-label">ที่อยู่จัดส่ง</label>
              <textarea class="form-control" name="ship_address" rows="3" required><?= h($ship_addr) ?></textarea>
              <div class="form-text">ตัวอย่าง: บ้านเลขที่ / หมู่ / ถนน / ตำบล / อำเภอ / จังหวัด / รหัสไปรษณีย์</div>
            </div>
            <div class="col-12">
              <label class="form-label">หมายเหตุ (ถ้ามี)</label>
              <input class="form-control" name="note" value="<?= h($note) ?>" maxlength="255" placeholder="เช่น ฝากวางหน้าบ้าน / ติดต่อก่อนส่ง">
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-header bg-white">
          <div class="fw-semibold"><i class="bi bi-credit-card me-1"></i>วิธีชำระเงิน</div>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="payCod" value="cod" <?= $pay_method==='cod' ? 'checked' : '' ?>>
                <label class="form-check-label" for="payCod">ชำระเงินปลายทาง (COD)</label>
              </div>
              <div class="form-check mt-2">
                <input class="form-check-input" type="radio" name="payment_method" id="payQr" value="bank_qr" <?= $pay_method==='bank_qr' ? 'checked' : '' ?>>
                <label class="form-check-label" for="payQr">โอนเงินผ่าน QR / แนบสลิป</label>
              </div>
            </div>

            <div class="col-12" id="qrBox" style="display:none;">
              <div class="border rounded-3 p-3 bg-light">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                  <div>
                    <div class="fw-semibold">สแกนเพื่อชำระเงิน</div>
                    <div class="text-muted small">หลังโอนเงิน กรุณาแนบสลิปเพื่อยืนยัน</div>
                  </div>
                  <span class="badge text-bg-light border">ยอดชำระ <?= number_format($grandTotal,2) ?> ฿</span>
                </div>

                <div class="row g-3 mt-1 align-items-center">
                  <div class="col-12 col-md-5">
                    <div class="ratio ratio-1x1 bg-white border rounded-3 overflow-hidden">
                      <img src="<?= BASE_URL ?>/assets/img/qr.png" alt="QR" style="object-fit:contain" onerror="this.style.display='none'">
                      <div class="d-flex align-items-center justify-content-center text-muted" style="display:none" id="qrFallback">
                        <div class="text-center">
                          <i class="bi bi-qr-code" style="font-size:2rem"></i>
                          <div class="small mt-2">ยังไม่มีไฟล์ QR</div>
                          <div class="small">วางไฟล์ที่ assets/img/qr.png</div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-12 col-md-7">
                    <label class="form-label">แนบสลิปการโอน</label>
                    <input class="form-control" type="file" name="payment_slip" accept="image/*">
                    <div class="form-text">รองรับ jpg/png/webp ขนาดไม่เกิน 5MB</div>

                    <div class="mt-2" id="slipPreviewWrap" style="display:none;">
                      <div class="small text-muted mb-1">ตัวอย่างสลิป</div>
                      <div class="ratio ratio-4x3 border rounded-3 overflow-hidden bg-white">
                        <img id="slipPreview" alt="slip" style="object-fit:contain;">
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-grid gap-2 mt-3">
        <button class="btn btn-primary btn-lg" type="submit">
          <i class="bi bi-bag-check me-1"></i>ยืนยันสั่งซื้อ
        </button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/public/cart.php">กลับไปแก้ไขตะกร้า</a>
      </div>
    </form>
  </div>

  <!-- Summary -->
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm position-sticky" style="top: 92px;">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-receipt me-1"></i>สรุปรายการ</div>
        <span class="badge text-bg-light border"><?= count($products) ?> รายการ</span>
      </div>
      <div class="card-body">

        <div class="vstack gap-2">
          <?php foreach ($products as $p): ?>
            <?php
              $img = !empty($p['image']) ? (BASE_URL . '/' . ltrim(h($p['image']), '/')) : '';
            ?>
            <div class="d-flex gap-2 align-items-center">
              <div class="bg-light border rounded-2" style="width:46px;height:46px;overflow:hidden;flex:0 0 auto;">
                <?php if ($img): ?>
                  <img src="<?= $img ?>" alt="" style="width:100%;height:100%;object-fit:cover" loading="lazy">
                <?php else: ?>
                  <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1">
                <div class="small fw-semibold text-truncate"><?= h($p['name']) ?></div>
                <div class="small text-muted">จำนวน <?= (int)$p['qty'] ?> x <?= number_format((float)$p['price'],2) ?></div>
              </div>
              <div class="small fw-semibold"><?= number_format((float)$p['sub'],2) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <hr>

        <div class="d-flex justify-content-between mb-2">
          <div class="text-muted">ยอดสินค้า</div>
          <div class="fw-semibold"><?= number_format($subtotal,2) ?></div>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <div class="text-muted">ค่าจัดส่ง</div>
          <div class="fw-semibold">
            <?php if ($shipping <= 0): ?>
              <span class="text-success">ฟรี</span>
            <?php else: ?>
              <?= number_format($shipping,2) ?>
            <?php endif; ?>
          </div>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between align-items-center">
          <div class="text-muted">ยอดชำระ</div>
          <div class="fs-4 fw-semibold"><?= number_format($grandTotal,2) ?></div>
        </div>

        <?php if ($shipping > 0): ?>
          <div class="small text-muted mt-1">ซื้อเพิ่มอีก <?= number_format(500 - $subtotal,2) ?> บาท เพื่อส่งฟรี</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  function toggleQr(){
    var isQr = document.getElementById('payQr') && document.getElementById('payQr').checked;
    var box = document.getElementById('qrBox');
    if(box) box.style.display = isQr ? 'block' : 'none';
  }

  var cod = document.getElementById('payCod');
  var qr  = document.getElementById('payQr');
  if(cod) cod.addEventListener('change', toggleQr);
  if(qr) qr.addEventListener('change', toggleQr);
  toggleQr();

  // slip preview
  var input = document.querySelector('input[name="payment_slip"]');
  var wrap = document.getElementById('slipPreviewWrap');
  var img  = document.getElementById('slipPreview');
  if(input && wrap && img){
    input.addEventListener('change', function(){
      var f = input.files && input.files[0];
      if(!f){ wrap.style.display = 'none'; return; }
      var url = URL.createObjectURL(f);
      img.src = url;
      wrap.style.display = 'block';
    });
  }

  // qr fallback if image missing
  var qrImg = document.querySelector('#qrBox img');
  var fb = document.getElementById('qrFallback');
  if(qrImg && fb){
    qrImg.addEventListener('error', function(){
      fb.style.display = 'flex';
    });
  }
})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>