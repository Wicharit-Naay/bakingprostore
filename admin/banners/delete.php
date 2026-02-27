<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header('Location: ' . BASE_URL . '/admin/banners/index.php');
  exit;
}

$stmt = $pdo->prepare('SELECT image FROM site_banners WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
  header('Location: ' . BASE_URL . '/admin/banners/index.php');
  exit;
}

$del = $pdo->prepare('DELETE FROM site_banners WHERE id = ?');
$del->execute([$id]);

// ลบไฟล์รูป (เฉพาะที่อยู่ใน uploads/banners)
$image = (string)($row['image'] ?? '');
if ($image !== '' && str_starts_with($image, 'uploads/banners/')) {
  $fs = __DIR__ . '/../../' . $image;
  if (is_file($fs)) {
    @unlink($fs);
  }
}

header('Location: ' . BASE_URL . '/admin/banners/index.php');
exit;