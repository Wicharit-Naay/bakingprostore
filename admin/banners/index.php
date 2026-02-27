<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_admin();

$rows = $pdo->query("SELECT * FROM site_banners ORDER BY sort_order ASC, id DESC")->fetchAll();

require_once __DIR__ . '/../../templates/admin_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-1">จัดการแบนเนอร์</h1>
    <div class="text-secondary small">รูปเลื่อนหน้าแรกจะดึงจากตารางนี้ (เฉพาะรายการที่ Active)</div>
  </div>
  <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/banners/create.php">
    <i class="bi bi-plus-lg me-1"></i>เพิ่มแบนเนอร์
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:110px;">รูป</th>
            <th>ชื่อ</th>
            <th style="width:140px;">เรียง</th>
            <th style="width:120px;">สถานะ</th>
            <th style="width:200px;" class="text-end">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="text-center text-secondary py-4">ยังไม่มีแบนเนอร์</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $imgPath = (string)($r['image'] ?? '');
                $img = $imgPath !== '' ? (BASE_URL . '/' . ltrim($imgPath, '/')) : '';
                $active = (int)($r['is_active'] ?? 0) === 1;
              ?>
              <tr>
                <td>
                  <div class="ratio ratio-16x9 rounded overflow-hidden border bg-light" style="width:100px;">
                    <?php if ($img !== ''): ?>
                      <img src="<?= h($img) ?>" alt="" style="object-fit:cover;" onerror="this.style.display='none'">
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="fw-semibold"><?= h($r['title'] ?? '') ?></div>
                  <?php if (!empty($r['link_url'])): ?>
                    <div class="small text-muted text-truncate" style="max-width:520px;"><?= h($r['link_url']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= (int)($r['sort_order'] ?? 0) ?></td>
                <td>
                  <?php if ($active): ?>
                    <span class="badge text-bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Hidden</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/banners/edit.php?id=<?= (int)($r['id'] ?? 0) ?>">
                    <i class="bi bi-pencil me-1"></i>แก้ไข
                  </a>
                  <a class="btn btn-sm btn-outline-danger"
                     href="<?= BASE_URL ?>/admin/banners/delete.php?id=<?= (int)($r['id'] ?? 0) ?>"
                     onclick="return confirm('ลบแบนเนอร์นี้?');">
                    <i class="bi bi-trash me-1"></i>ลบ
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>