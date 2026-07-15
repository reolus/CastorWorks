<?php
namespace App\Services;
use App\Core\Database;use App\Core\Env;use Throwable;
final class IntegrationHealthService
{
    public function all(bool $probe=false): array{return ['graph'=>$this->graph($probe),'sharepoint'=>$this->sharePoint($probe),'teams'=>$this->teams(),'geocoding'=>$this->geocoding(),'route_engine'=>$this->routeEngine(),'gps_tracking'=>$this->gpsTracking(),'eta_progress'=>$this->etaProgress(),'operational_analytics'=>$this->operationalAnalytics(),'ai_assistant'=>$this->aiAssistant(),'communications'=>$this->communications(),'aws'=>$this->providerFamily('aws'),'azure'=>$this->providerFamily('azure'),'stripe'=>$this->stripe(),'twilio'=>$this->twilio()];}
    public function graph(bool $probe=false): array
    {
        $mailbox=Env::string('M365_SHARED_MAILBOX');$configured=Env::string('M365_TENANT_ID')!==''&&Env::string('M365_CLIENT_ID')!==''&&Env::string('M365_CLIENT_SECRET')!==''&&$mailbox!=='';
        $result=['name'=>'Microsoft Graph','configured'=>$configured,'healthy'=>null,'detail'=>$configured?'Configuration present':'Missing tenant, client, secret, or mailbox','latency_ms'=>null,'last_tested_at'=>null];if(!$probe||!$configured)return $result;$start=microtime(true);
        try{$g=new GraphService();$user=$g->request('GET','/users/'.rawurlencode($mailbox).'?%24select=id,displayName,mail,userPrincipalName');$calendar=Env::string('M365_CALENDAR_ID');if($calendar!=='')$g->request('GET','/users/'.rawurlencode($mailbox).'/calendars/'.rawurlencode($calendar).'?%24select=id,name');else $g->request('GET','/users/'.rawurlencode($mailbox).'/calendar?%24select=id,name');$result['healthy']=true;$result['detail']='Connected as '.($user['displayName']??$user['mail']??$mailbox).'; calendar found.';}catch(Throwable $e){$result['healthy']=false;$result['detail']=$e->getMessage();}$result['latency_ms']=(int)round((microtime(true)-$start)*1000);$result['last_tested_at']=date('Y-m-d H:i:s');return $result;
    }
    public function sharePoint(bool $probe=false): array
    {
        $site=Env::string('M365_SHAREPOINT_SITE_ID');$drive=Env::string('M365_SHAREPOINT_DRIVE_ID');$configured=$site!==''&&$drive!=='';$result=['name'=>'SharePoint','configured'=>$configured,'healthy'=>null,'detail'=>$configured?'Site and drive configured':'Missing site or drive ID','latency_ms'=>null,'last_tested_at'=>null];if(!$probe||!$configured)return $result;$start=microtime(true);
        try{(new GraphService())->request('GET','/sites/'.rawurlencode($site).'/drives/'.rawurlencode($drive).'?%24select=id,name,webUrl');$result['healthy']=true;$result['detail']='Document library reachable.';}catch(Throwable $e){$result['healthy']=false;$result['detail']=$e->getMessage();}$result['latency_ms']=(int)round((microtime(true)-$start)*1000);$result['last_tested_at']=date('Y-m-d H:i:s');return $result;
    }
    public function teams():array
    {
        $configured=Env::string('TEAMS_WEBHOOK_URL')!=='';$result=['name'=>'Microsoft Teams','configured'=>$configured,'healthy'=>null,'detail'=>$configured?'Webhook configured; run a connection test.':'Webhook not configured','latency_ms'=>null,'last_tested_at'=>null,'optional'=>true];if(!$configured)return $result;
        try{$pdo=Database::connection();$row=$pdo->query("SELECT status,http_status,duration_ms,detail,tested_at FROM integration_health_checks WHERE integration_key='teams' ORDER BY tested_at DESC,id DESC LIMIT 1")->fetch();if(!$row)return $result;$age=time()-strtotime($row['tested_at']);$result['healthy']=$row['status']==='ok';$result['detail']=$row['detail'].($age>2592000?' Last test is older than 30 days.':'');$result['latency_ms']=$row['duration_ms']!==null?(int)$row['duration_ms']:null;$result['last_tested_at']=$row['tested_at'];if($age>2592000&&$result['healthy']===true)$result['healthy']=null;}catch(Throwable){ }
        return $result;
    }
    private function geocoding():array
    {
        try{$pdo=Database::connection();$settings=(new GeocodingService($pdo))->settings();$provider=(string)($settings['provider']??'none');$active=(int)($settings['active']??0)===1&&$provider!=='none';$ready=$active&&($provider!=='google'||Env::string('GOOGLE_MAPS_API_KEY')!=='');$counts=$pdo->query("SELECT SUM(latitude IS NULL OR longitude IS NULL) pending,SUM(geocode_status='failed') failed FROM properties")->fetch()?:[];return ['name'=>'Map & Geocoding','configured'=>$active,'healthy'=>$active?$ready:null,'optional'=>true,'detail'=>$active?(ucfirst($provider).' active; '.(int)($counts['pending']??0).' unmapped, '.(int)($counts['failed']??0).' failed.'): 'Map provider disabled.','latency_ms'=>null,'last_tested_at'=>null];}catch(Throwable $e){return ['name'=>'Map & Geocoding','configured'=>false,'healthy'=>false,'optional'=>true,'detail'=>$e->getMessage(),'latency_ms'=>null,'last_tested_at'=>null];}
    }

