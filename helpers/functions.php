<?php
function h($str) {
  return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
function redirect($path) {
  header("Location: " . BASE_URL . $path);
  exit;
}