<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/cart.php';

cart_init();

// ===== Handle actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Add to cart
  if (isset($_POST['add_id'])) {
    cart_add($_POST['add_id'], $_POST['qty'] ?? 1);
    redirect('/public/cart.php');
  }

  // Update qty (set)
  if (isset($_POST['set_qty_id'])) {
    $id  = (int)($_POST['set_qty_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);

    if ($id > 0) {
      if ($qty <= 0) {
        cart_remove($id);
      } else {
        // clamp to reasonable max (protect from absurd input)
        $qty = max(1, min(999, $qty));
        $_SESSION['cart'][$id] = $qty;
      }
    }
    redirect('/public/cart.php');
  }

  // Remove item
  if (isset($_POST['remove_id'])) {
    cart_remove($_POST['remove_id']);
    redirect('/public/cart.php');
  }

  // Clear cart
  if (isset($_POST['clear'])) {
    cart_clear();
    redirect('/public/cart.php');
  }
}

$cart = (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];
$ids = array_keys($cart);

$items = [];
$subtotal = 0.0;

if (count($ids) > 0) {
  $in = implode(',', array_fill(0, count($ids), '?'));

  // Keep cart order
  $orderBy = 'ORDER BY FIELD(id,' . implode(',', array_map('intval', $ids)) . ')';

  $stmt = $pdo->prepare("SELECT id, name, price, image, stock FROM products WHERE id IN ($in) $orderBy");
  $stmt->execute($ids);
  $items = $stmt->fetchAll();

  foreach ($items as &$p) {
    $id = (int)$p['id'];
    $qty = (int)($cart[$id] ?? 0);
    $price = (float)($p['price'] ?? 0);

    // If stock exists, clamp qty to stock
    $stock = isset($p['stock']) ? (int)$p['stock'] : 0;
    if ($stock > 0 && $qty > $stock) {
      $qty = $stock;
      $_SESSION['cart'][$id] = $qty;
    }

    $p['qty'] = $qty;
    $p['sub'] = $qty * $price;
    $subtotal += $p['sub'];
  }
  unset($p);
}

// Shipping rules (simple)
$shipping = 0.0;
if ($subtotal > 0) {
  $shipping = ($subtotal >= 500) ? 0.0 : 40.0;
}

$grandTotal = $subtotal + $shipping;

$pageTitle = 'ตะกร้าสินค้า - BakingProStore';
include __DIR__ . '/../templates/header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0">ตะกร้าสินค้า</h2>
    <div class="text-muted small">ตรวจสอบรายการสินค้าและจำนวนก่อนสั่งซื้อ</div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/public/index.php">
    <i class="bi bi-arrow-left me-1"></i>เลือกซื้อสินค้าต่อ
  </a>
</div>

<?php if (empty($items)): ?>
  <div class="card shadow-sm">
    <div class="card-body py-5 text-center">
      <div class="text-muted mb-2"><i class="bi bi-cart3" style="font-size:2.25rem;"></i></div>
      <div class="fw-semibold">ยังไม่มีสินค้าในตะกร้า</div>
      <div class="text-muted small mt-1">ไปที่หน้าร้านแล้วกดหยิบสินค้าที่ต้องการได้เลย</div>
      <a class="btn btn-primary mt-3" href="<?= BASE_URL ?>/public/index.php">
        <i class="bi bi-shop me-1"></i>ไปหน้าร้าน
      </a>
    </div>
  </div>
<?php else: ?>

  <div class="row g-3">
    <!-- Items -->
    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:84px;">รูป</th>
                <th>สินค้า</th>
                <th class="text-end" style="width:120px;">ราคา</th>
                <th class="text-center" style="width:190px;">จำนวน</th>
                <th class="text-end" style="width:120px;">รวม</th>
                <th class="text-end" style="width:90px;">ลบ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $p): ?>
                <?php
                  $img = !empty($p['image']) ? (BASE_URL . '/' . ltrim(h($p['image']), '/')) : '';
                  $stock = isset($p['stock']) ? (int)$p['stock'] : 0;
                  $maxQty = ($stock > 0) ? max(1, min(999, $stock)) : 999;
                ?>
                <tr>
                  <td>
                    <a class="d-block ratio ratio-1x1 rounded overflow-hidden border bg-light" href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$p['id'] ?>" style="width:68px;">
                      <?php if ($img): ?>
                        <img src="<?= $img ?>" alt="" style="object-fit:cover;" loading="lazy">
                      <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center text-muted">
                          <i class="bi bi-image"></i>
                        </div>
                      <?php endif; ?>
                    </a>
                  </td>

                  <td>
                    <div class="fw-semibold">
                      <a class="text-decoration-none" href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$p['id'] ?>">
                        <?= h($p['name']) ?>
                      </a>
                    </div>
                    <div class="text-muted small">รหัสสินค้า: #<?= (int)$p['id'] ?></div>
                    <?php if ($stock > 0): ?>
                      <div class="text-muted small">คงเหลือ: <?= $stock ?></div>
                    <?php endif; ?>
                  </td>

                  <td class="text-end">
                    <span data-unit-price><?= number_format((float)$p['price'], 2) ?></span>
                  </td>

                  <td class="text-center">
                    <form class="d-inline" method="post">
                      <input type="hidden" name="set_qty_id" value="<?= (int)$p['id'] ?>">

                      <div class="input-group input-group-sm justify-content-center" style="max-width: 170px; margin:0 auto;">
                        <button class="btn btn-outline-secondary" type="button" data-qty-minus>
                          <i class="bi bi-dash"></i>
                        </button>
                        <input
                          class="form-control text-center"
                          type="number"
                          name="qty"
                          value="<?= (int)$p['qty'] ?>"
                          min="1"
                          max="<?= (int)$maxQty ?>"
                          inputmode="numeric"
                          data-qty-input
                          data-stock-max="<?= (int)$maxQty ?>"
                        >
                        <button class="btn btn-outline-secondary" type="button" data-qty-plus>
                          <i class="bi bi-plus"></i>
                        </button>
                      </div>

                      <div class="form-text">สูงสุด <?= (int)$maxQty ?> ชิ้น</div>
                    </form>
                  </td>

                  <td class="text-end fw-semibold">
                    <span data-row-subtotal><?= number_format((float)$p['sub'], 2) ?></span>
                  </td>

                  <td class="text-end">
                    <form method="post" onsubmit="return confirm('ลบสินค้านี้ออกจากตะกร้า?');" class="m-0">
                      <input type="hidden" name="remove_id" value="<?= (int)$p['id'] ?>">
                      <button class="btn btn-outline-danger btn-sm" type="submit" title="ลบ">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <form method="post" onsubmit="return confirm('ล้างตะกร้าทั้งหมด?');" class="m-0">
          <button class="btn btn-outline-secondary" type="submit" name="clear" value="1">
            <i class="bi bi-x-circle me-1"></i>ล้างตะกร้า
          </button>
        </form>
      </div>
    </div>

    <!-- Summary -->
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm position-sticky" style="top: 92px;">
        <div class="card-body">
          <div class="fw-semibold mb-2">สรุปยอด</div>

          <div class="d-flex justify-content-between mb-2">
            <div class="text-muted">ยอดสินค้า</div>
            <div class="fw-semibold"><span data-summary-subtotal><?= number_format($subtotal, 2) ?></span></div>
          </div>

          <div class="d-flex justify-content-between mb-2">
            <div class="text-muted">ค่าจัดส่ง</div>
            <div class="fw-semibold">
              <span data-summary-shipping>
                <?php if ($shipping <= 0): ?>
                  <span class="text-success">ฟรี</span>
                <?php else: ?>
                  <?= number_format($shipping, 2) ?>
                <?php endif; ?>
              </span>
            </div>
          </div>

          <hr class="my-2">

          <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">ยอดชำระ</div>
            <div class="fs-4 fw-semibold"><span data-summary-grand><?= number_format($grandTotal, 2) ?></span></div>
          </div>

          <?php if ($subtotal > 0 && $shipping > 0): ?>
            <div class="small text-muted mt-1" data-summary-freehint>
              ซื้อเพิ่มอีก <?= number_format(max(0, 500 - $subtotal), 2) ?> บาท เพื่อส่งฟรี
            </div>
          <?php endif; ?>

          <div class="d-grid gap-2 mt-3">
            <a class="btn btn-primary" href="<?= BASE_URL ?>/public/checkout.php">
              <i class="bi bi-credit-card me-1"></i>ไปหน้าสั่งซื้อ
            </a>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/public/index.php">
              <i class="bi bi-shop me-1"></i>เลือกซื้อเพิ่ม
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  function fmt(n){
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function getRowEls(form){
    const tr = form.closest('tr');
    if (!tr) return null;
    const unitEl = tr.querySelector('[data-unit-price]');
    const subEl  = tr.querySelector('[data-row-subtotal]');
    const input  = form.querySelector('[data-qty-input]');
    if (!unitEl || !subEl || !input) return null;
    const unit = parseFloat(String(unitEl.textContent || '0').replace(/,/g,'')) || 0;
    return { tr, unitEl, subEl, input, unit };
  }

  function recalcAll(){
    let subtotal = 0;
    document.querySelectorAll('tbody tr').forEach(function(tr){
      const unitEl = tr.querySelector('[data-unit-price]');
      const subEl  = tr.querySelector('[data-row-subtotal]');
      const input  = tr.querySelector('[data-qty-input]');
      if (!unitEl || !subEl || !input) return;
      const unit = parseFloat(String(unitEl.textContent || '0').replace(/,/g,'')) || 0;
      const qty  = parseInt(input.value || '1', 10) || 1;
      const rowSub = unit * qty;
      subEl.textContent = fmt(rowSub);
      subtotal += rowSub;
    });

    const shipping = (subtotal > 0) ? ((subtotal >= 500) ? 0 : 40) : 0;
    const grand = subtotal + shipping;

    const subSumEl = document.querySelector('[data-summary-subtotal]');
    const shipEl   = document.querySelector('[data-summary-shipping]');
    const grandEl  = document.querySelector('[data-summary-grand]');
    const hintEl   = document.querySelector('[data-summary-freehint]');

    if (subSumEl) subSumEl.textContent = fmt(subtotal);
    if (shipEl) {
      if (shipping <= 0 && subtotal > 0) {
        shipEl.innerHTML = '<span class="text-success">ฟรี</span>';
      } else {
        shipEl.textContent = fmt(shipping);
      }
    }
    if (grandEl) grandEl.textContent = fmt(grand);

    if (hintEl) {
      if (subtotal > 0 && subtotal < 500) {
        hintEl.style.display = '';
        hintEl.textContent = 'ซื้อเพิ่มอีก ' + fmt(500 - subtotal) + ' บาท เพื่อส่งฟรี';
      } else {
        hintEl.style.display = 'none';
      }
    }
  }

  const submitTimers = new WeakMap();
  function scheduleSubmit(form){
    if (!form) return;
    const old = submitTimers.get(form);
    if (old) clearTimeout(old);
    const t = setTimeout(function(){
      // submit to persist in session and refresh totals from server
      form.submit();
    }, 350);
    submitTimers.set(form, t);
  }

  function clamp(input){
    const min = parseInt(input.min || '1', 10) || 1;
    const max = parseInt(input.max || input.dataset.stockMax || '999', 10) || 999;
    let v = parseInt(input.value || String(min), 10);
    if (isNaN(v)) v = min;
    v = Math.max(min, Math.min(max, v));
    input.value = String(v);
    return v;
  }

  // qty + / - controls
  document.querySelectorAll('[data-qty-minus]').forEach(function(btn){
    btn.addEventListener('click', function(){
      const form = btn.closest('form');
      if(!form) return;
      const input = form.querySelector('[data-qty-input]');
      if(!input) return;
      const min = parseInt(input.min || '1', 10) || 1;
      let v = parseInt(input.value || String(min), 10);
      if (isNaN(v)) v = min;
      input.value = String(Math.max(min, v - 1));
      clamp(input);
      recalcAll();
      scheduleSubmit(form);
    });
  });

  document.querySelectorAll('[data-qty-plus]').forEach(function(btn){
    btn.addEventListener('click', function(){
      const form = btn.closest('form');
      if(!form) return;
      const input = form.querySelector('[data-qty-input]');
      if(!input) return;
      const max = parseInt(input.max || input.dataset.stockMax || '999', 10) || 999;
      let v = parseInt(input.value || '1', 10);
      if (isNaN(v)) v = 1;
      input.value = String(Math.min(max, v + 1));
      clamp(input);
      recalcAll();
      scheduleSubmit(form);
    });
  });

  // manual typing
  document.querySelectorAll('[data-qty-input]').forEach(function(input){
    input.addEventListener('input', function(){
      clamp(input);
      recalcAll();
      const form = input.closest('form');
      scheduleSubmit(form);
    });
    input.addEventListener('blur', function(){
      clamp(input);
      recalcAll();
    });
  });

  // initial calc (ensure formatting consistent)
  recalcAll();
})();
</script>

<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>