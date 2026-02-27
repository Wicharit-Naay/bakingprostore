<?php

require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/functions.php';
$authFile = __DIR__ . '/../../helpers/auth.php';
if (file_exists($authFile)) {
    require_once $authFile;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guard: ต้องเป็นแอดมิน
if (function_exists('require_admin')) {
    require_admin();
} elseif (function_exists('require_login')) {
    require_login();
    $role = $_SESSION['user']['role'] ?? ($_SESSION['role'] ?? '');
    if ($role !== 'admin') {
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }
} else {
    $role = $_SESSION['user']['role'] ?? ($_SESSION['role'] ?? '');
    if ($role !== 'admin') {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/products/index.php?err=invalid_id');
    exit;
}

// helper: เช็คว่ามีตารางไหม
function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

// helper: ลบไฟล์รูปแบบปลอดภัย (เฉพาะภายใต้ uploads/products)
function safe_unlink_product_upload(string $relPath): void {
    $relPath = ltrim($relPath, '/');
    // อนุญาตเฉพาะ uploads/products/...
    if (strpos($relPath, 'uploads/products/') !== 0) return;

    $abs = realpath(__DIR__ . '/../../' . $relPath);
    $base = realpath(__DIR__ . '/../../uploads/products');
    if ($abs && $base && strpos($abs, $base) === 0 && is_file($abs)) {
        @unlink($abs);
    }
}

try {
    $pdo->beginTransaction();

    // ดึงข้อมูลสินค้า (สำหรับลบรูปหลัก)
    $pStmt = $pdo->prepare("SELECT id, name, image FROM products WHERE id=? LIMIT 1");
    $pStmt->execute([$id]);
    $p = $pStmt->fetch();
    if (!$p) {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . '/admin/products/index.php?err=not_found');
        exit;
    }

    // ถ้ามี order_items อ้างอิงอยู่ ไม่ให้ลบ (กันข้อมูลออเดอร์พัง)
    if (table_exists($pdo, 'order_items')) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id=?");
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: ' . BASE_URL . '/admin/products/index.php?err=has_orders');
            exit;
        }
    }

    // ลบรูปหลายรูป (ถ้ามีตาราง product_images)
    if (table_exists($pdo, 'product_images')) {
        $imgRows = $pdo->prepare("SELECT image FROM product_images WHERE product_id=?");
        $imgRows->execute([$id]);
        foreach ($imgRows->fetchAll() as $r) {
            if (!empty($r['image'])) safe_unlink_product_upload($r['image']);
        }
        $delImgs = $pdo->prepare("DELETE FROM product_images WHERE product_id=?");
        $delImgs->execute([$id]);
    }

    // ลบตัวเลือกสินค้า (ถ้ามีตาราง product_variants)
    if (table_exists($pdo, 'product_variants')) {
        $delVar = $pdo->prepare("DELETE FROM product_variants WHERE product_id=?");
        $delVar->execute([$id]);
    }

    // ลบสินค้า
    $del = $pdo->prepare("DELETE FROM products WHERE id=?");
    $del->execute([$id]);

    $pdo->commit();

    // ลบรูปหลักหลัง commit (ไม่กระทบ DB)
    if (!empty($p['image'])) {
        safe_unlink_product_upload($p['image']);
    }

    header('Location: ' . BASE_URL . '/admin/products/index.php?ok=deleted');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . BASE_URL . '/admin/products/index.php?err=delete_failed');
    exit;
}