<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// helper: เช็คว่ามีตารางไหม (กันหน้าแตกถ้ายังไม่ได้สร้าง)
function table_exists(PDO $pdo, string $table): bool {
  try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

// helper: แสดงดาว (Bootstrap Icons)
function render_stars(float $rating): string {
  $full = (int)floor($rating);
  $half = ($rating - $full) >= 0.5 ? 1 : 0;
  $empty = 5 - $full - $half;

  $html = '';
  for ($i=0; $i<$full; $i++) $html .= '<i class="bi bi-star-fill"></i>';
  if ($half) $html .= '<i class="bi bi-star-half"></i>';
  for ($i=0; $i<$empty; $i++) $html .= '<i class="bi bi-star"></i>';
  return $html;
}

// helper: format price (hide .00 if integer)
function format_price($value): string {
  $n = (float)$value;
  if (!is_finite($n)) $n = 0.0;
  // If it's an integer number, show without decimals
  if (abs($n - round($n)) < 0.00001) {
    return number_format((float)round($n), 0);
  }
  return number_format($n, 2);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  die('ไม่พบสินค้า');
}

// ===== Handle Review POST (PRG) =====
$hasReviewsTable = table_exists($pdo, 'product_reviews');
$isLoggedIn = !empty($_SESSION['user']);
$meId = (int)($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
  if (!$isLoggedIn || $meId <= 0) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
  }

  if (!$hasReviewsTable) {
    header('Location: ' . BASE_URL . '/public/product.php?id=' . $id . '&err=review_table_missing');
    exit;
  }

  $rating = (int)($_POST['rating'] ?? 0);
  $comment = trim($_POST['comment'] ?? '');

  if ($rating < 1 || $rating > 5) {
    header('Location: ' . BASE_URL . '/public/product.php?id=' . $id . '&err=invalid_rating');
    exit;
  }

  if ($comment === '' || mb_strlen($comment) > 255) {
    header('Location: ' . BASE_URL . '/public/product.php?id=' . $id . '&err=invalid_comment');
    exit;
  }

  try {
    // 1 คน 1 รีวิวต่อสินค้า: ถ้ามีแล้วให้อัปเดตแทน
    $chk = $pdo->prepare('SELECT id FROM product_reviews WHERE product_id=? AND user_id=? LIMIT 1');
    $chk->execute([$id, $meId]);
    $existing = $chk->fetch();

    if ($existing) {
      $up = $pdo->prepare('UPDATE product_reviews SET rating=?, comment=?, created_at=NOW(), is_approved=1 WHERE id=?');
      $up->execute([$rating, $comment, (int)$existing['id']]);
    } else {
      $ins = $pdo->prepare('INSERT INTO product_reviews (product_id, user_id, rating, comment, is_approved) VALUES (?,?,?,?,1)');
      $ins->execute([$id, $meId, $rating, $comment]);
    }

    header('Location: ' . BASE_URL . '/public/product.php?id=' . $id . '&ok=review_saved');
    exit;
  } catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/public/product.php?id=' . $id . '&err=review_failed');
    exit;
  }
}

