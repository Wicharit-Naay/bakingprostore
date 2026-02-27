<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/functions.php';
$authFile = __DIR__ . '/../../helpers/auth.php';
if (file_exists($authFile)) require_once $authFile;

if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$pageTitle = 'การเงิน';

// ===== Date range (default: this month) =====
$tz = new DateTimeZone('Asia/Bangkok');
$today = new DateTime('now', $tz);

$startDefault = (clone $today)->modify('first day of this month')->format('Y-m-d');
$endDefault   = (clone $today)->modify('last day of this month')->format('Y-m-d');

$start = trim($_GET['start'] ?? $startDefault);
$end   = trim($_GET['end'] ?? $endDefault);

// Basic validation (YYYY-MM-DD)
$re = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($re, $start)) $start = $startDefault;
if (!preg_match($re, $end))   $end   = $endDefault;

// Normalize: ensure start <= end
if ($start > $end) { $tmp = $start; $start = $end; $end = $tmp; }

// Use inclusive end for DATETIME columns by adding 1 day and using < endNext
$endNext = (new DateTime($end, $tz))->modify('+1 day')->format('Y-m-d');

// ===== Helpers =====
function fmt_money($n): string { return number_format((float)$n, 2); }

function status_th(string $s): string {
  return match (strtolower(trim($s))) {
    'pending' => 'รอดำเนินการ',
    'paid' => 'ชำระแล้ว',
    'shipping' => 'กำลังจัดส่ง',
    'completed' => 'สำเร็จ',
    'cancelled', 'canceled' => 'ยกเลิก',
    default => $s !== '' ? $s : '-',
  };
}

// ===== Queries =====
$incomeStatuses = ['paid','shipping','completed'];

// Summary: income
$sumIncome = 0.0;
try {
  // created_at is typically DATETIME
  $in = $pdo->prepare("
    SELECT COALESCE(SUM(total),0) AS s
    FROM orders
    WHERE status IN ('paid','shipping','completed')
      AND created_at >= ? AND created_at < ?
  ");
  $in->execute([$start, $endNext]);
  $sumIncome = (float)($in->fetchColumn() ?: 0);
} catch (Throwable $e) { $sumIncome = 0.0; }

// Summary: expense
$sumExpense = 0.0;
try {
  // expense_date is DATE
  $ex = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0) AS s
    FROM expenses
    WHERE expense_date >= ? AND expense_date <= ?
  ");
  $ex->execute([$start, $end]);
  $sumExpense = (float)($ex->fetchColumn() ?: 0);
} catch (Throwable $e) { $sumExpense = 0.0; }

$net = $sumIncome - $sumExpense;

