<?php
require_once __DIR__ . '/../config/config.php';
unset($_SESSION['user']);
header("Location: " . BASE_URL . "/public/index.php");
exit;