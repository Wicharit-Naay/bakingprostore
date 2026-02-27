<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';
// ให้เมนูรู้สถานะล็อกอินเสมอ
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$isLoggedIn = !empty($_SESSION['user']);
$userName   = $isLoggedIn ? (string)($_SESSION['user']['name'] ?? '') : '';
$role       = $isLoggedIn ? (string)($_SESSION['user']['role'] ?? '') : '';

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  $cartCount = array_sum(array_map('intval', $_SESSION['cart']));
}

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isActive = function (string $needle) use ($currentPath): bool {
  return $needle !== '' && str_contains($currentPath, $needle);
};

// ใช้กับ title (แต่ละหน้า override ได้)
$pageTitle = $pageTitle ?? 'BakingProStore';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Bootstrap + Icons (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
  <div class="container">

    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/public/index.php" aria-label="BakingProStore">
      <span class="d-inline-flex align-items-center justify-content-center rounded-circle border bg-white" style="width:40px;height:40px;overflow:hidden;">
        <img
          src="<?= BASE_URL ?>/assets/img/logo1.png"
          alt="BakingProStore"
          width="36"
          height="36"
          style="object-fit:contain"
          onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';"
        >
        <i class="bi bi-bag-heart" style="display:none;font-size:20px;"></i>
      </span>
      <span class="fw-semibold">BakingProStore</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= $isActive('/public/index.php') ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/public/index.php">
            <i class="bi bi-shop me-1"></i>หน้าร้าน
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= ($isActive('/public/category.php') || $isActive('/public/search.php') || $isActive('/public/product.php')) ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/public/index.php#products">
            <i class="bi bi-grid-3x3-gap me-1"></i>สินค้า
          </a>
        </li>

        <?php if ($isLoggedIn): ?>
          <li class="nav-item">
            <a class="nav-link <?= ($isActive('/public/orders.php') || $isActive('/public/order_status.php')) ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/public/orders.php">
              <i class="bi bi-receipt me-1"></i>ออเดอร์ของฉัน
            </a>
          </li>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/admin/index.php">
              <i class="bi bi-speedometer2 me-1"></i>หลังร้าน
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- Right side -->
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm position-relative" href="<?= BASE_URL ?>/public/cart.php">
          <i class="bi bi-cart3 me-1"></i>ตะกร้า
          <?php if ($cartCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
              <?= (int)$cartCount ?>
              <span class="visually-hidden">items in cart</span>
            </span>
          <?php endif; ?>
        </a>

        <?php if ($isLoggedIn): ?>
          <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($userName !== '' ? $userName : 'บัญชีของฉัน', ENT_QUOTES, 'UTF-8') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/profile.php"><i class="bi bi-person me-2"></i>โปรไฟล์</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/orders.php"><i class="bi bi-receipt me-2"></i>ออเดอร์ของฉัน</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/public/logout.php"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/public/register.php"><i class="bi bi-person-plus me-1"></i>สมัครสมาชิก</a>
          <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/public/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<main class="container py-4">