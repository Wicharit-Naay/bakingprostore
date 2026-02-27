<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ไม่พบประเภท");

$check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
$check->execute([$id]);

if ((int)$check->fetchColumn() > 0) {
  die("ลบไม่ได้: ประเภทนี้มีสินค้าอยู่");
}

$del = $pdo->prepare("DELETE FROM categories WHERE id=?");
$del->execute([$id]);

header("Location: " . BASE_URL . "/admin/categories/index.php");
exit;