    private function routeEngine():array
    {
        try{
            $pdo=Database::connection();
            $tables=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('route_plans','route_plan_stops','route_plan_history')")->fetchColumn();
            $provider=Env::string('ROUTE_MATRIX_PROVIDER','local');
            $configured=$tables===3;
            $needsKey=in_array($provider,['openrouteservice','mapbox'],true);
            $providerReady=!$needsKey||Env::string('ROUTE_MATRIX_API_KEY')!=='';
            $mapped=(int)$pdo->query("SELECT COUNT(*) FROM jobs j LEFT JOIN properties p ON p.id=j.property_id WHERE COALESCE(j.latitude,p.latitude) IS NOT NULL AND COALESCE(j.longitude,p.longitude) IS NOT NULL AND j.status<>'cancelled'")->fetchColumn();
            return ['name'=>'Route Optimization','configured'=>$configured,'healthy'=>$configured&&$providerReady,'optional'=>false,'detail'=>$configured?(ucfirst($provider).' provider; '.$mapped.' geocoded active job(s).'.($providerReady?'':' API key missing.')):'Route plan tables are missing.','latency_ms'=>null,'last_tested_at'=>null];
        }catch(Throwable $e){return ['name'=>'Route Optimization','configured'=>false,'healthy'=>false,'optional'=>false,'detail'=>$e->getMessage(),'latency_ms'=>null,'last_tested_at'=>null];}
    }

    private function gpsTracking(): array
    {
        try {
            $pdo = Database::connection();
            $policy = $pdo->query('SELECT * FROM gps_tracking_policies WHERE id=1')->fetch();
            if (!$policy || !(bool)$policy['enabled']) {
                return ['name'=>'Field GPS Tracking','configured'=>false,'healthy'=>null,'optional'=>true,'detail'=>'GPS tracking disabled by policy.','latency_ms'=>null,'last_tested_at'=>null];
            }
            $last = $pdo->query('SELECT MAX(captured_at) FROM gps_location_history')->fetchColumn();
            $staleMinutes = (int)($policy['stale_after_minutes'] ?? 10);
            $healthy = $last ? (time() - strtotime((string)$last)) <= ($staleMinutes * 60) : null;
            $detail = $last ? 'Last field update '.$last.'.' : 'Enabled; awaiting the first field location.';
            return ['name'=>'Field GPS Tracking','configured'=>true,'healthy'=>$healthy,'optional'=>true,'detail'=>$detail,'latency_ms'=>null,'last_tested_at'=>$last ?: null];
        } catch (Throwable $e) {
            return ['name'=>'Field GPS Tracking','configured'=>false,'healthy'=>false,'optional'=>true,'detail'=>$e->getMessage(),'latency_ms'=>null,'last_tested_at'=>null];
        }
    }

