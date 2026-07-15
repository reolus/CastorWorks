<?php
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
require dirname(__DIR__) . '/app/core/Env.php';
require dirname(__DIR__) . '/app/core/Database.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');
$name = $argv[1] ?? 'Administrator';
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;
if (!$email || !$password || strlen($password) < 12) { exit("Usage: php scripts/create-admin.php \"Name\" email@example.com 'password-at-least-12-chars'\n"); }
$pdo = \App\Core\Database::connection();
$stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,?, 'active') ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role='administrator',status='active'");
$stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),'administrator']);
echo "Administrator created or updated.\n";
