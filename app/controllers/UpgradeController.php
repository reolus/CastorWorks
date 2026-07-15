<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\View; use App\Services\MigrationService;
final class UpgradeController
{
 public function index():void{Auth::requireRole('owner','administrator');$pdo=\App\Core\Database::connection();$applied=[];try{$applied=$pdo->query('SELECT migration,checksum,applied_at FROM schema_migrations ORDER BY applied_at DESC,id DESC LIMIT 15')->fetchAll();}catch(\Throwable){}View::render('portal/system/upgrade',['title'=>'System upgrade','pending'=>array_map('basename',MigrationService::pending()),'applied'=>$applied,'version'=>trim((string)@file_get_contents(dirname(__DIR__,2).'/VERSION'))],'portal');}
 public function run():void{Auth::requireRole('owner','administrator');verify_csrf();try{$ran=MigrationService::runPending();flash('success',$ran?'Applied: '.implode(', ',$ran):'Database is already current.');}catch(\Throwable $e){flash('danger','Upgrade failed: '.$e->getMessage());}redirect('/portal/system/upgrade');}
}
