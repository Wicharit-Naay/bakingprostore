<?php
function cart_init() {
  if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
}

function cart_add($product_id, $qty = 1) {
  cart_init();
  $product_id = (int)$product_id;
  $qty = (int)$qty;
  if ($qty < 1) $qty = 1;

  if (!isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id] = 0;
  }
  $_SESSION['cart'][$product_id] += $qty;
}

function cart_remove($product_id) {
  cart_init();
  $product_id = (int)$product_id;
  unset($_SESSION['cart'][$product_id]);
}

function cart_clear() {
  $_SESSION['cart'] = [];
}