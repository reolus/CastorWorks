<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\EntraUserSyncService;

final class EntraUserController
{
    public function index(): void
    {
        Auth::requireRole('owner','administrator');
        $search = trim((string)($_GET['q'] ?? ''));
        $error = null;
        $users = [];
        try {
            $users = (new EntraUserSyncService())->directoryUsers($search ?: null);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $runs = Database::connection()->query('SELECT * FROM entra_sync_runs ORDER BY id DESC LIMIT 10')->fetchAll();
        $preview=$_SESSION['entra_sync_preview']??null; unset($_SESSION['entra_sync_preview']);
        View::render('portal/users/entra',['title'=>'Microsoft 365 Users','directoryUsers'=>$users,'runs'=>$runs,'search'=>$search,'error'=>$error,'preview'=>$preview],'portal');
    }

    public function import(): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        $ids = $_POST['object_ids'] ?? [];
        $result = (new EntraUserSyncService())->import(is_array($ids) ? $ids : [], (string)($_POST['role'] ?? 'technician'));
        AuditService::log('entra.users_imported','user',null,['created'=>$result['created'],'updated'=>$result['updated']]);
        flash($result['errors'] ? 'warning' : 'success', "Microsoft users imported: {$result['created']} created, {$result['updated']} updated" . ($result['errors'] ? ', with errors.' : '.'));
        redirect('/portal/users/microsoft');
    }


    public function preview(): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        try {
            $preview=(new EntraUserSyncService())->preview();
            $_SESSION['entra_sync_preview']=$preview;
            flash('success',"Sync preview: {$preview['create']} create, {$preview['update']} update.");
        } catch (\Throwable $e) {
            flash('danger','Preview failed: '.$e->getMessage());
        }
        redirect('/portal/users/microsoft');
    }

    public function syncOne(string $id): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        try {
            $result=(new EntraUserSyncService())->syncOne($id);
            AuditService::log('entra.user_synced','user',null,['object_id'=>$id,'result'=>$result['result'],'role'=>$result['role']]);
            flash('success','Microsoft user synchronized as '.str_replace('_',' ',$result['role']).'.');
        } catch (\Throwable $e) {
            flash('danger','User sync failed: '.$e->getMessage());
        }
        redirect('/portal/users/microsoft');
    }

    public function sync(): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        $result = (new EntraUserSyncService())->syncAll(!empty($_POST['disable_missing']));
        AuditService::log('entra.users_synced','user',null,$result);
        flash($result['errors'] ? 'warning' : 'success', "Microsoft sync complete: {$result['created']} created, {$result['updated']} updated, {$result['disabled']} disabled.");
        redirect('/portal/users/microsoft');
    }
}
