<?php
namespace App\Services;
use App\Core\Env;
use RuntimeException;
final class GraphService
{
    private string $tenant; private string $client; private string $secret; private string $sender;
    public function __construct()
    {
        $this->tenant=(string)Env::get('M365_TENANT_ID',''); $this->client=(string)Env::get('M365_CLIENT_ID','');
        $this->secret=(string)Env::get('M365_CLIENT_SECRET',''); $this->sender=(string)Env::get('M365_SHARED_MAILBOX','');
    }
    public function configured(): bool { return $this->tenant!=='' && $this->client!=='' && $this->secret!=='' && $this->sender!==''; }
    private function token(): string
    {
        if (!$this->configured()) throw new RuntimeException('Microsoft Graph is not configured.');
        $ch=curl_init("https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/token");
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$this->client,'client_secret'=>$this->secret,'scope'=>'https://graph.microsoft.com/.default','grant_type'=>'client_credentials'])]);
        $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        $data=json_decode((string)$raw,true); if($code>=300||empty($data['access_token'])) throw new RuntimeException('Unable to obtain Microsoft Graph token.');
        return $data['access_token'];
    }
    public function request(string $method,string $path,?array $body=null): array
    {
        $ch=curl_init('https://graph.microsoft.com/v1.0'.$path);
        $headers=['Authorization: Bearer '.$this->token(),'Content-Type: application/json'];
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers]);
        if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
        $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($code>=300) throw new RuntimeException('Microsoft Graph request failed: '.$code.' '.substr((string)$raw,0,500));
        return $raw!=='' ? (json_decode((string)$raw,true) ?: []) : [];
    }
    public function putBinary(string $path,string $content,string $contentType): array
    {
        $ch=curl_init('https://graph.microsoft.com/v1.0'.$path);
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token(),'Content-Type: '.$contentType],CURLOPT_POSTFIELDS=>$content]);
        $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($code>=300) throw new RuntimeException('Microsoft Graph upload failed: '.$code.' '.substr((string)$raw,0,500));
        return json_decode((string)$raw,true)?:[];
    }

    public function sendMail(string $to,string $subject,string $html,array $attachments=[]): array
    {
        // Accept either one attachment descriptor or a list of descriptors.
        // Older callers passed a single associative array, which caused PHP to
        // iterate over strings and then attempt array offsets on those strings.
        if (isset($attachments['path'])) {
            $attachments = [$attachments];
        }

        $atts=[];
        foreach($attachments as $a){
            if(!is_array($a)) continue;
            $path=(string)($a['path']??'');
            if($path===''||!is_file($path)) continue;
            $name=(string)($a['name']??basename($path));
            $type=(string)($a['type']??'application/pdf');
            $bytes=file_get_contents($path);
            if($bytes===false) continue;
            $atts[]=['@odata.type'=>'#microsoft.graph.fileAttachment','name'=>$name,'contentType'=>$type,'contentBytes'=>base64_encode($bytes)];
        }

        return $this->request('POST','/users/'.rawurlencode($this->sender).'/sendMail',['message'=>['subject'=>$subject,'body'=>['contentType'=>'HTML','content'=>$html],'toRecipients'=>[['emailAddress'=>['address'=>$to]]],'attachments'=>$atts],'saveToSentItems'=>true]);
    }
    public function updateEvent(string $eventId,array $job): array
    {
        $calendar=(string)Env::get('M365_CALENDAR_ID',''); $base='/users/'.rawurlencode($this->sender).($calendar?'/calendars/'.rawurlencode($calendar):'/calendar').'/events/'.rawurlencode($eventId);
        return $this->request('PATCH',$base,['subject'=>$job['service_summary'].' - '.$job['display_name'],'body'=>['contentType'=>'HTML','content'=>'Job '.$job['job_number'].'<br><a href="'.rtrim((string)Env::get('APP_URL',''),'/').'/portal/jobs/'.$job['id'].'">Open work order</a>'],'start'=>['dateTime'=>date('Y-m-d\TH:i:s',strtotime($job['scheduled_start'])),'timeZone'=>(string)Env::get('APP_TIMEZONE','America/Chicago')],'end'=>['dateTime'=>date('Y-m-d\TH:i:s',strtotime($job['scheduled_end'])),'timeZone'=>(string)Env::get('APP_TIMEZONE','America/Chicago')],'location'=>['displayName'=>$job['full_address'] ?? '']]);
    }
    public function getEvent(string $eventId): array
    {
        $calendar=(string)Env::get('M365_CALENDAR_ID',''); $base='/users/'.rawurlencode($this->sender).($calendar?'/calendars/'.rawurlencode($calendar):'/calendar').'/events/'.rawurlencode($eventId);
        return $this->request('GET',$base);
    }

    public function createEvent(array $job): array
    {
        $calendar=(string)Env::get('M365_CALENDAR_ID',''); $base='/users/'.rawurlencode($this->sender).($calendar?'/calendars/'.rawurlencode($calendar):'/calendar').'/events';
        return $this->request('POST',$base,['subject'=>$job['service_summary'].' - '.$job['display_name'],'body'=>['contentType'=>'HTML','content'=>'Job '.$job['job_number'].'<br><a href="'.rtrim((string)Env::get('APP_URL',''),'/').'/portal/jobs/'.$job['id'].'">Open work order</a>'],'start'=>['dateTime'=>date('Y-m-d\TH:i:s',strtotime($job['scheduled_start'])),'timeZone'=>(string)Env::get('APP_TIMEZONE','America/Chicago')],'end'=>['dateTime'=>date('Y-m-d\TH:i:s',strtotime($job['scheduled_end'])),'timeZone'=>(string)Env::get('APP_TIMEZONE','America/Chicago')],'location'=>['displayName'=>$job['full_address'] ?? '']]);
    }
}
