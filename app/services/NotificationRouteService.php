<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use Throwable;

final class NotificationRouteService
{
    private const LEVELS=['info'=>1,'warning'=>2,'critical'=>3];

    public function emit(string $eventKey,string $severity,string $title,string $message,?string $actionUrl=null,array $metadata=[]): array
    {
        $severity=array_key_exists($severity,self::LEVELS)?$severity:'info';
        $pdo=Database::connection();
        $q=$pdo->prepare('SELECT * FROM notification_routes WHERE active=1 AND event_key=? ORDER BY id');
        $q->execute([$eventKey]);
        $results=[];
        foreach($q->fetchAll() as $route){
            if(self::LEVELS[$severity] < self::LEVELS[$route['severity_min']]) continue;
            foreach($this->recipients($route) as $recipient){
                $status='sent';$error=null;
                try{$this->deliver($route['channel'],$recipient,$title,$message,$actionUrl,$eventKey,$severity);}
                catch(Throwable $e){$status='failed';$error=$e->getMessage();}
                $pdo->prepare('INSERT INTO notification_delivery_logs(notification_route_id,event_key,severity,channel,recipient,subject,message,status,error_message,metadata_json,sent_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$route['id'],$eventKey,$severity,$route['channel'],$recipient,$title,$message,$status,$error,json_encode($metadata,JSON_UNESCAPED_SLASHES),$status==='sent'?date('Y-m-d H:i:s'):null]);
                $results[]=['route_id'=>(int)$route['id'],'channel'=>$route['channel'],'recipient'=>$recipient,'status'=>$status,'error'=>$error];
            }
        }
        return $results;
    }

    private function recipients(array $route):array
    {
        $pdo=Database::connection();$type=$route['recipient_type'];$value=trim((string)$route['recipient_value']);
        if(in_array($type,['email','phone','teams_webhook'],true)) return [$value];
        if($type==='user'){$s=$pdo->prepare('SELECT id,email,mobile_phone FROM users WHERE id=? AND status=\'active\'');$s->execute([(int)$value]);$u=$s->fetch();return $u?[$route['channel']==='sms'?$u['mobile_phone']:($route['channel']==='portal'?$u['id']:$u['email'])]:[];}
        if($type==='role'){$s=$pdo->prepare('SELECT id,email,mobile_phone FROM users WHERE role=? AND status=\'active\'');$s->execute([$value]);$out=[];foreach($s->fetchAll() as $u)$out[]=$route['channel']==='sms'?$u['mobile_phone']:($route['channel']==='portal'?$u['id']:$u['email']);return array_values(array_filter($out));}
        return [];
    }

    private function deliver(string $channel,string $recipient,string $title,string $message,?string $actionUrl,string $eventKey,string $severity):void
    {
        if($channel==='email'){(new EmailService())->sendMail($recipient,$title,'<p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>'.($actionUrl?'<p><a href="'.htmlspecialchars($actionUrl,ENT_QUOTES,'UTF-8').'">Open in portal</a></p>':''));return;}
        if($channel==='teams'){(new TeamsService())->send($title,$message,$actionUrl);return;}
        if($channel==='sms'){if(!SmsService::send(null,null,$recipient,$title.': '.$message))throw new \RuntimeException('SMS delivery failed.');return;}
        Database::connection()->prepare('INSERT INTO portal_user_notifications(user_id,event_key,severity,title,message,action_url) VALUES(?,?,?,?,?,?)')->execute([(int)$recipient,$eventKey,$severity,$title,$message,$actionUrl]);
    }
}
