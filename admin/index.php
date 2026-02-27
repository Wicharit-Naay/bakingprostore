<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';
require_admin();

// ===== Dashboard stats =====
try {
  $statProducts   = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
  $statCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
  $statCustomers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
  $statAdmins     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
  $statOrdersAll  = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
  $statOrdersPend = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
  $statReviews    = (int)$pdo->query("SELECT COUNT(*) FROM product_reviews")->fetchColumn();
  $statQrScans    = (int)$pdo->query("SELECT COUNT(*) FROM qr_scans")->fetchColumn();
} catch (Throwable $e) {
  // ถ้าบางตารางยังไม่ได้สร้าง ให้ไม่ล่มทั้งหน้า
  $statProducts = $statCategories = $statCustomers = $statAdmins = $statOrdersAll = $statOrdersPend = 0;
  $statReviews = $statQrScans = 0;
}

$meName = $_SESSION['user']['name'] ?? 'Admin';

require_once __DIR__ . '/../templates/admin_header.php';
?>
<style>
  /* Admin-only overrides: กัน CSS ฝั่งหน้าร้านที่ไปทับ .badge/.list-group แล้วทำให้ข้อความซ้อนกัน */
  .admin-scope .badge{
    position: static !important;
    inset: auto !important;
    transform: none !important;
    float: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: .75rem !important;
    line-height: 1 !important;
    padding: .35rem .5rem !important;
    border-radius: 999px !important;
    white-space: nowrap !important;
    max-width: 100%;
  }
  .admin-scope .list-group-item{
    gap: .75rem;
    flex-wrap: wrap;
  }
  .admin-scope .list-group-item > span:first-child{
    min-width: 220px;
  }
  .admin-scope .card-header{
    gap: .75rem;
    flex-wrap: wrap;
  }
  .admin-scope .card-header .badge{
    margin-left: .5rem;
  }
</style>

