<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class WebhookReplayController{
 public function index():void{Auth::requireRole('owner','administrator');$rows=Database::connection()->query('SELECT w.*,(SELECT COUNT(*) FROM webhook_replays r WHERE r.webhook_event_id=w.id) replay_count FROM webhook_events w ORDER BY received_at DESC LIMIT 200')->fetchAll();View::render('portal/webhooks/index',['title'=>'Webhook Replay','rows'=>$rows],'portal');}
 public function replay(int $id):void{Auth::requireRole('owner','administrator');verify_csrf();Database::connection()->prepare('INSERT INTO webhook_replays(webhook_event_id,requested_by) VALUES(?,?)')->execute([$id,Auth::id()]);flash('success','Webhook replay queued.');redirect('/portal/webhooks');}
}
