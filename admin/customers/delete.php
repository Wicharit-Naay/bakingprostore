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
    header('Location: ' . BASE_URL . '/admin/customers/index.php?err=invalid_id');
    exit;
}
$meId = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
if ($meId > 0 && $id === $meId) {
    header('Location: ' . BASE_URL . '/admin/customers/index.php?err=cannot_delete_self');
    exit;
}

try {
    $pdo->beginTransaction();

    $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . '/admin/customers/index.php?err=not_found');
        exit;
    }

    // ลบ order_items ของลูกค้านี้ 
    $delItems = $pdo->prepare(
        'DELETE oi FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = ?'
    );
    $delItems->execute([$id]);

    // ลบ orders ของลูกค้านี้
    $delOrders = $pdo->prepare('DELETE FROM orders WHERE user_id = ?');
    $delOrders->execute([$id]);

    // ลบ user
    $delUser = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $delUser->execute([$id]);

    $pdo->commit();

    header('Location: ' . BASE_URL . '/admin/customers/index.php?ok=deleted');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ' . BASE_URL . '/admin/customers/index.php?err=delete_failed');
    exit;
}