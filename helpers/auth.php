<?php
function is_logged_in(): bool {
  return isset($_SESSION['user']);
}
function require_login() {
  if (!is_logged_in()) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
  }
}
function is_admin(): bool {
  return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}
function require_admin() {
  if (!is_admin()) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
  }
}