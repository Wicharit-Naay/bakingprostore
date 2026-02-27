<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

// ===== Helpers =====
function order_status_badge(string $status): array {
  $s = strtolower(trim($status));
  switch ($s) {
    case 'pending':
      return ['รอดำเนินการ', 'secondary', 'bi-hourglass-split'];
    case 'paid':
      return ['ชำระแล้ว', 'primary', 'bi-check2-circle'];
    case 'shipping':
      return ['กำลังจัดส่ง', 'warning', 'bi-truck'];
    case 'completed':
      return ['สำเร็จ', 'success', 'bi-bag-check'];
    case 'cancelled':
    case 'canceled':
      return ['ยกเลิก', 'danger', 'bi-x-circle'];
    default:
      return [$status !== '' ? $status : 'ไม่ทราบสถานะ', 'light', 'bi-question-circle'];
  }
}

function safe_date($v): string {
  if (empty($v)) return '-';
  // keep raw if already a string
  return (string)$v;
}

// ===== Data =====
try {
  $orders = $pdo->query(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email\n"
    . "FROM orders o\n"
    . "JOIN users u ON u.id = o.user_id\n"
    . "ORDER BY o.id DESC"
  )->fetchAll();
} catch (Throwable $e) {
  $orders = [];
  $ordersError = $e->getMessage();
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<?php if (!empty($ordersError ?? '')): ?>
  <div class="alert alert-danger">
    <div class="fw-semibold">โหลดรายการออเดอร์ไม่สำเร็จ</div>
    <div class="small"><?= h($ordersError) ?></div>
  </div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">จัดการออเดอร์</h2>
    <div class="text-muted small">ดูรายการสั่งซื้อ, ลูกค้า, ยอดรวม และสถานะ</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/index.php"><i class="bi bi-arrow-left me-1"></i>กลับเมนู</a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div class="small text-muted">
        ทั้งหมด <span class="fw-semibold"><?= count($orders) ?></span> ออเดอร์
      </div>
    </div>

    <div class="table-responsive">
      <table id="dtOrders" class="table table-striped table-hover align-middle mb-0" style="width:100%">
        <thead class="table-light">
          <tr>
            <th style="width:90px">Order</th>
            <th>ลูกค้า</th>
            <th style="width:130px" class="text-end">ยอดรวม</th>
            <th style="width:150px">สถานะ</th>
            <th style="width:160px">วันที่</th>
            <th style="width:120px" class="text-end">จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($orders as $o): ?>
          <?php
            $id = (int)($o['id'] ?? 0);
            $total = (float)($o['total'] ?? 0);
            $created = $o['created_at'] ?? ($o['createdAt'] ?? ($o['created'] ?? null));
            [$label, $color, $icon] = order_status_badge((string)($o['status'] ?? ''));
          ?>
          <tr>
            <td class="fw-semibold">#<?= $id ?></td>
            <td>
              <div class="fw-semibold"><?= h($o['customer_name'] ?? '') ?></div>
              <div class="small text-muted"><?= h($o['customer_email'] ?? '') ?></div>
            </td>
            <td class="text-end fw-semibold"><?= number_format($total, 2) ?></td>
            <td>
              <span class="badge text-bg-<?= h($color) ?>">
                <i class="bi <?= h($icon) ?> me-1"></i><?= h($label) ?>
              </span>
            </td>
            <td><?= h(safe_date($created)) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/orders/show.php?id=<?= $id ?>">
                <i class="bi bi-search me-1"></i>ดูรายละเอียด
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- DataTables Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
(function(){
  if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable) return;

  jQuery(function($){
    $('#dtOrders').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      order: [[0, 'desc']],
      language: {
        search: 'ค้นหา:',
        lengthMenu: 'แสดง _MENU_ รายการ',
        info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
        infoEmpty: 'ไม่มีข้อมูล',
        zeroRecords: 'ไม่พบข้อมูลที่ค้นหา',
        paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' }
      },
      columnDefs: [
        { targets: [2,5], orderable: false },
      ]
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>