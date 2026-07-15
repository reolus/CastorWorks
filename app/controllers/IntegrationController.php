<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Services\TeamsService;use Throwable;
final class IntegrationController
{
 public function testTeams():void{Auth::requireRole('owner','administrator');verify_csrf();$status='failed';$http=null;$duration=null;$detail='Unknown Teams test failure.';try{$result=(new TeamsService())->test();$status='ok';$http=$result['http_status'];$duration=$result['duration_ms'];$detail='Teams accepted the test Adaptive Card (HTTP '.$http.').';flash('success',$detail);}catch(Throwable $e){$detail=$e->getMessage();flash('danger',$detail);}Database::connection()->prepare('INSERT INTO integration_health_checks(integration_key,status,http_status,duration_ms,detail,tested_by,tested_at) VALUES(?,?,?,?,?,?,NOW())')->execute(['teams',$status,$http,$duration,$detail,Auth::id()]);redirect('/portal/integrations');}
}
