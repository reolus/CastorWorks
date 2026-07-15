<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\WorkflowService;

final class WorkflowController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'administrator');
        $pdo = Database::connection();
        $rules = $pdo->query("SELECT w.*,COUNT(s.id) step_count FROM workflow_rules w LEFT JOIN workflow_steps s ON s.workflow_rule_id=w.id GROUP BY w.id ORDER BY w.priority,w.name")->fetchAll();
        $runs = $pdo->query("SELECT wr.*,w.name FROM workflow_runs wr JOIN workflow_rules w ON w.id=wr.workflow_rule_id ORDER BY wr.queued_at DESC LIMIT 75")->fetchAll();
        View::render('portal/workflows/index', ['title'=>'Workflow Designer','rules'=>$rules,'runs'=>$runs,'templates'=>WorkflowService::templates()], 'portal');
    }

    public function create(): void
    {
        Auth::requireRole('owner', 'administrator');
        View::render('portal/workflows/builder', ['title'=>'Create Workflow','workflow'=>null,'steps'=>[],'catalog'=>WorkflowService::catalog()], 'portal');
    }

    public function edit(int $id): void
    {
        Auth::requireRole('owner', 'administrator');
        $pdo=Database::connection();
        $s=$pdo->prepare('SELECT * FROM workflow_rules WHERE id=?');$s->execute([$id]);$workflow=$s->fetch();
        if(!$workflow){http_response_code(404);exit('Workflow not found.');}
        $s=$pdo->prepare('SELECT * FROM workflow_steps WHERE workflow_rule_id=? ORDER BY sort_order,id');$s->execute([$id]);
        View::render('portal/workflows/builder', ['title'=>'Edit Workflow','workflow'=>$workflow,'steps'=>$s->fetchAll(),'catalog'=>WorkflowService::catalog()], 'portal');
    }

    public function store(): void
    {
        Auth::requireRole('owner', 'administrator');verify_csrf();
        $id=WorkflowService::saveWorkflow(null,$_POST,Auth::id());
        AuditService::log('workflow.created','workflow_rule',$id);
        flash('success','Workflow created.');redirect('/portal/workflows/'.$id.'/edit');
    }

    public function update(int $id): void
    {
        Auth::requireRole('owner', 'administrator');verify_csrf();
        WorkflowService::saveWorkflow($id,$_POST,Auth::id());
        AuditService::log('workflow.updated','workflow_rule',$id);
        flash('success','Workflow saved as a new version.');redirect('/portal/workflows/'.$id.'/edit');
    }

    public function template(): void
    {
        Auth::requireRole('owner', 'administrator');verify_csrf();
        $key=(string)($_POST['template_key']??'');
        $id=WorkflowService::createFromTemplate($key,Auth::id());
        flash('success','Workflow template installed.');redirect('/portal/workflows/'.$id.'/edit');
    }

    public function toggle(int $id): void
    {
        Auth::requireRole('owner','administrator');verify_csrf();
        Database::connection()->prepare('UPDATE workflow_rules SET active=1-active WHERE id=?')->execute([$id]);
        redirect('/portal/workflows');
    }

    public function test(int $id): void
    {
        Auth::requireRole('owner','administrator');verify_csrf();
        $run=WorkflowService::queueRule($id,'manual',null,['tested_by'=>Auth::id(),'manual_test'=>true]);
        flash('success',$run?'Workflow test queued.':'Workflow is inactive or unavailable.');redirect('/portal/workflows');
    }

    public function retry(int $id): void
    {
        Auth::requireRole('owner','administrator');verify_csrf();
        Database::connection()->prepare("UPDATE workflow_runs SET status='queued',error_message=NULL,next_attempt_at=NOW(),completed_at=NULL WHERE id=?")->execute([$id]);
        flash('success','Workflow run queued for retry.');redirect('/portal/workflows');
    }
}
