<?php
use App\Core\Env;

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function asset(string $path): string { return '/assets/' . ltrim($path, '/'); }
function app_name(): string { return (string)Env::get('APP_NAME', 'Rock Bluffs Exterior Services'); }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(32)); } return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(419); exit('Session expired. Please refresh and try again.'); } }
function active_nav(string $path): string { return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === $path ? 'active' : ''; }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][] = ['type'=>$type,'message'=>$message]; }
function money(float|int|string|null $value): string { return '$' . number_format((float)$value, 2); }
function old(string $key, mixed $default=''): mixed { return $_SESSION['_old'][$key] ?? $default; }
function request_int(string $key): ?int { $v=filter_input(INPUT_POST,$key,FILTER_VALIDATE_INT); return $v===false||$v===null?null:(int)$v; }
function next_number(string $prefix): string { return $prefix . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)),0,6)); }

function public_token(): string { return hash('sha256', random_bytes(32)); }
function upload_dir(string $sub=''): string { $base=dirname(__DIR__,2).'/storage/uploads'; $dir=$base.($sub?'/'.trim($sub,'/'):''); if(!is_dir($dir)) mkdir($dir,0770,true); return $dir; }
function selected(mixed $a,mixed $b): string { return (string)$a===(string)$b?'selected':''; }
function checked(bool $value): string { return $value?'checked':''; }
