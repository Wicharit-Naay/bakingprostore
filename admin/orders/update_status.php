<?php
require_once __DIR__ . '/../../config/connectdb.php';
require_once __DIR__ . '/../../helpers/functions.php';

// ถ้ามี auth helper ให้ใช้
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

// --- helper: ดึงข้อมูลแอดมินจาก session ให้ได้มากที่สุด ---
$adminId = null;
$adminName = null;
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $adminId = $_SESSION['user']['id'] ?? ($_SESSION['user']['user_id'] ?? null);
    $adminName = $_SESSION['user']['name'] ?? ($_SESSION['user']['email'] ?? null);
}
if ($adminName === null) {
    $adminName = $_SESSION['admin_name'] ?? ($_SESSION['name'] ?? 'Admin');
}
if ($adminId !== null) {
    $adminId = (int)$adminId;
}
$adminName = (string)$adminName;

// รับค่า
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = trim($_POST['status'] ?? '');

$allowed = ['pending','paid','shipping','completed','cancelled'];
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/orders/index.php?err=invalid_id');
    exit;
}
if (!in_array($status, $allowed, true)) {
    header('Location: ' . BASE_URL . '/admin/orders/show.php?id=' . $id . '&err=invalid_status');
    exit;
}

// อัปเดต + log
try {
    // อ่านสถานะเดิมก่อน
    $oldStmt = $pdo->prepare('SELECT status FROM orders WHERE id=? LIMIT 1');
    $oldStmt->execute([$id]);
    $oldRow = $oldStmt->fetch();
    if (!$oldRow) {
        header('Location: ' . BASE_URL . '/admin/orders/index.php?err=not_found');
        exit;
    }
    $oldStatus = (string)($oldRow['status'] ?? '');

    // ถ้าไม่ได้เปลี่ยนจริง ก็ไม่ต้องทำอะไรหนัก ๆ
    if ($oldStatus === $status) {
        header('Location: ' . BASE_URL . '/admin/orders/show.php?id=' . $id . '&ok=no_change');
        exit;
    }

    // ทำแบบ transaction เพื่อให้ update + log ไปด้วยกัน
    if (method_exists($pdo, 'beginTransaction')) {
        $pdo->beginTransaction();
    }

    // 1) update orders
    $up = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
    $up->execute([$status, $id]);

    // 2) insert log (ถ้ามีตาราง)
    // ตารางที่แนะนำ: order_status_logs
    // columns: id, order_id, old_status, new_status, admin_id, admin_name, created_at
    try {
        $log = $pdo->prepare(
            "INSERT INTO order_status_logs (order_id, old_status, new_status, admin_id, admin_name, created_at)
             VALUES (?,?,?,?,?, NOW())"
        );
        $log->execute([
            $id,
            $oldStatus,
            $status,
            $adminId,
            $adminName,
        ]);
    } catch (Throwable $e) {
        // ถ้ายังไม่ได้สร้างตาราง log ให้ไม่ทำให้ทั้งระบบล้ม
        // สามารถเปิดทิ้งไว้เพื่อ debug ได้ ถ้าต้องการ
        // throw $e;
    }

    if (method_exists($pdo, 'commit')) {
        $pdo->commit();
    }

    header('Location: ' . BASE_URL . '/admin/orders/show.php?id=' . $id . '&ok=updated');
    exit;
} catch (Throwable $e) {
    if (method_exists($pdo, 'rollBack') && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . BASE_URL . '/admin/orders/show.php?id=' . $id . '&err=update_failed');
    exit;
}