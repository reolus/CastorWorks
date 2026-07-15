<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class NotificationCenterController{
 public function index():void{Auth::requireLogin();$pdo=Database::connection();$q=$pdo->prepare('SELECT * FROM portal_user_notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 200');$q->execute([Auth::id()]);View::render('portal/notifications/center',['title'=>'Notification Center','notifications'=>$q->fetchAll()],'portal');}
 public function read(int $id):void{Auth::requireLogin();verify_csrf();Database::connection()->prepare('UPDATE portal_user_notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?')->execute([$id,Auth::id()]);redirect('/portal/notifications');}
 public function readAll():void{Auth::requireLogin();verify_csrf();Database::connection()->prepare('UPDATE portal_user_notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=?')->execute([Auth::id()]);redirect('/portal/notifications');}
}
