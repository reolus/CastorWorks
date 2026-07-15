<?php
namespace App\Core;

final class Auth
{
    private const ROLE_RANK = ['technician'=>10,'estimator'=>20,'crew_leader'=>30,'office'=>40,'owner'=>90,'administrator'=>100];
    public static function check(): bool { return isset($_SESSION['user']); }
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function id(): ?int { return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null; }
    public static function role(): string { return (string)($_SESSION['user']['role'] ?? ''); }
    public static function login(array $user): void { session_regenerate_id(true); $_SESSION['user'] = $user; }
    public static function logout(): void { $_SESSION=[]; if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);} session_destroy(); }
    public static function requireLogin(): void { if(!self::check()) redirect('/login'); }
    public static function can(string ...$roles): bool { if(!self::check()) return false; return in_array(self::role(),$roles,true) || self::role()==='administrator'; }
    public static function atLeast(string $role): bool { return (self::ROLE_RANK[self::role()]??0) >= (self::ROLE_RANK[$role]??PHP_INT_MAX); }
    public static function requireRole(string ...$roles): void { self::requireLogin(); if(!self::can(...$roles)){http_response_code(403); View::render('errors/403',['title'=>'Access denied'],'portal'); exit;} }
}