// ===== Fetch Product =====
$stmt = $pdo->prepare("
  SELECT p.*, c.name AS category_name
  FROM products p
  JOIN categories c ON c.id = p.category_id
  WHERE p.id = ?
  LIMIT 1
");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) die('ไม่พบสินค้า');
if (!isset($p['price']) || $p['price'] === null || $p['price'] === '') {
  $p['price'] = 0;
}

// ===== Review Summary + List =====
$avgRating = 0.0;
$reviewCount = 0;
$reviews = [];
$myReview = null;
$ratingDist = [1=>0,2=>0,3=>0,4=>0,5=>0];

if ($hasReviewsTable) {
  // summary
  $sum = $pdo->prepare("
    SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt
    FROM product_reviews
    WHERE product_id=? AND is_approved=1
  ");
  $sum->execute([$id]);
  $row = $sum->fetch();
  if ($row) {
    $avgRating = (float)($row['avg_rating'] ?? 0);
    $reviewCount = (int)($row['cnt'] ?? 0);
  }

  // distribution (1-5)
  $dist = $pdo->prepare("
    SELECT rating, COUNT(*) AS cnt
    FROM product_reviews
    WHERE product_id=? AND is_approved=1
    GROUP BY rating
  ");
  $dist->execute([$id]);
  foreach ($dist->fetchAll() as $d) {
    $r = (int)($d['rating'] ?? 0);
    if ($r >= 1 && $r <= 5) $ratingDist[$r] = (int)$d['cnt'];
  }

  // list
  $list = $pdo->prepare("
    SELECT pr.id, pr.rating, pr.comment, pr.created_at,
           u.name AS user_name
    FROM product_reviews pr
    JOIN users u ON u.id = pr.user_id
    WHERE pr.product_id=? AND pr.is_approved=1
    ORDER BY pr.created_at DESC
    LIMIT 20
  ");
  $list->execute([$id]);
  $reviews = $list->fetchAll();

  // my review
  if ($isLoggedIn && $meId > 0) {
    $mine = $pdo->prepare('SELECT rating, comment FROM product_reviews WHERE product_id=? AND user_id=? LIMIT 1');
    $mine->execute([$id, $meId]);
    $myReview = $mine->fetch();
  }
}

$ok = $_GET['ok'] ?? '';
$err = $_GET['err'] ?? '';

include __DIR__ . '/../templates/header.php';
?>

<style>
  .thumb-strip{
    display:flex;
    gap:.5rem;
    overflow:auto;
    padding-bottom:.25rem;
    scroll-snap-type:x mandatory;
    -webkit-overflow-scrolling: touch;
  }
  .thumb-strip::-webkit-scrollbar{ height:8px; }
  .thumb-strip::-webkit-scrollbar-thumb{ background:rgba(0,0,0,.12); border-radius:999px; }
  .thumb-btn{ border:1px solid rgba(0,0,0,.08); background:#fff; padding:0; border-radius:.5rem; overflow:hidden; flex:0 0 auto; width:74px; height:56px; scroll-snap-align:start; }
  .thumb-btn img{ width:100%; height:100%; object-fit:cover; display:block; }
  .thumb-btn.active{ outline:2px solid rgba(13,110,253,.55); border-color:rgba(13,110,253,.35); }
  .gallery-main{ cursor: zoom-in; }
  .zoom-img{ width:100%; height:auto; max-height:80vh; object-fit:contain; background:#000; }
</style>

<?php
// ===== Related + Best sellers (for extra section) =====
$related = [];
$bestsellers = [];

try {
  // Related products (same category)
  $rel = $pdo->prepare("
    SELECT p.id, p.name, p.price, p.image, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.category_id = ? AND p.id <> ?
    ORDER BY p.id DESC
    LIMIT 6
  ");
  $rel->execute([(int)$p['category_id'], $id]);
  $related = $rel->fetchAll();

  // Bestsellers (by order_items)
  $best = $pdo->query("
    SELECT p.id, p.name, p.price, p.image, COALESCE(SUM(oi.qty),0) AS sold
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id = p.id
    GROUP BY p.id, p.name, p.price, p.image
    ORDER BY sold DESC, p.id DESC
    LIMIT 6
  ");
  $bestsellers = $best->fetchAll();
} catch (Throwable $e) {
  // ignore (e.g. order_items not ready)
}

// Stock and max qty
$stock = (int)($p['stock'] ?? 0);
$maxQty = max(1, $stock > 0 ? $stock : 1);

// ===== Gallery images (main + multi images) =====
$galleryImages = [];

// 1) main image first
if (!empty($p['image'])) {
  $main = trim((string)$p['image']);
  if ($main !== '') $galleryImages[] = $main;
}

// 2) extra images from product_images
if (table_exists($pdo, 'product_images')) {
  try {
    $gi = $pdo->prepare("SELECT image FROM product_images WHERE product_id=? ORDER BY sort_order ASC, id ASC");
    $gi->execute([$id]);
    foreach ($gi->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $img = trim((string)($r['image'] ?? ''));
      if ($img !== '') $galleryImages[] = $img;
    }
  } catch (Throwable $e) {
    // ignore
  }
}

// 3) unique (keep order)
$seen = [];
$galleryImages = array_values(array_filter($galleryImages, function($path) use (&$seen) {
  $k = ltrim((string)$path, '/');
  if ($k === '' || isset($seen[$k])) return false;
  $seen[$k] = true;
  return true;
}));
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= BASE_URL ?>/public/index.php">หน้าร้าน</a></li>
    <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= BASE_URL ?>/public/category.php?id=<?= (int)$p['category_id'] ?>"><?= h($p['category_name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= h($p['name']) ?></li>
  </ol>
</nav>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-0"><?= h($p['name']) ?></h2>
    <div class="text-muted small">หมวดหมู่: <?= h($p['category_name']) ?></div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/public/index.php"><i class="bi bi-arrow-left me-1"></i>กลับหน้าร้าน</a>
  </div>
</div>

<?php if ($ok === 'review_saved'): ?>
  <div class="alert alert-success py-2">บันทึกรีวิวเรียบร้อย</div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="alert alert-warning py-2">
    <?php
      $map = [
        'review_table_missing' => 'ยังไม่ได้สร้างตารางรีวิว (product_reviews)',
        'invalid_rating' => 'กรุณาให้คะแนน 1-5 ดาว',
        'invalid_comment' => 'กรุณาใส่ข้อความรีวิว (ไม่เกิน 255 ตัวอักษร)',
        'review_failed' => 'บันทึกรีวิวไม่สำเร็จ ลองใหม่อีกครั้ง',
      ];
      echo h($map[$err] ?? 'เกิดข้อผิดพลาด');
    ?>
  </div>
<?php endif; ?>

<div class="row g-3">
  <!-- Gallery -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="bg-light" style="border-top-left-radius:.5rem;border-top-right-radius:.5rem;">
        <?php if (!empty($galleryImages)): ?>
          <div id="productGallery" class="carousel slide" data-bs-ride="false" data-bs-interval="false" data-bs-touch="true">
<?php if (count($galleryImages) > 1): ?>
  <div class="carousel-indicators" style="margin-bottom:.25rem;">
    <?php foreach ($galleryImages as $i => $_): ?>
      <button type="button"
              data-bs-target="#productGallery"
              data-bs-slide-to="<?= (int)$i ?>"
              class="<?= ((int)$i === 0) ? 'active' : '' ?>"
              aria-current="<?= ((int)$i === 0) ? 'true' : 'false' ?>"
              aria-label="รูปที่ <?= (int)($i+1) ?>"></button>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
            <div class="carousel-inner">
              <?php foreach ($galleryImages as $idx => $imgPath): ?>
                <div class="carousel-item <?= ($idx === 0) ? 'active' : '' ?>">
                  <div class="ratio ratio-4x3">
                    <img
                      src="<?= BASE_URL . '/' . ltrim(h($imgPath), '/') ?>"
                      class="w-100 h-100 gallery-main"
                      style="object-fit:cover;"
                      alt="<?= h($p['name']) ?>"
                      loading="lazy"
                      data-bs-toggle="modal"
                      data-bs-target="#zoomModal"
                      data-zoom-src="<?= BASE_URL . '/' . ltrim(h($imgPath), '/') ?>"
                    >
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if (count($galleryImages) > 1): ?>
              <button class="carousel-control-prev" type="button" data-bs-target="#productGallery" data-bs-slide="prev" aria-label="รูปก่อนหน้า">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#productGallery" data-bs-slide="next" aria-label="รูปถัดไป">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
              </button>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="ratio ratio-4x3 d-flex align-items-center justify-content-center">
            <div class="text-center text-muted">
              <i class="bi bi-image" style="font-size:2rem"></i>
              <div class="small mt-2">No Image</div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="card-body">
        <?php if (count($galleryImages) > 1): ?>
          <div class="thumb-strip mb-2" aria-label="รูปสินค้าเพิ่มเติม">
            <?php foreach ($galleryImages as $idx => $imgPath): ?>
              <button
                class="thumb-btn <?= ($idx === 0) ? 'active' : '' ?>"
                type="button"
                data-bs-target="#productGallery"
                data-bs-slide-to="<?= (int)$idx ?>"
                data-idx="<?= (int)$idx ?>"
                aria-label="ดูรูปที่ <?= (int)($idx+1) ?>"
              >
                <img src="<?= BASE_URL . '/' . ltrim(h($imgPath), '/') ?>" alt="">
              </button>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
        <?php endif; ?>

        <hr class="my-3">

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="small text-muted">
            รหัสสินค้า: #<?= (int)$p['id'] ?>
          </div>
          <div class="d-flex align-items-center gap-2">
            <?php if ($stock > 0): ?>
              <span class="badge text-bg-success">พร้อมส่ง</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">สินค้าหมด</span>
            <?php endif; ?>
            <?php if ($hasReviewsTable): ?>
              <span class="badge text-bg-light border">
                <span class="text-warning bs-stars"><?= render_stars($avgRating) ?></span>
                <span class="ms-1 small text-muted"><?= number_format($avgRating, 1) ?></span>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Zoom Modal -->
    <div class="modal fade" id="zoomModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark">
          <div class="modal-header border-0">
            <div class="text-white small">ซูมรูปสินค้า</div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0">
            <img id="zoomImg" class="zoom-img" src="" alt="">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Purchase panel -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-3">
          <div class="flex-grow-1">
            <div class="text-muted small">ราคา</div>
            <div class="display-6 fw-semibold lh-1 mb-2" style="font-size:2rem;">
              <?= format_price($p['price']) ?> <span class="fs-6 fw-normal text-muted">฿</span>
            </div>

            <?php if ($hasReviewsTable): ?>
              <div class="d-flex align-items-center gap-2 mb-2">
                <div class="text-warning bs-stars"><?= render_stars($avgRating) ?></div>
                <div class="small text-muted"><?= number_format($avgRating, 1) ?> (<?= (int)$reviewCount ?> รีวิว)</div>
              </div>
            <?php endif; ?>

            <div class="small text-muted">คงเหลือ: <span class="fw-semibold"><?= $stock ?></span></div>
          </div>

          <div class="text-end">
            <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/public/cart.php">
              <i class="bi bi-bag me-1"></i>ไปที่ตะกร้า
            </a>
          </div>
        </div>

        <hr>

        <form class="row g-2" method="post" action="<?= BASE_URL ?>/public/cart.php">
          <input type="hidden" name="add_id" value="<?= (int)$p['id'] ?>">

          <div class="col-12">
            <label class="form-label mb-1 small text-muted">จำนวน</label>
            <div class="input-group">
              <button class="btn btn-outline-secondary" type="button" id="qtyMinus" aria-label="ลดจำนวน"><i class="bi bi-dash"></i></button>
              <input
                class="form-control text-center"
                type="number"
                id="qtyInput"
                name="qty"
                value="1"
                min="1"
                max="<?= $maxQty ?>"
              >
              <button class="btn btn-outline-secondary" type="button" id="qtyPlus" aria-label="เพิ่มจำนวน"><i class="bi bi-plus"></i></button>
            </div>
            <div class="form-text">สูงสุด <?= $maxQty ?> ชิ้น</div>
          </div>

          <div class="col-12">
            <button class="btn btn-primary w-100" type="submit" <?= ($stock <= 0) ? 'disabled' : '' ?> >
              <i class="bi bi-cart-plus me-1"></i>หยิบลงตะกร้า
            </button>
          </div>

          <?php if ($stock <= 0): ?>
            <div class="col-12">
              <div class="alert alert-secondary py-2 mb-0">สินค้าหมด</div>
            </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Tabs: Details / Reviews -->
<div class="card shadow-sm mt-3">
  <div class="card-header bg-white">
    <ul class="nav nav-tabs card-header-tabs" id="productTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-detail" data-bs-toggle="tab" data-bs-target="#pane-detail" type="button" role="tab">
          <i class="bi bi-card-text me-1"></i>รายละเอียด
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-review" data-bs-toggle="tab" data-bs-target="#pane-review" type="button" role="tab">
          <i class="bi bi-chat-square-text me-1"></i>รีวิว
          <?php if ($hasReviewsTable): ?><span class="badge text-bg-light border ms-1"><?= (int)$reviewCount ?></span><?php endif; ?>
        </button>
      </li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content" id="productTabsContent">
      <div class="tab-pane fade show active" id="pane-detail" role="tabpanel" aria-labelledby="tab-detail">
        <?php if (!empty($p['description'])): ?>
          <div style="white-space:pre-line;" class="small"><?= h($p['description']) ?></div>
        <?php else: ?>
          <div class="text-muted small">ยังไม่มีรายละเอียดเพิ่มเติม</div>
        <?php endif; ?>
      </div>

      <div class="tab-pane fade" id="pane-review" role="tabpanel" aria-labelledby="tab-review">
<div class="row g-3">
  <!-- Summary -->
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="fw-semibold">คะแนนจากผู้ใช้งาน</div>
          <?php if ($hasReviewsTable): ?>
            <span class="badge rounded-pill rating-chip px-3 py-2">
              <span class="text-warning bs-stars"><?= render_stars($avgRating) ?></span>
              <span class="ms-1 small text-muted"><?= number_format($avgRating, 1) ?></span>
            </span>
          <?php endif; ?>
        </div>

        <?php if (!$hasReviewsTable): ?>
          <div class="alert alert-warning py-2 mt-3 mb-0">ยังไม่ได้สร้างตารางรีวิว (product_reviews)</div>
        <?php else: ?>
          <div class="mt-3">
            <div class="d-flex align-items-end justify-content-between">
              <div>
                <div class="display-6 fw-semibold" style="font-size:2.25rem; line-height:1;">
                  <?= number_format($avgRating, 1) ?>
                </div>
                <div class="review-meta">จากทั้งหมด <?= (int)$reviewCount ?> รีวิว</div>
              </div>
              <div class="text-end">
                <div class="review-meta">เต็ม 5 คะแนน</div>
              </div>
            </div>

            <div class="mt-3">
              <?php
                for ($r = 5; $r >= 1; $r--) {
                  $cnt = (int)($ratingDist[$r] ?? 0);
                  $pct = ($reviewCount > 0) ? round(($cnt / $reviewCount) * 100) : 0;
              ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <div class="text-nowrap" style="width:56px;">
                    <span class="small fw-semibold"><?= $r ?></span>
                    <i class="bi bi-star-fill text-warning"></i>
                  </div>
                  <div class="progress flex-grow-1" style="height:10px;">
                    <div class="progress-bar" role="progressbar" style="width: <?= (int)$pct ?>%" aria-valuenow="<?= (int)$pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <div class="text-nowrap review-meta" style="width:56px; text-align:right;"><?= (int)$cnt ?></div>
                </div>
              <?php } ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($hasReviewsTable): ?>
      <div class="card shadow-sm mt-3">
        <div class="card-body">
          <?php if (!$isLoggedIn): ?>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div class="small text-muted">ต้องเข้าสู่ระบบก่อนจึงจะรีวิวได้</div>
              <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/public/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a>
            </div>
          <?php else: ?>
            <div class="fw-semibold mb-2">เขียนรีวิวของคุณ</div>
            <form method="post" class="vstack gap-2">
              <input type="hidden" name="action" value="review">

              <div>
                <label class="form-label mb-1">ให้คะแนน</label>
                <?php $selectedRating = (int)($myReview['rating'] ?? 5); ?>
                <div class="star-input" aria-label="ให้คะแนน 1 ถึง 5 ดาว">
                  <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" id="rate<?= $i ?>" name="rating" value="<?= $i ?>" <?= ($selectedRating === $i) ? 'checked' : '' ?> required>
                    <label for="rate<?= $i ?>" title="<?= $i ?> ดาว"><i class="bi bi-star-fill"></i></label>
                  <?php endfor; ?>
                </div>
                <div class="form-text">คลิกเลือกดาวได้เลย</div>
              </div>

              <div>
                <label class="form-label mb-1">ความคิดเห็น</label>
                <textarea class="form-control" name="comment" rows="3" maxlength="255" placeholder="พิมพ์ความคิดเห็นสั้น ๆ (ไม่เกิน 255 ตัวอักษร)" required><?= h($myReview['comment'] ?? '') ?></textarea>
              </div>

              <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-primary" type="submit">
                  <i class="bi bi-send me-1"></i><?= $myReview ? 'อัปเดตรีวิว' : 'ส่งรีวิว' ?>
                </button>
                <?php if ($myReview): ?>
                  <span class="small text-muted">คุณเคยรีวิวแล้ว สามารถแก้ไขได้</span>
                <?php endif; ?>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- List -->
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-chat-square-text me-1"></i>รีวิวล่าสุด</div>
        <?php if ($hasReviewsTable): ?>
          <div class="small text-muted">ทั้งหมด <?= (int)$reviewCount ?> รีวิว</div>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (!$hasReviewsTable): ?>
          <div class="text-muted small">ยังไม่พร้อมใช้งาน (ยังไม่ได้สร้างตารางรีวิว)</div>
        <?php else: ?>
          <?php if (empty($reviews)): ?>
            <div class="text-muted small">ยังไม่มีรีวิว</div>
          <?php else: ?>
            <div class="vstack gap-3">
              <?php foreach($reviews as $r): ?>
                <div class="review-card rounded-3 p-3">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                      <div class="fw-semibold mb-1"><?= h($r['user_name'] ?? 'User') ?></div>
                      <div class="text-warning bs-stars"><?= render_stars((float)$r['rating']) ?></div>
                    </div>
                    <div class="review-meta"><?= h($r['created_at']) ?></div>
                  </div>
                  <div class="mt-2 small" style="white-space:pre-line;">
                    <?= h($r['comment']) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
    </div>
  </div>
</div>

<!-- More products -->
<div class="row g-3 mt-1">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-fire me-1"></i>สินค้าขายดี</div>
        <a class="small text-decoration-none" href="<?= BASE_URL ?>/public/index.php">ดูหน้าร้าน</a>
      </div>
      <div class="card-body">
        <?php if (empty($bestsellers)): ?>
          <div class="text-muted small">ยังไม่มีข้อมูลยอดขาย</div>
        <?php else: ?>
          <div class="row g-2">
            <?php foreach ($bestsellers as $bx): ?>
              <div class="col-12 col-md-6">
                <a class="text-decoration-none" href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$bx['id'] ?>">
                  <div class="border rounded-3 p-2 h-100 d-flex gap-2 align-items-center">
                    <div class="bg-light rounded-2" style="width:52px;height:52px;overflow:hidden;flex:0 0 auto;">
                      <?php if (!empty($bx['image'])): ?>
                        <img src="<?= BASE_URL . '/' . ltrim(h($bx['image']), '/') ?>" alt="" style="width:100%;height:100%;object-fit:cover" loading="lazy">
                      <?php else: ?>
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>
                      <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                      <div class="text-dark small fw-semibold text-truncate"><?= h($bx['name']) ?></div>
                      <div class="text-muted small"><?= format_price($bx['price']) ?> ฿</div>
                    </div>
                    <div class="small text-muted"><i class="bi bi-cart-check me-1"></i><?= (int)($bx['sold'] ?? 0) ?></div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-grid me-1"></i>สินค้าใกล้เคียง</div>
        <a class="small text-decoration-none" href="<?= BASE_URL ?>/public/category.php?id=<?= (int)$p['category_id'] ?>">ดูในหมวดหมู่</a>
      </div>
      <div class="card-body">
        <?php if (empty($related)): ?>
          <div class="text-muted small">ยังไม่มีสินค้าอื่นในหมวดนี้</div>
        <?php else: ?>
          <div class="row g-2">
            <?php foreach ($related as $rx): ?>
              <div class="col-12 col-md-6">
                <a class="text-decoration-none" href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$rx['id'] ?>">
                  <div class="border rounded-3 p-2 h-100 d-flex gap-2 align-items-center">
                    <div class="bg-light rounded-2" style="width:52px;height:52px;overflow:hidden;flex:0 0 auto;">
                      <?php if (!empty($rx['image'])): ?>
                        <img src="<?= BASE_URL . '/' . ltrim(h($rx['image']), '/') ?>" alt="" style="width:100%;height:100%;object-fit:cover" loading="lazy">
                      <?php else: ?>
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>
                      <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                      <div class="text-dark small fw-semibold text-truncate"><?= h($rx['name']) ?></div>
                      <div class="text-muted small"><?= format_price($rx['price']) ?> ฿</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // qty controls
  const input = document.getElementById('qtyInput');
  const minus = document.getElementById('qtyMinus');
  const plus = document.getElementById('qtyPlus');

  function clamp(val){
    const min = parseInt((input && input.min) || '1', 10);
    const max = parseInt((input && input.max) || '9999', 10);
    val = parseInt(val || '1', 10);
    if (isNaN(val)) val = min;
    return Math.max(min, Math.min(max, val));
  }

  if(input && minus && plus){
    minus.addEventListener('click', function(){
      input.value = clamp(parseInt(input.value || '1', 10) - 1);
    });
    plus.addEventListener('click', function(){
      input.value = clamp(parseInt(input.value || '1', 10) + 1);
    });
    input.addEventListener('change', function(){
      input.value = clamp(input.value);
    });
  }

  // gallery: thumbnail active state + click-to-slide fallback
  const galleryEl = document.getElementById('productGallery');
  const thumbs = Array.from(document.querySelectorAll('.thumb-btn'));

  if (galleryEl && window.bootstrap) {
    // Ensure a Carousel instance exists
    const carousel = bootstrap.Carousel.getOrCreateInstance(galleryEl, {
      interval: false,
      ride: false,
      touch: true,
      pause: true,
      wrap: true
    });

    // Keep active thumb in sync after sliding
    galleryEl.addEventListener('slid.bs.carousel', function(ev){
      const idx = (typeof ev.to === 'number') ? ev.to : 0;
      thumbs.forEach((b, i) => b.classList.toggle('active', i === idx));

      // auto-scroll the active thumb into view
      const active = thumbs[idx];
      if (active && typeof active.scrollIntoView === 'function') {
        try { active.scrollIntoView({behavior:'smooth', inline:'center', block:'nearest'}); } catch(e) {}
      }
    });

    // Some themes block data-bs-slide-to clicks; add explicit click handler
    thumbs.forEach((btn) => {
      btn.addEventListener('click', function(){
        const idx = parseInt(this.getAttribute('data-idx') || '0', 10);
        if (!isNaN(idx)) carousel.to(idx);
      });
    });

    // Horizontal scroll with mouse wheel (nice on desktop)
    const strip = document.querySelector('.thumb-strip');
    if (strip) {
      strip.addEventListener('wheel', function(e){
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
          this.scrollLeft += e.deltaY;
          e.preventDefault();
        }
      }, { passive: false });
    }
  }

  // zoom modal: set image src
  const zoomModal = document.getElementById('zoomModal');
  const zoomImg = document.getElementById('zoomImg');
  if (zoomModal && zoomImg) {
    zoomModal.addEventListener('show.bs.modal', function(event){
      const trigger = event.relatedTarget;
      const src = trigger && trigger.getAttribute('data-zoom-src');
      if (src) zoomImg.src = src;
    });
    zoomModal.addEventListener('hidden.bs.modal', function(){
      zoomImg.src = '';
    });
  }
})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>