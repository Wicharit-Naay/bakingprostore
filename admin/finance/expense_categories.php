

<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

// ---------- Inputs ----------
$q = trim($_GET['q'] ?? '');
$msg = '';
$err = '';

// ---------- Helpers ----------
function can_use_expense_categories(PDO $pdo): bool {
  try {
    $pdo->query("SELECT 1 FROM expense_categories LIMIT 1");
    return true;
  } catch (Throwable $e) {
    return false;
  }
}

function get_category_usage(PDO $pdo): array {
  // returns map category_id => count
  $map = [];
  try {
    $rs = $pdo->query("SELECT category_id, COUNT(*) c FROM expenses GROUP BY category_id");
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $cid = (int)($r['category_id'] ?? 0);
      $map[$cid] = (int)($r['c'] ?? 0);
    }
  } catch (Throwable $e) {
    // ignore
  }
  return $map;
}

if (!can_use_expense_categories($pdo)) {
  $pageTitle = 'หมวดหมู่รายจ่าย';
  require_once __DIR__ . '/../../templates/admin_header.php';
  ?>
  <div class="alert alert-danger border">
    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>ยังไม่มีตาราง expense_categories</div>
    <div class="small text-muted">กรุณารัน SQL สร้างตาราง <code>expense_categories</code> ก่อน แล้วค่อยกลับมาเปิดหน้านี้</div>
  </div>
  <?php
  require_once __DIR__ . '/../../templates/footer.php';
  exit;
}

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'add') {
      $name = trim($_POST['name'] ?? '');
      if ($name === '') throw new RuntimeException('กรุณากรอกชื่อหมวดหมู่');

      $st = $pdo->prepare("INSERT INTO expense_categories(name) VALUES (?)");
      $st->execute([$name]);
      $msg = 'เพิ่มหมวดหมู่เรียบร้อย';

    } elseif ($action === 'edit') {
      $id = (int)($_POST['id'] ?? 0);
      $name = trim($_POST['name'] ?? '');
      if ($id <= 0) throw new RuntimeException('ไม่พบรหัสหมวดหมู่');
      if ($name === '') throw new RuntimeException('กรุณากรอกชื่อหมวดหมู่');

      $st = $pdo->prepare("UPDATE expense_categories SET name=? WHERE id=?");
      $st->execute([$name, $id]);
      $msg = 'บันทึกการแก้ไขเรียบร้อย';

    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException('ไม่พบรหัสหมวดหมู่');

      // block delete if used
      $cnt = 0;
      try {
        $cst = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category_id=?");
        $cst->execute([$id]);
        $cnt = (int)$cst->fetchColumn();
      } catch (Throwable $e) {
        $cnt = 0;
      }
      if ($cnt > 0) throw new RuntimeException('ลบไม่ได้: มีรายการรายจ่ายใช้งานหมวดหมู่นี้อยู่');

      $st = $pdo->prepare("DELETE FROM expense_categories WHERE id=?");
      $st->execute([$id]);
      $msg = 'ลบหมวดหมู่เรียบร้อย';

    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

// ---------- Data ----------
$params = [];
$where = '';
if ($q !== '') {
  $where = 'WHERE name LIKE ?';
  $params[] = '%' . $q . '%';
}

$stmt = $pdo->prepare("SELECT * FROM expense_categories {$where} ORDER BY id DESC");
$stmt->execute($params);
$cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$usageMap = get_category_usage($pdo);

$pageTitle = 'หมวดหมู่รายจ่าย';
require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">หมวดหมู่รายจ่าย</h2>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/finance/index.php"><i class="bi bi-arrow-left me-1"></i>กลับการเงิน</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/finance/expenses.php"><i class="bi bi-receipt me-1"></i>ไปหน้ารายจ่าย</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success border small"><i class="bi bi-check-circle me-1"></i><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger border small"><i class="bi bi-x-circle me-1"></i><?= h($err) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <form class="d-flex gap-2" method="get" style="min-width:280px; flex: 1 1 auto; max-width: 560px;">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="ค้นหาหมวดหมู่รายจ่าย...">
        </div>
        <button class="btn btn-primary" type="submit">ค้นหา</button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/finance/expense_categories.php">ล้าง</a>
      </form>

      <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addBox" aria-expanded="false">
        <i class="bi bi-plus-circle me-1"></i>เพิ่มหมวดหมู่
      </button>
    </div>

    <div class="collapse mt-3" id="addBox">
      <div class="border rounded-3 p-3 bg-light">
        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="action" value="add">
          <div class="col-12 col-md-8">
            <label class="form-label">ชื่อหมวดหมู่</label>
            <input class="form-control" name="name" placeholder="เช่น ค่าไฟ, ค่าน้ำ, ค่าวัตถุดิบ" required>
          </div>
          <div class="col-12 col-md-4 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-save me-1"></i>บันทึก</button>
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#addBox">ยกเลิก</button>
          </div>
          <div class="col-12">
          </div>
        </form>
      </div>
    </div>

  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:90px;">ID</th>
          <th>ชื่อหมวดหมู่</th>
          <th class="text-center" style="width:140px;">จำนวนรายการ</th>
          <th style="width:200px;">วันที่สร้าง</th>
          <th class="text-end" style="width:220px;">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$cats): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-5">ยังไม่มีหมวดหมู่รายจ่าย</td>
          </tr>
        <?php else: ?>
          <?php foreach ($cats as $c): ?>
            <?php
              $cid = (int)($c['id'] ?? 0);
              $use = (int)($usageMap[$cid] ?? 0);
            ?>
            <tr>
              <td class="fw-semibold">#<?= $cid ?></td>
              <td>
                <div class="fw-semibold"><?= h($c['name'] ?? '') ?></div>
                <div class="small text-muted">ใช้ในรายจ่าย: <?= $use ?> รายการ</div>
              </td>
              <td class="text-center">
                <span class="badge text-bg-<?= $use > 0 ? 'primary' : 'secondary' ?>"><?= $use ?></span>
              </td>
              <td class="text-muted small"><?= h($c['created_at'] ?? '-') ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <!-- Edit trigger -->
                  <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#editBox<?= $cid ?>">
                    <i class="bi bi-pencil-square me-1"></i>แก้ไข
                  </button>

                  <!-- Delete -->
                  <form method="post" class="m-0" onsubmit="return confirm('ยืนยันลบหมวดหมู่นี้?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $cid ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit" <?= $use > 0 ? 'disabled title="ลบไม่ได้: มีรายการใช้งานอยู่"' : '' ?>>
                      <i class="bi bi-trash me-1"></i>ลบ
                    </button>
                  </form>
                </div>

                <!-- Edit box -->
                <div class="collapse mt-2" id="editBox<?= $cid ?>">
                  <div class="border rounded-3 p-3 bg-light text-start">
                    <form method="post" class="row g-2 align-items-end">
                      <input type="hidden" name="action" value="edit">
                      <input type="hidden" name="id" value="<?= $cid ?>">
                      <div class="col-12 col-md-8">
                        <label class="form-label">ชื่อหมวดหมู่</label>
                        <input class="form-control" name="name" value="<?= h($c['name'] ?? '') ?>" required>
                      </div>
                      <div class="col-12 col-md-4 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-save me-1"></i>บันทึก</button>
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editBox<?= $cid ?>">ยกเลิก</button>
                      </div>
                      <?php if ($use > 0): ?>
                        <div class="col-12">
                          <div class="small text-muted">* หมวดหมู่นี้ถูกใช้งานอยู่ ลบไม่ได้ แต่แก้ชื่อได้</div>
                        </div>
                      <?php endif; ?>
                    </form>
                  </div>
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