<div class="admin-scope container py-4 py-lg-5">
  <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <div class="text-muted small">Admin Dashboard</div>
      <h1 class="h3 mb-1">หลังร้าน</h1>
      <div class="text-muted">สวัสดี, <span class="fw-semibold"><?= htmlspecialchars($meName) ?></span></div>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/public/index.php">
        <i class="bi bi-shop me-1"></i> ไปหน้าร้าน
      </a>
      <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/orders/index.php">
        <i class="bi bi-receipt me-1"></i> ดูออเดอร์
        <?php if ($statOrdersPend > 0): ?>
          <span class="badge text-bg-danger ms-2"><?= (int)$statOrdersPend ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>

  <!-- Stats cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-muted small">สินค้า</div>
              <div class="h4 mb-0"><?= (int)$statProducts ?></div>
            </div>
            <div class="rounded-circle border d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
              <i class="bi bi-box-seam"></i>
            </div>
          </div>
          <div class="mt-3">
            <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/products/index.php">จัดการสินค้า <i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-muted small">หมวดหมู่</div>
              <div class="h4 mb-0"><?= (int)$statCategories ?></div>
            </div>
            <div class="rounded-circle border d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
              <i class="bi bi-grid-3x3-gap"></i>
            </div>
          </div>
          <div class="mt-3">
            <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/categories/index.php">จัดการหมวดหมู่ <i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-muted small">ออเดอร์ทั้งหมด</div>
              <div class="h4 mb-0"><?= (int)$statOrdersAll ?></div>
            </div>
            <div class="rounded-circle border d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
              <i class="bi bi-receipt"></i>
            </div>
          </div>
          <div class="mt-3 d-flex align-items-center justify-content-between">
            <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/orders/index.php">จัดการออเดอร์ <i class="bi bi-arrow-right"></i></a>
            <?php if ($statOrdersPend > 0): ?>
              <span class="badge text-bg-danger">ค้าง <?= (int)$statOrdersPend ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-muted small">ผู้ใช้</div>
              <div class="h4 mb-0"><?= (int)($statCustomers + $statAdmins) ?></div>
              <div class="text-muted small">ลูกค้า <?= (int)$statCustomers ?> • แอดมิน <?= (int)$statAdmins ?></div>
            </div>
            <div class="rounded-circle border d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
              <i class="bi bi-people"></i>
            </div>
          </div>
          <div class="mt-3">
            <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/customers/index.php">ดูข้อมูลลูกค้า <i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick actions + Overview -->
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
          <div class="fw-semibold"><i class="bi bi-lightning-charge me-1"></i>ทางลัดจัดการระบบ</div>
          <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/orders/index.php">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-md-6">
              <a class="text-decoration-none" href="<?= BASE_URL ?>/admin/products/index.php">
                <div class="border rounded-4 p-3 h-100 bg-white shadow-sm" style="transition:transform .12s ease, box-shadow .12s ease;">
                  <div class="d-flex align-items-start gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(13,110,253,.12);">
                      <i class="bi bi-box-seam text-primary"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width:0;">
                      <div class="fw-semibold text-dark">จัดการสินค้า</div>
                      <div class="text-muted small">เพิ่ม/แก้ไข/ลบ/อัปโหลดรูป</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                  </div>
                </div>
              </a>
            </div>

            <div class="col-md-6">
              <a class="text-decoration-none" href="<?= BASE_URL ?>/admin/categories/index.php">
                <div class="border rounded-4 p-3 h-100 bg-white shadow-sm" style="transition:transform .12s ease, box-shadow .12s ease;">
                  <div class="d-flex align-items-start gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(13,110,253,.12);">
                      <i class="bi bi-grid-3x3-gap text-primary"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width:0;">
                      <div class="fw-semibold text-dark">จัดการหมวดหมู่</div>
                      <div class="text-muted small">จัดระเบียบประเภทสินค้า</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                  </div>
                </div>
              </a>
            </div>

            <div class="col-md-6">
              <a class="text-decoration-none" href="<?= BASE_URL ?>/admin/orders/index.php">
                <div class="border rounded-4 p-3 h-100 bg-white shadow-sm" style="transition:transform .12s ease, box-shadow .12s ease;">
                  <div class="d-flex align-items-start gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(13,110,253,.12);">
                      <i class="bi bi-receipt text-primary"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width:0;">
                      <div class="fw-semibold text-dark">จัดการออเดอร์</div>
                      <div class="text-muted small">ตรวจสอบ/อัปเดตสถานะ</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                  </div>
                </div>
              </a>
            </div>

            <div class="col-md-6">
              <a class="text-decoration-none" href="<?= BASE_URL ?>/admin/customers/index.php">
                <div class="border rounded-4 p-3 h-100 bg-white shadow-sm" style="transition:transform .12s ease, box-shadow .12s ease;">
                  <div class="d-flex align-items-start gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(13,110,253,.12);">
                      <i class="bi bi-people text-primary"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width:0;">
                      <div class="fw-semibold text-dark">ข้อมูลลูกค้า</div>
                      <div class="text-muted small">ดูรายชื่อผู้ใช้งาน</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                  </div>
                </div>
              </a>
            </div>
          </div>

          <hr class="my-4">

          <div class="row g-2">
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="fw-semibold"><i class="bi bi-star me-1"></i>รีวิวสินค้า</div>
                  <div class="badge text-bg-light"><?= (int)$statReviews ?> รายการ</div>
                </div>
                <div class="text-muted small mt-1">รวมคะแนนและคอมเมนต์จากลูกค้า</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="fw-semibold"><i class="bi bi-qr-code-scan me-1"></i>สแกน QR</div>
                  <div class="badge text-bg-light"><?= (int)$statQrScans ?> ครั้ง</div>
                </div>
                <div class="text-muted small mt-1">เก็บประวัติการสแกนและแต้ม/ยอดสะสม</div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header bg-white border-0 fw-semibold"><i class="bi bi-shield-check me-1"></i>สรุปสถานะระบบ</div>
        <div class="card-body">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex align-items-center justify-content-between">
              <span><i class="bi bi-check2-circle me-2"></i>ฐานข้อมูลเชื่อมต่อ</span>
              <span class="badge text-bg-success">พร้อมใช้งาน</span>
            </li>
            <li class="list-group-item d-flex align-items-center justify-content-between">
              <span><i class="bi bi-bag-check me-2"></i>สินค้าที่มีอยู่</span>
              <span class="badge text-bg-primary"><?= (int)$statProducts ?></span>
            </li>
            <li class="list-group-item d-flex align-items-center justify-content-between">
              <span><i class="bi bi-receipt-cutoff me-2"></i>ออเดอร์รอดำเนินการ</span>
              <span class="badge text-bg-danger"><?= (int)$statOrdersPend ?></span>
            </li>
            <li class="list-group-item d-flex align-items-center justify-content-between">
              <span><i class="bi bi-people me-2"></i>ลูกค้าทั้งหมด</span>
              <span class="badge text-bg-secondary"><?= (int)$statCustomers ?></span>
            </li>
          </ul>

        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>