<?php
require_once __DIR__ . '/../config/config.php';

// เริ่ม session ถ้ายังไม่เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ล้างข้อมูล session ทั้งหมด
$_SESSION = [];

// ลบ session cookie (ถ้ามี) เพื่อให้ออกจากระบบสมบูรณ์
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

// ทำลาย session
session_destroy();

// กลับไปหน้าเข้าสู่ระบบ (หรือจะเปลี่ยนเป็นหน้าแรกก็ได้)
header('Location: ' . BASE_URL . '/public/login.php');
exit;