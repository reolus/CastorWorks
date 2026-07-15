<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\AuditService;
use App\Services\EntraAccessService;

final class EntraAccessController
{
    public function index(): void
    {
        Auth::requireRole('owner','administrator');
        $service = new EntraAccessService();
        $search = trim((string)($_GET['q'] ?? ''));
        $error = null;
        $groups = [];
        try { $groups = $service->groups($search ?: null); } catch (\Throwable $e) { $error=$e->getMessage(); }
        View::render('portal/users/entra-access',[
            'title'=>'Entra Access Mapping','groups'=>$groups,'mappings'=>$service->mappings(),
            'settings'=>$service->settings(),'search'=>$search,'error'=>$error
        ],'portal');
    }

    public function saveMapping(): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        (new EntraAccessService())->saveMapping($_POST);
        AuditService::log('entra.group_mapping_saved','entra_group',null,['group_id'=>$_POST['entra_group_id'] ?? null]);
        flash('success','Microsoft group role mapping saved.'); redirect('/portal/users/microsoft/access');
    }

    public function deleteMapping(string $id): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        (new EntraAccessService())->deleteMapping((int)$id);
        AuditService::log('entra.group_mapping_deleted','entra_group',(int)$id);
        flash('success','Mapping removed.'); redirect('/portal/users/microsoft/access');
    }

    public function saveSettings(): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        (new EntraAccessService())->saveSettings($_POST,Auth::id());
        AuditService::log('entra.sync_settings_updated','system',null);
        flash('success','Microsoft synchronization settings saved.'); redirect('/portal/users/microsoft/access');
    }
}
