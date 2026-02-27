<?php
require_once __DIR__ . '/../config/connectdb.php';
require_once __DIR__ . '/../helpers/functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// หมวดหมู่
$cats = $pdo->query("SELECT c.id, c.name, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c ORDER BY c.name")->fetchAll();

// สินค้าขายดี (อิงยอดขายจาก order_items)
$bestsellersSql = <<<SQL
SELECT p.id, p.name, p.price, p.image, c.name AS category_name,
       COALESCE(SUM(oi.qty), 0) AS sold,
       COALESCE(rv.avg_rating, 0) AS avg_rating,
       COALESCE(rv.review_count, 0) AS review_count
FROM products p
JOIN categories c ON c.id = p.category_id
LEFT JOIN order_items oi ON oi.product_id = p.id
LEFT JOIN (
  SELECT product_id,
         AVG(rating) AS avg_rating,
         COUNT(*) AS review_count
  FROM product_reviews
  GROUP BY product_id
) rv ON rv.product_id = p.id
GROUP BY p.id, p.name, p.price, p.image, c.name, rv.avg_rating, rv.review_count
ORDER BY sold DESC, p.id DESC
LIMIT 8
SQL;
$bestsellers = $pdo->query($bestsellersSql)->fetchAll();

// สินค้าใหม่
$productsSql = <<<SQL
SELECT p.id, p.name, p.price, p.image, c.name AS category_name,
       COALESCE(rv.avg_rating, 0) AS avg_rating,
       COALESCE(rv.review_count, 0) AS review_count
FROM products p
JOIN categories c ON c.id = p.category_id
LEFT JOIN (
  SELECT product_id,
         AVG(rating) AS avg_rating,
         COUNT(*) AS review_count
  FROM product_reviews
  GROUP BY product_id
) rv ON rv.product_id = p.id
ORDER BY p.id DESC
LIMIT 12
SQL;
$products = $pdo->query($productsSql)->fetchAll();

// Top 5 Sidebar
$popularSql = <<<SQL
SELECT p.id, p.name, p.price, COALESCE(SUM(oi.qty), 0) AS sold
FROM products p
LEFT JOIN order_items oi ON oi.product_id = p.id
GROUP BY p.id, p.name, p.price
ORDER BY sold DESC, p.id DESC
LIMIT 5
SQL;
$popular = $pdo->query($popularSql)->fetchAll();

// ค่าสูงสุดสำหรับทำ progress bar
$maxSold = 0;
foreach ($popular as $r) {
  $maxSold = max($maxSold, (int)$r['sold']);
}

include __DIR__ . '/../templates/header.php';
?>

<?php
// ===== Rating helpers =====
function bp_format_rating($avg): string {
  $n = (float)$avg;
  if ($n <= 0) return '0.0';
  return number_format($n, 1);
}

function bp_star_icons($avg): string {
  $n = max(0.0, min(5.0, (float)$avg));
  $full = (int)floor($n);
  $frac = $n - $full;
  $half = ($frac >= 0.25 && $frac < 0.75) ? 1 : 0;
  if ($frac >= 0.75) { $full++; $half = 0; }
  $empty = max(0, 5 - $full - $half);

  $html = '';
  for ($i=0; $i<$full; $i++) $html .= '<i class="bi bi-star-fill"></i>';
  if ($half) $html .= '<i class="bi bi-star-half"></i>';
  for ($i=0; $i<$empty; $i++) $html .= '<i class="bi bi-star"></i>';
  return $html;
}
?>

<?php
// ===== Banner (Admin-managed if table exists) =====
$bannerRows = [];
try {
  $bannerRows = $pdo->query("SELECT id, title, image, link_url FROM site_banners WHERE is_active=1 ORDER BY sort_order ASC, id DESC LIMIT 6")->fetchAll();
} catch (Throwable $e) {
  $bannerRows = [];
}

// fallback banners (static)
$bannerFallback = [
  ['title' => 'BakingProStore', 'image' => BASE_URL . '/assets/img/hero-1.jpg', 'link_url' => ''],
  ['title' => 'สินค้าใหม่และขายดี', 'image' => BASE_URL . '/assets/img/hero-2.jpg', 'link_url' => ''],
  ['title' => 'วัตถุดิบและอุปกรณ์', 'image' => BASE_URL . '/assets/img/hero-3.jpg', 'link_url' => ''],
];

