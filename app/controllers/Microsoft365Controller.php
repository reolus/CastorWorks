<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\Env;use App\Core\View;use App\Services\IntegrationHealthService;use Throwable;
final class Microsoft365Controller
{
 public function index():void{Auth::requireRole('owner','administrator');$health=(new IntegrationHealthService())->all(true);$subs=[];try{$subs=Database::connection()->query('SELECT * FROM graph_subscriptions ORDER BY expiration_at')->fetchAll();}catch(Throwable){}$config=['Tenant ID'=>self::mask(Env::string('M365_TENANT_ID'),8),'Client ID'=>self::mask(Env::string('M365_CLIENT_ID'),8),'Shared mailbox / object ID'=>self::mask(Env::string('M365_SHARED_MAILBOX'),8),'Calendar ID'=>self::mask(Env::string('M365_CALENDAR_ID'),10),'SharePoint site ID'=>self::mask(Env::string('M365_SHAREPOINT_SITE_ID'),8),'SharePoint drive ID'=>self::mask(Env::string('M365_SHAREPOINT_DRIVE_ID'),10),'Base folder'=>Env::string('M365_SHAREPOINT_BASE_FOLDER','General'),'Teams webhook'=>Env::string('TEAMS_WEBHOOK_URL')!==''?'Configured':'Missing'];View::render('portal/microsoft365/index',['title'=>'Microsoft 365 Administration','health'=>$health,'subscriptions'=>$subs,'config'=>$config],'portal');}
 private static function mask(string $value,int $visible=6):string{if($value==='')return 'Missing';if(strlen($value)<=$visible*2)return str_repeat('*',strlen($value));return substr($value,0,$visible).'…'.substr($value,-$visible);}
}