// ===== Pie chart: expense by category =====
$pieLabels = [];
$pieValues = [];
try {
  $stPie = $pdo->prepare("
    SELECT COALESCE(ec.name, 'ไม่ระบุหมวดหมู่') AS cat_name,
           COALESCE(SUM(e.amount),0) AS total_amount
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id = e.category_id
    WHERE e.expense_date >= ? AND e.expense_date <= ?
    GROUP BY cat_name
    ORDER BY total_amount DESC
  ");
  $stPie->execute([$start, $end]);
  foreach ($stPie->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $pieLabels[] = (string)($r['cat_name'] ?? 'ไม่ระบุหมวดหมู่');
    $pieValues[] = (float)($r['total_amount'] ?? 0);
  }
} catch (Throwable $e) {
  $pieLabels = [];
  $pieValues = [];
}

// ===== Daily series for chart =====
// Build date list
$dates = [];
$cursor = new DateTime($start, $tz);
$endObj = new DateTime($end, $tz);
while ($cursor <= $endObj) {
  $dates[] = $cursor->format('Y-m-d');
  $cursor->modify('+1 day');
}

// Income per day
$incomeMap = array_fill_keys($dates, 0.0);
try {
  $rs = $pdo->prepare("
    SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS s
    FROM orders
    WHERE status IN ('paid','shipping','completed')
      AND created_at >= ? AND created_at < ?
    GROUP BY DATE(created_at)
    ORDER BY d
  ");
  $rs->execute([$start, $endNext]);
  foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = (string)($r['d'] ?? '');
    if ($d !== '' && isset($incomeMap[$d])) $incomeMap[$d] = (float)$r['s'];
  }
} catch (Throwable $e) {
  // ignore
}

// Expense per day
$expenseMap = array_fill_keys($dates, 0.0);
try {
  $rs2 = $pdo->prepare("
    SELECT expense_date AS d, COALESCE(SUM(amount),0) AS s
    FROM expenses
    WHERE expense_date >= ? AND expense_date <= ?
    GROUP BY expense_date
    ORDER BY d
  ");
  $rs2->execute([$start, $end]);
  foreach ($rs2->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = (string)($r['d'] ?? '');
    if ($d !== '' && isset($expenseMap[$d])) $expenseMap[$d] = (float)$r['s'];
  }
} catch (Throwable $e) {
  // ignore
}

$chartLabels = $dates;
$chartIncome = array_values($incomeMap);
$chartExpense = array_values($expenseMap);

// ===== Latest lists =====
$latestExpenses = [];
try {
  $q1 = $pdo->prepare("
    SELECT e.id, e.expense_date, e.title, e.amount, e.pay_method, e.ref_no, e.created_at
    FROM expenses e
    WHERE e.expense_date >= ? AND e.expense_date <= ?
    ORDER BY e.expense_date DESC, e.id DESC
    LIMIT 8
  ");
  $q1->execute([$start, $end]);
  $latestExpenses = $q1->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $latestExpenses = []; }

$latestOrders = [];
try {
  $q2 = $pdo->prepare("
    SELECT o.id, o.created_at, o.total, o.status, u.name AS customer_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status IN ('paid','shipping','completed')
      AND o.created_at >= ? AND o.created_at < ?
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT 8
  ");
  $q2->execute([$start, $endNext]);
  $latestOrders = $q2->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $latestOrders = []; }

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">การเงิน</h2>
    <div class="text-muted small">สรุปรายรับ-รายจ่าย พร้อมกราฟตามช่วงวันที่</div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-4">
        <label class="form-label">วันที่เริ่ม</label>
        <input type="date" class="form-control" name="start" value="<?= h($start) ?>" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">วันที่สิ้นสุด</label>
        <input type="date" class="form-control" name="end" value="<?= h($end) ?>" required>
      </div>
      <div class="col-12 col-md-4 d-flex gap-2">
        <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>กรอง</button>
        <a class="btn btn-outline-secondary flex-fill" href="<?= BASE_URL ?>/admin/finance/index.php"><i class="bi bi-arrow-counterclockwise me-1"></i>รีเซ็ต</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">รายรับ</div>
            <div class="fs-3 fw-semibold"><?= fmt_money($sumIncome) ?> ฿</div>
          </div>
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-success-subtle text-success" style="width:46px;height:46px;">
            <i class="bi bi-graph-up"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">รายจ่าย</div>
            <div class="fs-3 fw-semibold"><?= fmt_money($sumExpense) ?> ฿</div>
          </div>
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger" style="width:46px;height:46px;">
            <i class="bi bi-receipt"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">คงเหลือ</div>
            <div class="fs-3 fw-semibold"><?= fmt_money($net) ?> ฿</div>
          </div>
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary" style="width:46px;height:46px;">
            <i class="bi bi-cash-coin"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <div class="fw-semibold"><i class="bi bi-bar-chart-line me-1"></i>กราฟรายวัน (<?= h($start) ?> ถึง <?= h($end) ?>)</div>
    <span class="badge text-bg-light border">แสดงรายรับ/รายจ่ายต่อวัน</span>
  </div>
  <div class="card-body">
    <div class="position-relative" style="height:280px; max-height:280px;">
      <canvas id="financeChart"></canvas>
    </div>
    <div class="small text-muted mt-2">หมายเหตุ: รายรับคำนวณจากยอดรวม (total) ของออเดอร์ในช่วงวันที่</div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <div class="fw-semibold"><i class="bi bi-pie-chart me-1"></i>สัดส่วนรายจ่ายตามหมวดหมู่</div>
    <span class="badge text-bg-light border">ช่วง <?= h($start) ?> ถึง <?= h($end) ?></span>
  </div>
  <div class="card-body">
    <?php if (count($pieValues) === 0): ?>
      <div class="text-center text-muted py-4">ยังไม่มีข้อมูลรายจ่ายในช่วงวันที่ที่เลือก</div>
    <?php else: ?>
      <div class="mx-auto" style="max-width:520px;">
        <div class="position-relative" style="height:260px; max-height:260px;">
          <canvas id="expensePie"></canvas>
        </div>
      </div>
      <div class="small text-muted mt-2 text-center">หมายเหตุ: แสดงยอดรวมรายจ่าย (บาท) แยกตามหมวดหมู่</div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-arrow-down-circle me-1"></i>รายจ่ายล่าสุด</div>
        <span class="badge text-bg-light border"><?= count($latestExpenses) ?> รายการ</span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:110px;">วันที่</th>
              <th>รายการ</th>
              <th class="text-end" style="width:130px;">จำนวนเงิน</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$latestExpenses): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">ไม่มีข้อมูลรายจ่ายในช่วงนี้</td></tr>
            <?php else: foreach ($latestExpenses as $e): ?>
              <tr>
                <td class="text-muted"><?= h($e['expense_date'] ?? '') ?></td>
                <td>
                  <div class="fw-semibold"><?= h($e['title'] ?? '-') ?></div>
                  <div class="small text-muted">
                    <?= h($e['pay_method'] ?? '-') ?>
                    <?php if (!empty($e['ref_no'])): ?> • Ref: <?= h($e['ref_no']) ?><?php endif; ?>
                  </div>
                </td>
                <td class="text-end fw-semibold"><?= fmt_money($e['amount'] ?? 0) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body pt-2">
        <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/finance/expenses.php">
          <i class="bi bi-list-ul me-1"></i>ดูรายจ่ายทั้งหมด
        </a>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-arrow-up-circle me-1"></i>รายรับล่าสุด (จากออเดอร์)</div>
        <span class="badge text-bg-light border"><?= count($latestOrders) ?> รายการ</span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:90px;">#</th>
              <th>ลูกค้า</th>
              <th style="width:130px;">สถานะ</th>
              <th class="text-end" style="width:130px;">ยอด</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$latestOrders): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">ไม่มีข้อมูลรายรับในช่วงนี้</td></tr>
            <?php else: foreach ($latestOrders as $od): ?>
              <tr>
                <td>
                  <a href="<?= BASE_URL ?>/admin/orders/show.php?id=<?= (int)($od['id'] ?? 0) ?>" class="text-decoration-none">
                    #<?= (int)($od['id'] ?? 0) ?>
                  </a>
                  <div class="small text-muted"><?= h($od['created_at'] ?? '') ?></div>
                </td>
                <td class="fw-semibold"><?= h($od['customer_name'] ?? '-') ?></td>
                <td><?= h(status_th((string)($od['status'] ?? ''))) ?></td>
                <td class="text-end fw-semibold"><?= fmt_money($od['total'] ?? 0) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body pt-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/orders/index.php">
          <i class="bi bi-receipt me-1"></i>ไปหน้าออเดอร์
        </a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (function(){
    const el = document.getElementById('financeChart');
    if (!el) return;

    const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const income = <?= json_encode($chartIncome, JSON_UNESCAPED_UNICODE) ?>;
    const expense = <?= json_encode($chartExpense, JSON_UNESCAPED_UNICODE) ?>;

    new Chart(el, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'รายรับ', data: income, tension: 0.25, borderWidth: 2, pointRadius: 2 },
          { label: 'รายจ่าย', data: expense, tension: 0.25, borderWidth: 2, pointRadius: 2 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        resizeDelay: 80,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: { callbacks: {
            label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.raw || 0).toLocaleString('th-TH',{minimumFractionDigits:2, maximumFractionDigits:2})} ฿`
          }}
        },
        scales: {
          x: {
            ticks: {
              maxRotation: 0,
              autoSkip: true,
              maxTicksLimit: 10
            }
          },
          y: {
            ticks: {
              callback: (v) => Number(v).toLocaleString('th-TH')
            }
          }
        }
      }
    });

    // ===== Pie chart: expenses by category =====
    const pieEl = document.getElementById('expensePie');
    if (pieEl) {
      const pieLabels = <?= json_encode($pieLabels, JSON_UNESCAPED_UNICODE) ?>;
      const pieValues = <?= json_encode($pieValues, JSON_UNESCAPED_UNICODE) ?>;

      const colors = pieLabels.map((_, i) => {
        const hue = (i * 47) % 360;
        return `hsl(${hue} 70% 55%)`;
      });

      new Chart(pieEl, {
        type: 'pie',
        data: {
          labels: pieLabels,
          datasets: [{
            data: pieValues,
            backgroundColor: colors,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: {
                label: (ctx) => {
                  const v = Number(ctx.raw || 0);
                  return `${ctx.label}: ${v.toLocaleString('th-TH',{minimumFractionDigits:2, maximumFractionDigits:2})} ฿`;
                }
              }
            }
          }
        }
      });
    }
  })();
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>