    private function etaProgress(): array
    {
        try {
            $pdo=Database::connection();
            $settings=$pdo->query('SELECT * FROM eta_notification_settings WHERE id=1')->fetch();
            if(!$settings || !(bool)$settings['enabled']) return ['name'=>'ETA & Route Progress','configured'=>false,'healthy'=>null,'optional'=>true,'detail'=>'ETA processing disabled.','latency_ms'=>null,'last_tested_at'=>null];
            $last=$pdo->query('SELECT * FROM eta_worker_runs ORDER BY completed_at DESC,id DESC LIMIT 1')->fetch();
            if(!$last) return ['name'=>'ETA & Route Progress','configured'=>true,'healthy'=>null,'optional'=>true,'detail'=>'Enabled; awaiting the first worker run.','latency_ms'=>null,'last_tested_at'=>null];
            $age=time()-strtotime((string)$last['completed_at']);
            $threshold=max(300,((int)$settings['minimum_recalculation_minutes']+5)*60);
            return ['name'=>'ETA & Route Progress','configured'=>true,'healthy'=>$last['status']==='ok'&&$age<=$threshold,'optional'=>true,'detail'=>(int)$last['jobs_updated'].' job(s) updated; '.(int)$last['notifications_sent'].' notice(s) sent.','latency_ms'=>null,'last_tested_at'=>$last['completed_at']];
        } catch(Throwable $e) { return ['name'=>'ETA & Route Progress','configured'=>false,'healthy'=>false,'optional'=>true,'detail'=>$e->getMessage(),'latency_ms'=>null,'last_tested_at'=>null]; }
    }


