<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

// UI helper: status badge
function order_status_badge(string $status): string {
  $s = strtolower(trim($status));
  return match ($s) {
    'pending' => '<span class="badge text-bg-warning">รอดำเนินการ</span>',
    'paid' => '<span class="badge text-bg-success">ชำระแล้ว</span>',
    'shipping' => '<span class="badge text-bg-primary">กำลังจัดส่ง</span>',
    'completed' => '<span class="badge text-bg-success">สำเร็จ</span>',
    'cancelled', 'canceled' => '<span class="badge text-bg-secondary">ยกเลิก</span>',
    default => '<span class="badge text-bg-light border">' . h($status !== '' ? $status : '-') . '</span>',
  };
}

// UI helper: format datetime to TH (dd/mm/yyyy hh:mm)
function fmt_dt_th(?string $dt): string {
  $dt = trim((string)$dt);
  if ($dt === '') return '-';
  try {
    $tz = new DateTimeZone('Asia/Bangkok');
    $d = new DateTime($dt, $tz);
    return $d->format('d/m/Y H:i');
  } catch (Throwable $e) {
    return h($dt);
  }
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC");
$stmt->execute([$_SESSION['user']['id']]);
$orders = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';
?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-1">ประวัติการสั่งซื้อ</h2>
    <div class="text-muted small">ดูรายการสั่งซื้อย้อนหลัง และตรวจสอบสถานะการจัดส่ง</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/public/index.php">
      <i class="bi bi-arrow-left me-1"></i>กลับหน้าร้าน
    </a>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/public/cart.php">
      <i class="bi bi-cart3 me-1"></i>ไปตะกร้า
    </a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <div class="fw-semibold"><i class="bi bi-receipt me-1"></i>รายการออเดอร์</div>
    <span class="badge text-bg-light border"><?= count($orders) ?> รายการ</span>
  </div>

  <?php if (!$orders): ?>
    <div class="card-body text-center py-5">
      <div class="display-6 mb-2">🧾</div>
      <div class="fw-semibold">ยังไม่มีประวัติการสั่งซื้อ</div>
      <div class="text-muted small mb-3">เริ่มเลือกสินค้า แล้วกลับมาตรวจสอบสถานะได้ที่หน้านี้</div>
      <a class="btn btn-primary" href="<?= BASE_URL ?>/public/index.php">
        <i class="bi bi-bag me-1"></i>ไปเลือกสินค้า
      </a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:120px;">เลขออเดอร์</th>
            <th style="width:140px;" class="text-end">ยอดรวม</th>
            <th style="width:160px;">สถานะ</th>
            <th style="width:190px;">วันที่</th>
            <th style="width:160px;" class="text-end">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($orders as $o): ?>
            <tr>
              <td class="fw-semibold">#<?= (int)$o['id'] ?></td>
              <td class="text-end fw-semibold"><?= number_format((float)($o['total'] ?? 0), 2) ?> ฿</td>
              <td><?= order_status_badge((string)($o['status'] ?? '')) ?></td>
              <td class="text-muted"><?= fmt_dt_th($o['created_at'] ?? '') ?></td>
              <td class="text-end">
                <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/public/order_status.php?id=<?= (int)$o['id'] ?>">
                  <i class="bi bi-search me-1"></i>เช็คสถานะ
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>