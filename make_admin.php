<?php
require_once __DIR__ . '/config/db.php';

$name = 'Admin';
$email = '66010914115@msu.ac.th';
$pass  = 'bc662';

$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (role,name,email,password_hash) VALUES ('admin',?,?,?)");
$stmt->execute([$name,$email,$hash]);

echo "Created admin: $email / $pass";