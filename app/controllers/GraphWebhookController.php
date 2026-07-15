<?php
namespace App\Controllers;
use App\Core\Database;
final class GraphWebhookController{
 public function receive():void{if(isset($_GET['validationToken'])){header('Content-Type: text/plain');echo $_GET['validationToken'];return;}$payload=json_decode(file_get_contents('php://input'),true)?:[];$s=Database::connection()->prepare("INSERT INTO communication_queue(channel,action,payload,status) VALUES('calendar','graph_notification',?,'pending')");foreach($payload['value']??[] as $notification){$json=json_encode($notification,JSON_THROW_ON_ERROR);$s->execute([$json]);$external=(string)($notification['subscriptionId']??'graph').':'.hash('sha256',$json);$type=(string)($notification['changeType']??'notification');$pdo=Database::connection();$pdo->prepare('INSERT IGNORE INTO webhook_events(provider,provider_event_id,event_type,payload) VALUES(?,?,?,?)')->execute(['graph',$external,$type,$json]);}http_response_code(202);}
}