function banner_src($row){
  if (empty($row['image'])) return '';
  // ถ้าเป็น path ในระบบ เช่น uploads/banners/xxx.jpg
  if (str_starts_with($row['image'], 'http://') || str_starts_with($row['image'], 'https://')) return $row['image'];
  return BASE_URL . '/' . ltrim($row['image'], '/');
}

$heroList = !empty($bannerRows) ? array_map(function($r){
  return [
    'title' => $r['title'] ?? 'BakingProStore',
    'image' => banner_src($r),
    'link_url' => $r['link_url'] ?? ''
  ];
}, $bannerRows) : $bannerFallback;
?>

<section class="bp-hero mb-4">
  <div class="card border-0 bp-heroCard">
    <div id="bpHero" class="carousel slide" data-bs-ride="carousel">

      <?php if (count($heroList) > 1): ?>
        <div class="carousel-indicators">
          <?php foreach ($heroList as $i => $_): ?>
            <button type="button" data-bs-target="#bpHero" data-bs-slide-to="<?= $i ?>" class="<?= $i===0?'active':'' ?>" aria-current="<?= $i===0?'true':'false' ?>" aria-label="Slide <?= $i+1 ?>"></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="carousel-inner rounded-4 overflow-hidden">
        <?php foreach ($heroList as $i => $b): ?>
          <?php
            $src = $b['image'] ?? '';
            $title = $b['title'] ?? 'BakingProStore';
            $link = $b['link_url'] ?? '';
          ?>
          <div class="carousel-item <?= $i===0?'active':'' ?>">
            <?php if (!empty($link)): ?><a href="<?= h($link) ?>" class="d-block" style="text-decoration:none;"><?php endif; ?>

            <div class="bp-heroMedia">
              <img class="d-block w-100 bp-heroImg" src="<?= h($src) ?>" alt="<?= h($title) ?>" onerror="this.closest('.bp-heroMedia').style.display='none'">
              <div class="bp-heroOverlay"></div>

              <div class="bp-heroContent">
                <div class="bp-heroBadge">
                  <i class="bi bi-bag-check"></i>
                  <span>ร้านวัตถุดิบและอุปกรณ์เครื่องดื่ม</span>
                </div>
                <h1 class="bp-heroTitle"><?= h($title) ?></h1>
                <div class="row g-3 align-items-end mt-2">
                  <div class="col-12 col-lg-7">
                    <div class="d-flex flex-wrap gap-2">
                      <a class="btn btn-primary bp-btn" href="#section-bestsellers"><i class="bi bi-fire"></i><span>ดูสินค้าขายดี</span></a>
                      <a class="btn btn-outline-light bp-btn" href="#section-new"><i class="bi bi-stars"></i><span>ดูสินค้าใหม่</span></a>
                      <?php if (!empty($_SESSION['user']) && (($_SESSION['user']['role'] ?? '') === 'admin')): ?>
                        <a class="btn btn-outline-light bp-btn" href="<?= BASE_URL ?>/admin/index.php"><i class="bi bi-gear"></i><span>จัดการหลังบ้าน</span></a>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="col-12 col-lg-5">
                    <div class="card border-0 shadow-sm bp-searchCard">
                      <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                          <i class="bi bi-search"></i>
                          <div class="fw-semibold">ค้นหาสินค้า</div>
                        </div>

                        <form class="d-flex gap-2" method="get" action="<?= BASE_URL ?>/public/search.php">
                          <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="q" placeholder="พิมพ์ชื่อสินค้า..." autocomplete="off">
                          </div>
                          <button class="btn btn-primary bp-btn" type="submit"><span>ค้นหา</span></button>
                        </form>

                        <div class="text-muted small mt-2">ตัวอย่าง: แก้ว, ชา, ผงโกโก้</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if (!empty($link)): ?></a><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (count($heroList) > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#bpHero" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#bpHero" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Next</span>
        </button>
      <?php endif; ?>
    </div>

    <?php if (empty($bannerRows)): ?>
      <div class="small text-muted mt-2">
        หากต้องการให้แอดมินเพิ่ม/แก้ไขรูปแบนเนอร์ได้ เดี๋ยวไฟล์ถัดไปเราจะทำตาราง <code>site_banners</code> และหน้า Admin สำหรับจัดการแบนเนอร์
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="row g-4">
  <div class="col-12 col-lg-9">

    <!-- Categories -->
    <section class="card bp-card mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="bp-sectionTitle">
            <i class="bi bi-grid"></i>
            <span>หมวดหมู่สินค้า</span>
          </div>
          <a class="small text-decoration-none" href="<?= BASE_URL ?>/public/index.php">ดูทั้งหมด</a>
        </div>

        <div class="bp-chipRow mt-3">
          <a class="bp-chip bp-chip--all" href="<?= BASE_URL ?>/public/index.php">
            <i class="bi bi-grid"></i>
            <span>ทั้งหมด</span>
          </a>

          <?php foreach($cats as $c): ?>
            <a class="bp-chip" href="<?= BASE_URL ?>/public/category.php?id=<?= (int)$c['id'] ?>" title="<?= h($c['name']) ?>">
              <span class="bp-chip__icon"><i class="bi bi-tag"></i></span>
              <span class="bp-chip__text"><?= h($c['name']) ?></span>
              <span class="bp-chip__count"><?= (int)($c['product_count'] ?? 0) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Bestsellers -->
    <?php if (!empty($bestsellers)): ?>
      <section id="section-bestsellers" class="card bp-card mb-4">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="bp-sectionTitle">
              <i class="bi bi-fire"></i>
              <span>สินค้าขายดี</span>
            </div>
          </div>

          <div class="row g-3">
            <?php foreach($bestsellers as $i => $p): ?>
              <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <div class="card bp-product h-100">
                  <a class="text-decoration-none" href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$p['id'] ?>">
                    <div class="bp-product__media">
                      <?php if(!empty($p['image'])): ?>
                        <img class="bp-product__img" src="<?= BASE_URL . '/' . ltrim(h($p['image']), '/') ?>" alt="<?= h($p['name']) ?>">
                      <?php else: ?>
                        <div class="bp-product__noimg">
                          <i class="bi bi-image"></i>
                          <div>No Image</div>
                        </div>
                      <?php endif; ?>
                      <span class="bp-rank">#<?= (int)($i+1) ?></span>
                    </div>
                  </a>

                  <div class="card-body">
                    <div class="bp-product__title" title="<?= h($p['name']) ?>"><?= h($p['name']) ?></div>
                    <div class="bp-product__meta">
                      <span class="text-muted small"><i class="bi bi-collection me-1"></i><?= h($p['category_name']) ?></span>
                      <span class="text-muted small"><i class="bi bi-bag-check me-1"></i><?= (int)$p['sold'] ?></span>
                      <span class="text-muted small bp-stars" title="คะแนนรีวิว">
                        <i class="bi bi-star me-1"></i>
                        <span class="bp-stars__icons"><?= bp_star_icons($p['avg_rating'] ?? 0) ?></span>
                        <span class="bp-stars__text"><?= bp_format_rating($p['avg_rating'] ?? 0) ?></span>
                        <span class="bp-stars__count">(<?= (int)($p['review_count'] ?? 0) ?>)</span>
                      </span>
                    </div>
                    <div class="bp-product__price"><?= number_format((float)$p['price'], 2) ?> ฿</div>
                  </div>

                  <div class="card-footer bg-transparent border-0 pt-0">
                    <form method="post" action="<?= BASE_URL ?>/public/cart.php">
                      <input type="hidden" name="add_id" value="<?= (int)$p['id'] ?>">
                      <button class="btn btn-primary w-100 bp-btn" type="submit">
                        <i class="bi bi-cart-plus"></i>
                        <span>หยิบลงตะกร้า</span>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <!-- New products -->
    <section id="section-new" class="card bp-card mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <div class="bp-sectionTitle">
            <i class="bi bi-stars"></i>
            <span>สินค้าใหม่</span>
          </div>
        </div>

        <div class="row g-3">
          <?php foreach($products as $p): ?>
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
              <div class="card bp-product h-100">
                <a class="text-decoration-none" href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$p['id'] ?>">
                  <div class="bp-product__media">
                    <?php if(!empty($p['image'])): ?>
                      <img class="bp-product__img" src="<?= BASE_URL . '/' . ltrim(h($p['image']), '/') ?>" alt="<?= h($p['name']) ?>">
                    <?php else: ?>
                      <div class="bp-product__noimg">
                        <i class="bi bi-image"></i>
                        <div>No Image</div>
                      </div>
                    <?php endif; ?>
                    <span class="bp-pill"><i class="bi bi-clock"></i><span>New</span></span>
                  </div>
                </a>

                <div class="card-body">
                  <div class="bp-product__title" title="<?= h($p['name']) ?>"><?= h($p['name']) ?></div>
                  <div class="bp-product__meta">
                    <span class="text-muted small"><i class="bi bi-collection me-1"></i><?= h($p['category_name']) ?></span>
                    <span class="text-muted small bp-stars" title="คะแนนรีวิว">
                      <i class="bi bi-star me-1"></i>
                      <span class="bp-stars__icons"><?= bp_star_icons($p['avg_rating'] ?? 0) ?></span>
                      <span class="bp-stars__text"><?= bp_format_rating($p['avg_rating'] ?? 0) ?></span>
                      <span class="bp-stars__count">(<?= (int)($p['review_count'] ?? 0) ?>)</span>
                    </span>
                  </div>
                  <div class="bp-product__price"><?= number_format((float)$p['price'], 2) ?> ฿</div>
                </div>

                <div class="card-footer bg-transparent border-0 pt-0">
                  <form method="post" action="<?= BASE_URL ?>/public/cart.php">
                    <input type="hidden" name="add_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-outline-primary w-100 bp-btn" type="submit">
                      <i class="bi bi-cart-plus"></i>
                      <span>หยิบลงตะกร้า</span>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

  </div>

  <!-- Sidebar -->
  <div class="col-12 col-lg-3">

    <div class="card bp-card mb-4 bp-sticky">
      <div class="card-body">
        <div class="bp-sectionTitle mb-2">
          <i class="bi bi-graph-up-arrow"></i>
          <span>อันดับขายดี (Top 5)</span>
        </div>

        <?php if (empty($popular)): ?>
          <div class="text-muted small">ยังไม่มีข้อมูลยอดขาย</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach($popular as $i => $row):
              $sold = (int)$row['sold'];
              $pct = ($maxSold > 0) ? (int)round(($sold / $maxSold) * 100) : 0;
            ?>
              <a class="list-group-item list-group-item-action px-0" href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$row['id'] ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div class="me-2" style="min-width:0;">
                    <div class="small fw-semibold text-truncate">#<?= (int)($i+1) ?> <?= h($row['name']) ?></div>
                    <div class="text-muted small"><?= number_format((float)$row['price'], 2) ?> ฿</div>
                  </div>
                  <span class="badge text-bg-light border"><i class="bi bi-bag-check me-1"></i><?= $sold ?></span>
                </div>
                <div class="progress mt-2" style="height:6px;">
                  <div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>


<style>
  .bp-stars{display:inline-flex;align-items:center;gap:.25rem;white-space:nowrap}
  .bp-stars__icons{display:inline-flex;gap:2px;line-height:1}
  .bp-stars__icons .bi{font-size:.9rem}
  .bp-stars__text{font-weight:600}
  .bp-stars__count{opacity:.7}

  /* Category chips */
  .bp-chipRow{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
  .bp-chip{
    display:inline-flex;align-items:center;gap:.5rem;
    padding:.55rem .8rem;
    border-radius:999px;
    text-decoration:none;
    background:#fff;
    border:1px solid rgba(16,24,40,.10);
    box-shadow: 0 6px 18px rgba(16,24,40,.06);
    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
    color:#0b1f3a;
    max-width: 100%;
  }
  .bp-chip:hover{transform: translateY(-1px); border-color: rgba(13,110,253,.28); box-shadow: 0 10px 22px rgba(16,24,40,.10)}
  .bp-chip__icon{
    width:28px;height:28px;border-radius:999px;
    display:inline-flex;align-items:center;justify-content:center;
    background: rgba(13,110,253,.10);
    color:#0d6efd;
    flex: 0 0 auto;
  }
  .bp-chip__text{font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 220px}
  .bp-chip__count{
    margin-left:.15rem;
    padding:.1rem .5rem;
    border-radius:999px;
    font-size:.75rem;
    background: rgba(16,24,40,.06);
    border:1px solid rgba(16,24,40,.08);
    color: rgba(16,24,40,.75);
    flex: 0 0 auto;
  }
  .bp-chip--all .bp-chip__icon{background: rgba(25,135,84,.10); color:#198754}

  /* Mobile: allow horizontal scroll if many categories */
  @media (max-width: 576px){
    .bp-chipRow{flex-wrap:nowrap; overflow:auto; padding-bottom: .25rem}
    .bp-chip{flex: 0 0 auto}
    .bp-chipRow::-webkit-scrollbar{height:8px}
    .bp-chipRow::-webkit-scrollbar-thumb{background: rgba(16,24,40,.12); border-radius:999px}
  }
</style>

<?php include __DIR__ . '/../templates/footer.php'; ?>