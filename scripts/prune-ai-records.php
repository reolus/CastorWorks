<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__);
require $root . '/app/core/Env.php';
Env::load($root . '/.env');
require $root . '/app/core/Database.php';

$pdo = Database::connection();
$policy = $pdo->query('SELECT * FROM ai_policy_settings WHERE id=1')->fetch() ?: [];
$draftDays = max(1, (int) ($policy['draft_retention_days'] ?? 90));
$usageDays = max(1, (int) ($policy['usage_retention_days'] ?? 365));
$auditDays = max(1, (int) ($policy['audit_retention_days'] ?? 730));
$expireDays = max(1, (int) ($policy['draft_expiration_days'] ?? 30));

$pdo->exec("UPDATE ai_generated_drafts SET status='expired' WHERE status IN ('draft','approved') AND COALESCE(expires_at,DATE_ADD(created_at,INTERVAL {$expireDays} DAY)) < NOW()");
$drafts = $pdo->exec("DELETE FROM ai_generated_drafts WHERE status IN ('rejected','used','expired') AND created_at < DATE_SUB(NOW(),INTERVAL {$draftDays} DAY)");
$usage = $pdo->exec("DELETE FROM ai_usage_logs WHERE created_at < DATE_SUB(NOW(),INTERVAL {$usageDays} DAY)");
$audit = $pdo->exec("DELETE FROM audit_logs WHERE action LIKE 'ai.%' AND created_at < DATE_SUB(NOW(),INTERVAL {$auditDays} DAY)");

echo "AI retention complete: drafts={$drafts}, usage={$usage}, audit={$audit}\n";
