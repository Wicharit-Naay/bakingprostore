<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_admin();

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
  $stmt = $pdo->prepare("SELECT id,name,email,phone,created_at
                         FROM users
                         WHERE role='customer'
                         AND (name LIKE ? OR email LIKE ?)
                         ORDER BY id DESC");
  $stmt->execute(['%'.$q.'%','%'.$q.'%']);
  $customers = $stmt->fetchAll();
} else {
  $customers = $pdo->query("SELECT id,name,email,phone,created_at
                            FROM users
                            WHERE role='customer'
                            ORDER BY id DESC")->fetchAll();
}

require_once __DIR__ . '/../../templates/admin_header.php';
?>
<h2>จัดการลูกค้า</h2>

<form method="get">
  <input name="q" value="<?= h($q) ?>" placeholder="ค้นหา ชื่อ/อีเมล">
  <button>ค้นหา</button>
  <a href="<?= BASE_URL ?>/admin/index.php">กลับเมนู</a>
</form>

<table id="dt" class="table">
  <thead>
    <tr><th>ID</th><th>ชื่อ</th><th>อีเมล</th><th>เบอร์</th><th>สมัครเมื่อ</th><th>จัดการ</th></tr>
  </thead>
  <tbody>
  <?php foreach($customers as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td>
      <td><?= h($c['name']) ?></td>
      <td><?= h($c['email']) ?></td>
      <td><?= h($c['phone']) ?></td>
      <td><?= h($c['created_at']) ?></td>
      <td>
        <a href="<?= BASE_URL ?>/admin/customers/edit.php?id=<?= (int)$c['id'] ?>">แก้ไข</a> |
        <a href="<?= BASE_URL ?>/admin/customers/delete.php?id=<?= (int)$c['id'] ?>" onclick="return confirm('ลบลูกค้าคนนี้?')">ลบ</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>