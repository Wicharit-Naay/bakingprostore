<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// page title (optional per-page)
$pageTitle = $pageTitle ?? 'Admin - BakingProStore';

// current path for active menu
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isActive = function(string $needle) use ($currentPath) {
  return $needle !== '' && str_contains($currentPath, $needle);
};

// user display
$adminName = '';
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
  $adminName = (string)($_SESSION['user']['name'] ?? ($_SESSION['user']['email'] ?? 'Admin'));
}
if ($adminName === '') $adminName = 'Administrator';
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

  <!-- Custom CSS (ธีมรวมของเว็บ) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">

  <!-- jQuery (เผื่อหน้าไหนใช้ DataTables) -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    :root{
      --admin-sidebar-w: 280px;
      --admin-sidebar-bg: #0b1f3a;
      --admin-sidebar-bg2:#08172d;
      --admin-sidebar-text:#e9f1ff;
      --admin-sidebar-muted: rgba(233,241,255,.7);
      --admin-card-radius: 18px;
    }

    body.admin-body{ background:#f6f8fb; }

    /* layout */
    .admin-shell{ min-height:100vh; display:flex; }
    .admin-sidebar{
      width: var(--admin-sidebar-w);
      background: linear-gradient(180deg, var(--admin-sidebar-bg) 0%, var(--admin-sidebar-bg2) 100%);
      color: var(--admin-sidebar-text);
      position: sticky;
      top:0;
      height:100vh;
      flex: 0 0 auto;
      padding: 18px 14px;
      border-right: 1px solid rgba(255,255,255,.08);
    }
    .admin-brand{
      display:flex;
      align-items:center;
      gap:10px;
      padding: 10px 10px;
      border-radius: 14px;
    }
    .admin-brand__logo{
      width:40px;
      height:40px;
      border-radius: 12px;
      background: rgba(255,255,255,.08);
      display:flex;
      align-items:center;
      justify-content:center;
      overflow:hidden;
      border: 1px solid rgba(255,255,255,.12);
    }
    .admin-brand__title{ line-height:1.1; }
    .admin-brand__title b{ display:block; font-size: 16px; }
    .admin-brand__title span{ display:block; font-size: 12px; color: var(--admin-sidebar-muted); }

    .admin-nav{ margin-top: 14px; }
    .admin-nav .nav-link{
      color: var(--admin-sidebar-text);
      border-radius: 14px;
      padding: 10px 12px;
      display:flex;
      align-items:center;
      justify-content:flex-start;
      gap:10px;
      margin: 4px 6px;
      width:100%;
      text-align:left;
      opacity: .92;
    }
    .admin-nav .nav-link:hover{ background: rgba(255,255,255,.10); opacity: 1; }
    .admin-nav .nav-link.active{
      background: rgba(56, 139, 253, .18);
      border: 1px solid rgba(56, 139, 253, .35);
      opacity: 1;
      font-weight: 600;
    }
    .admin-nav .nav-link i{ font-size: 18px; width: 20px; text-align:center; }

    /* finance submenu look */
    .admin-nav .collapse .nav-link{
      margin: 2px 6px;
      padding: 9px 12px;
      opacity: .9;
    }
    .admin-nav .collapse .nav-link i{ font-size: 16px; }
    .admin-nav .collapse{ margin-top: 4px; }

    /* finance toggle button behaves like a link */
    .admin-nav button.nav-link{ cursor:pointer; }

    /* rotate chevron when expanded */
    .admin-nav button.nav-link[aria-expanded="true"] .bi-chevron-down{ transform: rotate(180deg); transition: .18s ease; }
    .admin-nav button.nav-link[aria-expanded="false"] .bi-chevron-down{ transform: rotate(0deg); transition: .18s ease; }

    .admin-sidebar__footer{
      position:absolute;
      left:14px;
      right:14px;
      bottom: 14px;
    }
    .admin-logout{
      width:100%;
      border-radius: 14px;
      padding: 10px 12px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.08);
      color: var(--admin-sidebar-text);
    }
    .admin-logout:hover{ background: rgba(255,255,255,.12); }

    .admin-main{ flex: 1 1 auto; min-width: 0; }

    .admin-topbar{
      position: sticky;
      top: 0;
      z-index: 1030;
      background: rgba(246,248,251,.85);
      backdrop-filter: saturate(180%) blur(12px);
      border-bottom: 1px solid rgba(16,24,40,.06);
    }
    .admin-topbar__inner{
      padding: 14px 18px;
      display:flex;
      align-items:center;
      gap: 12px;
    }

    .admin-search{
      max-width: 520px;
      width: 100%;
    }
    .admin-search .input-group-text{ background:#fff; }

    .admin-user{
      margin-left:auto;
      display:flex;
      align-items:center;
      gap: 10px;
    }

    .admin-user__pill{
      display:flex;
      align-items:center;
      gap:10px;
      background:#fff;
      border: 1px solid rgba(16,24,40,.08);
      border-radius: 999px;
      padding: 6px 10px;
    }

    .admin-user__avatar{
      width:36px;
      height:36px;
      border-radius: 999px;
      display:flex;
      align-items:center;
      justify-content:center;
      background: rgba(13,110,253,.12);
      border: 1px solid rgba(13,110,253,.18);
      color: #0d6efd;
      flex: 0 0 auto;
    }

    /* content container */
    .admin-content{ padding: 18px; }
    .admin-card{ border-radius: var(--admin-card-radius); }

    /* responsive */
    @media (max-width: 992px){
      .admin-sidebar{ position: fixed; left: 0; top: 0; transform: translateX(-105%); transition: .2s ease; z-index: 1040; }
      .admin-sidebar.show{ transform: translateX(0); }
      .admin-sidebar__footer{ position: static; margin-top: 18px; }
    }
  </style>
</head>
<body class="admin-body">
<div class="admin-shell">
  <!-- SIDEBAR -->
  <aside class="admin-sidebar" id="adminSidebar">
    <a class="text-decoration-none admin-brand" href="<?= BASE_URL ?>/admin/index.php">
      <span class="admin-brand__logo">
        <img src="<?= BASE_URL ?>/assets/img/mbs.png" alt="BakingProStore" width="34" height="34" style="object-fit:contain" onerror="this.style.display='none'">
        <i class="bi bi-speedometer2" style="display:none"></i>
      </span>
      <span class="admin-brand__title">
        <b>MBS Admin</b>
        <span>BakingProStore</span>
      </span>
    </a>

    <nav class="admin-nav nav flex-column">
      <a class="nav-link <?= $isActive('/admin/index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/index.php">
        <i class="bi bi-house"></i> <span>Home</span>
      </a>
      <a class="nav-link <?= $isActive('/admin/products/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/products/index.php">
        <i class="bi bi-box-seam"></i> <span>สินค้า</span>
      </a>
      <a class="nav-link <?= $isActive('/admin/categories/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/categories/index.php">
        <i class="bi bi-tags"></i> <span>ประเภท</span>
      </a>
      <a class="nav-link <?= $isActive('/admin/customers/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/customers/index.php">
        <i class="bi bi-people"></i> <span>ลูกค้า</span>
      </a>
      <a class="nav-link <?= $isActive('/admin/orders/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/orders/index.php">
        <i class="bi bi-receipt"></i> <span>ออเดอร์</span>
      </a>
      <?php $financeOpen = $isActive('/admin/finance/'); ?>
      <button
        class="nav-link w-100 text-start d-flex justify-content-between align-items-center <?= $financeOpen ? 'active' : '' ?>"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#financeMenu"
        aria-expanded="<?= $financeOpen ? 'true' : 'false' ?>"
        aria-controls="financeMenu"
        style="background:transparent;border:0;">
        <span class="d-flex align-items-center gap-2">
          <i class="bi bi-cash-coin"></i> <span>การเงิน</span>
        </span>
        <i class="bi bi-chevron-down small" style="opacity:.8"></i>
      </button>

      <div class="collapse <?= $financeOpen ? 'show' : '' ?>" id="financeMenu">
        <div class="ps-3">
          <a class="nav-link <?= $isActive('/admin/finance/index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/finance/index.php">
            <i class="bi bi-graph-up"></i> <span>ภาพรวมการเงิน</span>
          </a>
          <a class="nav-link <?= $isActive('/admin/finance/expenses.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/finance/expenses.php">
            <i class="bi bi-receipt"></i> <span>รายจ่าย</span>
          </a>
          <a class="nav-link <?= $isActive('/admin/finance/expense_categories.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/finance/expense_categories.php">
            <i class="bi bi-tags"></i> <span>หมวดหมู่รายจ่าย</span>
          </a>
        </div>
      </div>
      <a class="nav-link <?= $isActive('/admin/banners/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/banners/index.php">
        <i class="bi bi-images"></i> <span>จัดการหน้าเว็บ</span>
      </a>
      <a class="nav-link" href="<?= BASE_URL ?>/public/index.php" target="_blank" rel="noopener">
        <i class="bi bi-shop"></i> <span>ดูหน้าร้าน</span>
      </a>
    </nav>

    <div class="admin-sidebar__footer">
      <a class="admin-logout text-decoration-none" href="<?= BASE_URL ?>/admin/logout.php">
        <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
      </a>
      <div class="small mt-2" style="color: var(--admin-sidebar-muted);">© <?= date('Y') ?> BakingProStore</div>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="admin-main">
    <header class="admin-topbar">
      <div class="admin-topbar__inner">
        <button class="btn btn-outline-secondary d-lg-none" type="button" id="btnToggleSidebar" aria-label="Toggle sidebar">
          <i class="bi bi-list"></i>
        </button>

        <form class="admin-search" method="get" action="#" onsubmit="return false;">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" placeholder="ค้นหาเมนู/รายการ..." aria-label="Search">
          </div>
        </form>

        <div class="admin-user">
          <button class="btn btn-light border" type="button" title="Notifications">
            <i class="bi bi-bell"></i>
          </button>
          <div class="admin-user__pill">
            <span class="admin-user__avatar"><i class="bi bi-person"></i></span>
            <div class="d-none d-sm-block">
              <div class="small text-muted" style="line-height:1.1">Administrator</div>
              <div class="fw-semibold" style="line-height:1.1; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="admin-content">

<script>
  (function(){
    const btn = document.getElementById('btnToggleSidebar');
    const sb = document.getElementById('adminSidebar');
    if (!btn || !sb) return;

    btn.addEventListener('click', function(){
      sb.classList.toggle('show');
    });

    // click outside to close on mobile
    document.addEventListener('click', function(e){
      if (window.matchMedia('(min-width: 992px)').matches) return;
      if (!sb.classList.contains('show')) return;
      const isInside = sb.contains(e.target) || btn.contains(e.target);
      if (!isInside) sb.classList.remove('show');
    });
  })();
</script>