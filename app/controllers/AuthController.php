<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use Throwable;

final class AuthController
{
    public function loginForm(): void
    {
        if(Auth::check()){header('Location: /portal');exit;}
        View::render('auth/login',['title'=>'Portal Sign In'],'auth');
    }

    public function login(): void
    {
        verify_csrf();$email=strtolower(trim($_POST['email']??''));$password=(string)($_POST['password']??'');$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$pdo=Database::connection();
        $max=(int)Env::get('LOGIN_MAX_ATTEMPTS','5');$minutes=(int)Env::get('LOGIN_LOCKOUT_MINUTES','15');
        $limit=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email=? AND ip_address=? AND succeeded=0 AND attempted_at>=DATE_SUB(NOW(),INTERVAL ? MINUTE)');$limit->execute([$email,$ip,$minutes]);
        if((int)$limit->fetchColumn()>=$max){flash('danger','Too many failed sign-in attempts. Please try again later.');header('Location: /login');exit;}
        $success=0;
        try{
            $stmt=$pdo->prepare('SELECT id,name,email,password_hash,role,status FROM users WHERE email=? LIMIT 1');$stmt->execute([$email]);$user=$stmt->fetch();
            if(!$user||$user['status']!=='active'||!password_verify($password,$user['password_hash']))throw new \RuntimeException('Invalid credentials');
            unset($user['password_hash']);Auth::login($user);$success=1;
            $pdo->prepare('INSERT INTO login_attempts(email,ip_address,succeeded) VALUES(?,?,1)')->execute([$email,$ip]);
            header('Location: /portal');exit;
        }catch(Throwable){$pdo->prepare('INSERT INTO login_attempts(email,ip_address,succeeded) VALUES(?,?,0)')->execute([$email,$ip]);flash('danger','Invalid email address or password.');header('Location: /login');exit;}
    }

    public function logout(): void { Auth::logout();header('Location: /login');exit; }
}
