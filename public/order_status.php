<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  die('ไม่พบออเดอร์');
}

// ===== Helpers =====
function status_th_label(string $status): string {
  return match (strtolower(trim($status))) {
    'pending'   => 'รอดำเนินการ',
    'paid'      => 'ชำระแล้ว',
    'shipping'  => 'กำลังจัดส่ง',
    'completed' => 'สำเร็จ',
    'cancelled', 'canceled' => 'ยกเลิก',
    default     => $status !== '' ? $status : '-',
  };
}

function status_badge(string $status): array {
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

function fmt_th_datetime(string $dt): string {
  if ($dt === '') return '-';
  try {
    $d = new DateTime($dt, new DateTimeZone('Asia/Bangkok'));
    return $d->format('d/m/Y H:i');
  } catch (Throwable $e) {
    return $dt;
  }
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1");
$stmt->execute([$id, (int)($_SESSION['user']['id'] ?? 0)]);
$o = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$o) {
  die('ไม่พบออเดอร์');
}

[$stLabel, $stColor, $stIcon] = status_badge((string)($o['status'] ?? ''));

// ===== Order items (for bundle unlock links) =====
$items = [];
try {
  // order_items should exist in your project
  $it = $pdo->prepare("
    SELECT oi.product_id, oi.qty,
           p.name AS product_name,
           COALESCE(p.is_bundle, 0) AS is_bundle,
           p.bundle_link
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
  ");
  $it->execute([(int)($o['id'] ?? 0)]);
  $items = $it->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $items = [];
}

// Decide when to unlock digital links
$unlock = in_array(strtolower((string)($o['status'] ?? '')), ['paid','shipping','completed'], true);

include __DIR__ . '/../templates/header.php';
?>

<?php if ($unlock): ?>
  <?php
    $bundleItems = array_values(array_filter($items, function($r){
      return ((int)($r['is_bundle'] ?? 0) === 1) && trim((string)($r['bundle_link'] ?? '')) !== '';
    }));
  ?>
  <?php if (!empty($bundleItems)): ?>
    <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
      <div style="font-size:1.2rem;line-height:1;">🔓</div>
      <div class="flex-grow-1">
        <div class="fw-semibold mb-1">ลิงก์สูตร/วิดีโอสำหรับออเดอร์นี้</div>
        <div class="vstack gap-2">
          <?php foreach ($bundleItems as $bi): ?>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border rounded-3 p-2 bg-white">
              <div>
                <div class="fw-semibold small"><?= h((string)($bi['product_name'] ?? 'ชุดทำขนม')) ?></div>
                <div class="text-muted small">จำนวน: <?= (int)($bi['qty'] ?? 1) ?></div>
              </div>
              <a class="btn btn-success btn-sm" target="_blank" rel="noopener" href="<?= h((string)$bi['bundle_link']) ?>">
                🔓 ดูสูตรการทำ
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">สถานะออเดอร์ #<?= (int)($o['id'] ?? 0) ?></h1>
    <div class="text-muted small">ติดตามสถานะการสั่งซื้อและยอดรวม</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/public/orders.php">
      <i class="bi bi-arrow-left me-1"></i>กลับรายการออเดอร์
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm">
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
            <div class="text-muted small">วันที่สั่ง</div>
            <div class="fw-semibold"><?= h(fmt_th_datetime((string)($o['created_at'] ?? ''))) ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">รหัสออเดอร์</div>
            <div class="fw-semibold">#<?= (int)($o['id'] ?? 0) ?></div>
          </div>
        </div>

        <?php if (!empty($o['note'])): ?>
          <hr class="my-3">
          <div class="text-muted small">หมายเหตุ</div>
          <div class="border rounded-3 p-3 bg-light" style="white-space:pre-wrap;"><?= h((string)$o['note']) ?></div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <div class="fw-semibold"><i class="bi bi-diagram-3 me-1"></i>ไทม์ไลน์สถานะ</div>
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

        <div class="vstack gap-2">
          <?php $i = 0; foreach ($steps as $key => [$label, $icon]): ?>
            <?php
              $state = 'upcoming';
              if ($i < $curPos) $state = 'done';
              if ($i === $curPos) $state = 'current';

              $dotClass = $state === 'done' ? 'bg-success' : ($state === 'current' ? 'bg-primary' : 'bg-secondary');
              $textClass = $state === 'upcoming' ? 'text-muted' : 'text-dark';
              $sub = $state === 'done' ? 'เสร็จแล้ว' : ($state === 'current' ? 'กำลังอยู่ขั้นนี้' : 'รอดำเนินการ');
            ?>
            <div class="d-flex align-items-start gap-2">
              <span class="rounded-circle d-inline-flex align-items-center justify-content-center <?= h($dotClass) ?>" style="width:34px;height:34px;color:#fff; flex:0 0 auto;">
                <i class="bi <?= h($icon) ?>"></i>
              </span>
              <div>
                <div class="fw-semibold <?= h($textClass) ?>" style="line-height:1.15;"><?= h($label) ?></div>
                <div class="small text-muted" style="line-height:1.15;"><?= h($sub) ?></div>
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
  </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>