    private function aiAssistant(): array
    {
        try {
            $pdo = Database::connection();
            $settings = $pdo->query('SELECT * FROM ai_provider_settings WHERE id=1')->fetch();
            if (!is_array($settings) || !(bool) ($settings['enabled'] ?? false) || ($settings['provider'] ?? 'disabled') === 'disabled') {
                return ['name'=>'AI Assistant','configured'=>false,'healthy'=>null,'optional'=>true,'detail'=>'AI Assistant disabled.','latency_ms'=>null,'last_tested_at'=>null];
            }

            $health = (new AiProviderService($pdo))->healthCheck();
            $last = $pdo->query("SELECT status,provider,model,latency_ms,created_at FROM ai_usage_logs ORDER BY created_at DESC,id DESC LIMIT 1")->fetch();
            $month = $pdo->query("SELECT COUNT(*) requests,COALESCE(SUM(status<>'success'),0) failed,COALESCE(SUM(estimated_cost_usd),0) cost FROM ai_usage_logs WHERE created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetch() ?: [];
            $configured = ($health['status'] ?? '') !== 'disabled';
            $healthy = match ($health['status'] ?? 'warning') {
                'ok' => true,
                'failed' => false,
                default => null,
            };
            $detail = (string) ($health['detail'] ?? 'Unknown AI status.');
            $detail .= ' ' . (int) ($month['requests'] ?? 0) . ' request(s) this month; ' . (int) ($month['failed'] ?? 0) . ' failed; $' . number_format((float) ($month['cost'] ?? 0), 4) . ' estimated cost.';
            return [
                'name'=>'AI Assistant',
                'configured'=>$configured,
                'healthy'=>$healthy,
                'optional'=>true,
                'detail'=>$detail,
                'latency_ms'=>is_array($last) && $last['latency_ms'] !== null ? (int) $last['latency_ms'] : null,
                'last_tested_at'=>is_array($last) ? $last['created_at'] : null,
            ];
        } catch (Throwable $e) {
            return ['name'=>'AI Assistant','configured'=>false,'healthy'=>false,'optional'=>true,'detail'=>$e->getMessage(),'latency_ms'=>null,'last_tested_at'=>null];
        }
    }

    private function communications():array
    {
        try{$providers=(new CommunicationManager())->status();$enabled=array_values(array_filter($providers,fn($p)=>(int)$p['enabled']===1));$ready=array_values(array_filter($enabled,fn($p)=>$p['configured']));$labels=array_map(fn($p)=>$p['label'],$ready);return ['name'=>'Unified Communications','configured'=>$enabled!==[],'healthy'=>$ready!==[]?true:($enabled!==[]?false:null),'detail'=>$ready!==[]?'Active: '.implode(', ',$labels):($enabled!==[]?'Enabled providers are not configured.':'No providers enabled.'),'latency_ms'=>null,'last_tested_at'=>null,'optional'=>true];}catch(Throwable $e){return ['name'=>'Unified Communications','configured'=>false,'healthy'=>false,'detail'=>$e->getMessage(),'latency_ms'=>null,'last_tested_at'=>null,'optional'=>true];}
    }
    private function providerFamily(string $family):array
    {
        $label=$family==='aws'?'AWS Communications':'Azure Communications';
        try{$providers=array_values(array_filter((new CommunicationManager())->status(),fn($p)=>($family==='aws' ? (str_starts_with((string)$p['provider_key'],'aws_') || str_starts_with((string)$p['provider_key'],'amazon_')) : str_starts_with((string)$p['provider_key'],'azure_'))));$enabled=array_values(array_filter($providers,fn($p)=>(int)$p['enabled']===1));$configured=array_values(array_filter($enabled,fn($p)=>(bool)$p['configured']));if($enabled===[])return ['name'=>$label,'configured'=>false,'healthy'=>null,'optional'=>true,'detail'=>'No '.$family.' providers enabled.','latency_ms'=>null,'last_tested_at'=>null];$missing=array_map(fn($p)=>$p['label'],array_filter($enabled,fn($p)=>!(bool)$p['configured']));return ['name'=>$label,'configured'=>true,'healthy'=>$missing===[],'optional'=>true,'detail'=>$missing===[]?count($configured).' provider(s) ready.':'Missing configuration: '.implode(', ',$missing),'latency_ms'=>null,'last_tested_at'=>null];}catch(Throwable $e){return ['name'=>$label,'configured'=>false,'healthy'=>false,'optional'=>true,'detail'=>$e->getMessage(),'latency_ms'=>null,'last_tested_at'=>null];}
    }
    private function stripe():array{$ok=Env::string('STRIPE_SECRET_KEY')!=='';return ['name'=>'Stripe','configured'=>$ok,'healthy'=>null,'optional'=>true,'detail'=>$ok?'API key configured':'Not configured','latency_ms'=>null,'last_tested_at'=>null];}
    private function twilio():array{$ok=Env::string('TWILIO_ACCOUNT_SID')!==''&&Env::string('TWILIO_AUTH_TOKEN')!=='';return ['name'=>'Twilio','configured'=>$ok,'healthy'=>null,'optional'=>true,'detail'=>$ok?'Credentials configured':'Not configured','latency_ms'=>null,'last_tested_at'=>null];}
    private function operationalAnalytics(): array
    {
        try {
            $pdo=Database::connection();
            $tables=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('route_analytics','crew_daily_statistics','technician_daily_statistics','operational_analytics_runs')")->fetchColumn();
            if($tables<4)return ['name'=>'Operational Analytics','configured'=>false,'healthy'=>false,'optional'=>false,'detail'=>'Analytics tables are missing.','latency_ms'=>null,'last_tested_at'=>null];
            $last=$pdo->query('SELECT * FROM operational_analytics_runs ORDER BY completed_at DESC,id DESC LIMIT 1')->fetch();
            if(!$last)return ['name'=>'Operational Analytics','configured'=>true,'healthy'=>null,'optional'=>false,'detail'=>'Ready; awaiting the first analytics build.','latency_ms'=>null,'last_tested_at'=>null];
            $age=time()-strtotime((string)$last['completed_at']);
            return ['name'=>'Operational Analytics','configured'=>true,'healthy'=>$last['status']==='ok'&&$age<=90000,'optional'=>false,'detail'=>(int)$last['routes_built'].' route(s), '.(int)$last['crews_built'].' crew scorecard(s).','latency_ms'=>null,'last_tested_at'=>$last['completed_at']];
        } catch(Throwable $e) { return ['name'=>'Operational Analytics','configured'=>false,'healthy'=>false,'optional'=>false,'detail'=>$e->getMessage(),'latency_ms'=>null,'last_tested_at'=>null]; }
    }

}
