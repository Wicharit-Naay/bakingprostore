<?php

date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($host, 'localhost') !== false || strpos($host, '103.114.201.144') !== false) {
    define('BASE_URL', '/bakingprostore');
} else {
    define('BASE_URL', '');
}