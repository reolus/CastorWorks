<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;
final class PreferenceController
{
 public function save():void{Auth::requireLogin();verify_csrf();header('Content-Type: application/json');$key=preg_replace('/[^a-z0-9_.-]/i','',(string)($_POST['key']??''));$value=(string)($_POST['value']??'');if($key===''){http_response_code(422);echo json_encode(['ok'=>false]);return;}$s=Database::connection()->prepare('INSERT INTO user_preferences(user_id,preference_key,preference_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE preference_value=VALUES(preference_value),updated_at=NOW()');$s->execute([Auth::id(),$key,$value]);echo json_encode(['ok'=>true]);}
}
