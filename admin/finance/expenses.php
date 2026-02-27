

<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

// Make sure session exists (for CSRF + admin name)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$pageTitle = 'รายจ่าย';

// Helper: safe admin display
$adminId = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
$adminName = (string)($_SESSION['user']['name'] ?? ($_SESSION['user']['email'] ?? 'Admin'));

// ---- Load categories ----
$cats = [];
try {
  $cats = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // categories optional
  $cats = [];
}

// ---- Filters ----
$q = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);
$payMethod = trim($_GET['pay_method'] ?? '');

// Default date range: current month
if ($from === '' && $to === '') {
  $from = date('Y-m-01');
  $to = date('Y-m-t');
}

$msg = '';
$err = '';

// ---- Actions: create / update / delete ----
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check
  $postedCsrf = (string)($_POST['csrf'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $err = 'CSRF ไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่';
  } else {
    try {
      if ($action === 'create') {
        $expense_date = trim($_POST['expense_date'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        $ref_no = trim($_POST['ref_no'] ?? '');
        $pay_method = trim($_POST['pay_method'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($expense_date === '') throw new RuntimeException('กรุณาเลือกวันที่รายจ่าย');
        if ($title === '') throw new RuntimeException('กรุณากรอกชื่อรายการ');
        if ($amount <= 0) throw new RuntimeException('จำนวนเงินต้องมากกว่า 0');

        // If categories exist, allow 0 = none
        $sql = "INSERT INTO expenses (expense_date, category_id, title, amount, note, pay_method, ref_no, created_by, created_at, updated_at)
                VALUES (:expense_date, :category_id, :title, :amount, :note, :pay_method, :ref_no, :created_by, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':expense_date' => $expense_date,
          ':category_id' => ($category_id > 0 ? $category_id : null),
          ':title' => $title,
          ':amount' => $amount,
          ':note' => ($note !== '' ? $note : null),
          ':pay_method' => ($pay_method !== '' ? $pay_method : null),
          ':ref_no' => ($ref_no !== '' ? $ref_no : null),
          ':created_by' => ($adminId > 0 ? $adminId : null),
        ]);

        $msg = 'บันทึกรายจ่ายแล้ว';

      } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $expense_date = trim($_POST['expense_date'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        $ref_no = trim($_POST['ref_no'] ?? '');
        $pay_method = trim($_POST['pay_method'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($id <= 0) throw new RuntimeException('ไม่พบรายการที่ต้องการแก้ไข');
        if ($expense_date === '') throw new RuntimeException('กรุณาเลือกวันที่รายจ่าย');
        if ($title === '') throw new RuntimeException('กรุณากรอกชื่อรายการ');
        if ($amount <= 0) throw new RuntimeException('จำนวนเงินต้องมากกว่า 0');

        $sql = "UPDATE expenses
                SET expense_date=:expense_date,
                    category_id=:category_id,
                    title=:title,
                    amount=:amount,
                    note=:note,
                    pay_method=:pay_method,
                    ref_no=:ref_no,
                    updated_at=NOW()
                WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':expense_date' => $expense_date,
          ':category_id' => ($category_id > 0 ? $category_id : null),
          ':title' => $title,
          ':amount' => $amount,
          ':note' => ($note !== '' ? $note : null),
          ':pay_method' => ($pay_method !== '' ? $pay_method : null),
          ':ref_no' => ($ref_no !== '' ? $ref_no : null),
          ':id' => $id,
        ]);

        $msg = 'อัปเดตรายจ่ายแล้ว';

      } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('ไม่พบรายการที่ต้องการลบ');
        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id=?');
        $stmt->execute([$id]);
        $msg = 'ลบรายการแล้ว';
      }

    } catch (Throwable $e) {
      $err = $e->getMessage();
    }
  }
}

// ---- Load rows ----
$params = [];
$where = [];

if ($q !== '') {
  $where[] = '(e.title LIKE :q OR e.note LIKE :q OR e.ref_no LIKE :q)';
  $params[':q'] = '%' . $q . '%';
}
if ($from !== '') {
  $where[] = 'e.expense_date >= :from';
  $params[':from'] = $from;
}
if ($to !== '') {
  $where[] = 'e.expense_date <= :to';
  $params[':to'] = $to;
}
if ($categoryId > 0) {
  $where[] = 'e.category_id = :category_id';
  $params[':category_id'] = $categoryId;
}
if ($payMethod !== '') {
  $where[] = 'e.pay_method = :pay_method';
  $params[':pay_method'] = $payMethod;
}

$sql = "SELECT e.*, c.name AS category_name, u.name AS created_by_name
        FROM expenses e
        LEFT JOIN expense_categories c ON c.id = e.category_id
        LEFT JOIN users u ON u.id = e.created_by";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY e.expense_date DESC, e.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0.0;
foreach ($rows as $r) {
  $total += (float)($r['amount'] ?? 0);
}

// If edit requested
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
  foreach ($rows as $r) {
    if ((int)$r['id'] === $editId) { $editRow = $r; break; }
  }
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">รายจ่าย</h2>
    <div class="text-muted small">บันทึกรายจ่าย, ค้นหา/กรองตามวัน และดูยอดรวมช่วงวันที่</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/finance/index.php"><i class="bi bi-graph-up me-1"></i>ภาพรวม</a>
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#expenseFormWrap" aria-expanded="<?= $editRow ? 'true' : 'false' ?>">
      <i class="bi bi-plus-lg me-1"></i><?= $editRow ? 'แก้ไขรายการ' : 'เพิ่มรายจ่าย' ?>
    </button>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success py-2 small"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger py-2 small"><?= h($err) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-3">
        <label class="form-label">ค้นหา</label>
        <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="ชื่อรายการ/หมายเหตุ/เลขอ้างอิง">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">จากวันที่</label>
        <input class="form-control" type="date" name="from" value="<?= h($from) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">ถึงวันที่</label>
        <input class="form-control" type="date" name="to" value="<?= h($to) ?>">
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">หมวดหมู่</label>
        <select class="form-select" name="category_id">
          <option value="0">ทั้งหมด</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $categoryId) ? 'selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">วิธีจ่าย</label>
        <select class="form-select" name="pay_method">
          <option value="">ทั้งหมด</option>
          <?php foreach (['cash'=>'เงินสด','transfer'=>'โอน','card'=>'บัตร','other'=>'อื่นๆ'] as $k=>$v): ?>
            <option value="<?= h($k) ?>" <?= ($payMethod === $k) ? 'selected' : '' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-1 d-grid">
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
      </div>
    </form>

    <hr class="my-3">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="small text-muted">
        พบ <b><?= count($rows) ?></b> รายการ
        <?php if ($from || $to): ?>
          <span class="ms-1">(ช่วง <?= h($from ?: '-') ?> ถึง <?= h($to ?: '-') ?>)</span>
        <?php endif; ?>
      </div>
      <div class="text-end">
        <div class="text-muted small">ยอดรวมรายจ่าย</div>
        <div class="fs-5 fw-semibold text-danger"><?= number_format($total, 2) ?> ฿</div>
      </div>
    </div>
  </div>
</div>

<div class="collapse <?= $editRow ? 'show' : '' ?>" id="expenseFormWrap">
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <div class="fw-semibold"><i class="bi bi-receipt me-1"></i><?= $editRow ? 'แก้ไขรายจ่าย' : 'เพิ่มรายจ่าย' ?></div>
      <?php if ($editRow): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/finance/expenses.php?<?= http_build_query(array_filter([ 'q'=>$q, 'from'=>$from, 'to'=>$to, 'category_id'=>$categoryId ?: null, 'pay_method'=>$payMethod ?: null ])) ?>">
          <i class="bi bi-x-lg me-1"></i>ยกเลิกแก้ไข
        </a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
        <?php if ($editRow): ?>
          <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
        <?php endif; ?>

        <div class="col-12 col-md-3">
          <label class="form-label">วันที่รายจ่าย</label>
          <input class="form-control" type="date" name="expense_date" required value="<?= h($editRow['expense_date'] ?? date('Y-m-d')) ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">ชื่อรายการ</label>
          <input class="form-control" name="title" required placeholder="เช่น ค่าวัตถุดิบ / ค่าแพ็กเกจ" value="<?= h($editRow['title'] ?? '') ?>">
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label">จำนวนเงิน</label>
          <input class="form-control" type="number" step="0.01" min="0" name="amount" required value="<?= h((string)($editRow['amount'] ?? '')) ?>">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">หมวดหมู่</label>
          <select class="form-select" name="category_id">
            <option value="0">- ไม่ระบุ -</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)($editRow['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">วิธีจ่าย</label>
          <select class="form-select" name="pay_method">
            <option value="">- ไม่ระบุ -</option>
            <?php foreach (['cash'=>'เงินสด','transfer'=>'โอน','card'=>'บัตร','other'=>'อื่นๆ'] as $k=>$v): ?>
              <option value="<?= h($k) ?>" <?= ((string)($editRow['pay_method'] ?? '') === $k) ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">เลขอ้างอิง (ถ้ามี)</label>
          <input class="form-control" name="ref_no" placeholder="ใบเสร็จ/บิล" value="<?= h($editRow['ref_no'] ?? '') ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">หมายเหตุ</label>
          <input class="form-control" name="note" placeholder="รายละเอียดเพิ่มเติม" value="<?= h($editRow['note'] ?? '') ?>">
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i><?= $editRow ? 'บันทึกการแก้ไข' : 'บันทึก' ?></button>
          <?php if (!$editRow): ?>
            <button class="btn btn-outline-secondary" type="reset"><i class="bi bi-arrow-counterclockwise me-1"></i>ล้างค่า</button>
          <?php endif; ?>
        </div>

        <div class="small text-muted">
          ผู้บันทึก: <b><?= h($adminName ?: 'Admin') ?></b>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <div class="fw-semibold"><i class="bi bi-list-ul me-1"></i>รายการรายจ่าย</div>
    <span class="badge text-bg-light border"><?= count($rows) ?> รายการ</span>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:90px;">#</th>
          <th style="width:120px;">วันที่</th>
          <th>รายการ</th>
          <th style="width:160px;">หมวดหมู่</th>
          <th style="width:120px;">วิธีจ่าย</th>
          <th class="text-end" style="width:140px;">จำนวนเงิน</th>
          <th style="width:160px;">ผู้บันทึก</th>
          <th class="text-end" style="width:160px;">จัดการ</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-5">ไม่มีข้อมูลรายจ่ายในช่วงนี้</td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $payTh = match ((string)($r['pay_method'] ?? '')) {
              'cash' => 'เงินสด',
              'transfer' => 'โอน',
              'card' => 'บัตร',
              'other' => 'อื่นๆ',
              default => '-',
            };
          ?>
          <tr>
            <td class="fw-semibold">#<?= (int)$r['id'] ?></td>
            <td><?= h($r['expense_date'] ?? '') ?></td>
            <td>
              <div class="fw-semibold"><?= h($r['title'] ?? '') ?></div>
              <?php if (!empty($r['note'])): ?>
                <div class="small text-muted text-truncate" style="max-width:520px;"><?= h($r['note']) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['ref_no'])): ?>
                <div class="small text-muted"><i class="bi bi-hash"></i> <?= h($r['ref_no']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= h($r['category_name'] ?? '-') ?></td>
            <td><?= h($payTh) ?></td>
            <td class="text-end fw-semibold text-danger"><?= number_format((float)($r['amount'] ?? 0), 2) ?></td>
            <td><?= h($r['created_by_name'] ?? '-') ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-2">
                <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/finance/expenses.php?<?= http_build_query(array_filter([ 'q'=>$q ?: null, 'from'=>$from ?: null, 'to'=>$to ?: null, 'category_id'=>$categoryId ?: null, 'pay_method'=>$payMethod ?: null, 'edit'=>(int)$r['id'] ])) ?>">
                  <i class="bi bi-pencil me-1"></i>แก้ไข
                </a>
                <form method="post" onsubmit="return confirm('ลบรายการนี้?');">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash me-1"></i>ลบ</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>