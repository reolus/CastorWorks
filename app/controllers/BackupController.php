<?php
namespace App\Controllers;use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class BackupController{public function index():void{Auth::requireRole('owner','administrator');$rows=Database::connection()->query('SELECT * FROM backup_runs ORDER BY started_at DESC LIMIT 100')->fetchAll();View::render('portal/backups/index',['title'=>'Backup & Restore','rows'=>$rows],'portal');}}
