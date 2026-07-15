<?php
namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class TeamsService
{
    public function configured(): bool { return Env::string('TEAMS_WEBHOOK_URL') !== ''; }

    public function send(string $title, string $message, ?string $url = null): array
    {
        $webhook = Env::string('TEAMS_WEBHOOK_URL');
        if ($webhook === '') throw new RuntimeException('Teams webhook is not configured.');
        $body = ['type'=>'message','attachments'=>[['contentType'=>'application/vnd.microsoft.card.adaptive','contentUrl'=>null,'content'=>['$schema'=>'http://adaptivecards.io/schemas/adaptive-card.json','type'=>'AdaptiveCard','version'=>'1.4','body'=>[['type'=>'TextBlock','size'=>'Medium','weight'=>'Bolder','text'=>$title],['type'=>'TextBlock','wrap'=>true,'text'=>$message]],'actions'=>$url?[['type'=>'Action.OpenUrl','title'=>'Open in portal','url'=>$url]]:[]]]]];
        $start = microtime(true);
        $ch = curl_init($webhook);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_SLASHES)]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        $duration = (int)round((microtime(true)-$start)*1000);
        if ($raw === false || $error !== '') throw new RuntimeException('Teams request failed: '.$error);
        if ($code < 200 || $code >= 300) throw new RuntimeException('Teams notification failed: '.$code.' '.substr((string)$raw,0,500));
        return ['http_status'=>$code,'duration_ms'=>$duration,'response'=>substr((string)$raw,0,500)];
    }

    public function test(): array
    {
        return $this->send('Rock Bluffs integration test','The Operations Portal successfully reached the Exterior Services Teams workflow at '.date('Y-m-d H:i:s T').'.');
    }
}
