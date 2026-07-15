<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\SharePointService;
use Throwable;

final class DocumentController
{
    public function generateEstimate(string $id): void { $this->generate('estimate',(int)$id); }
    public function generateInvoice(string $id): void { $this->generate('invoice',(int)$id); }
    public function generateAgreement(string $id): void { $this->generate('agreement',(int)$id); }
    private function generate(string $type,int $id): void
    {
        Auth::requireRole('office','owner');verify_csrf();
        try{
            $doc=(new DocumentService())->generate($type,$id);
            $sp=new SharePointService();
            if($sp->configured()){
                try{$remote=(string)\App\Core\Env::get('M365_SHAREPOINT_BASE_FOLDER','Exterior Services').'/'.ucfirst($type).'s/'.$doc['original_name'];$uploaded=$sp->upload($doc['path'],$remote);Database::connection()->prepare("UPDATE documents SET storage_status='uploaded',sharepoint_item_id=?,sharepoint_url=? WHERE id=?")->execute([$uploaded['id']??null,$uploaded['webUrl']??null,$doc['id']]);}catch(Throwable $e){Database::connection()->prepare("UPDATE documents SET storage_status='failed',upload_error=? WHERE id=?")->execute([$e->getMessage(),$doc['id']]);}
            }
            AuditService::log('document.generated',$type,$id,['document_id'=>$doc['id']]);flash('success',ucfirst($type).' PDF generated.');
        }catch(Throwable $e){flash('danger',$e->getMessage());}
        redirect($type==='agreement'?'/portal/agreements':'/portal/'.$type.'s/'.$id);
    }

    public function download(string $id): void
    {
        Auth::requireLogin();$s=Database::connection()->prepare('SELECT * FROM documents WHERE id=?');$s->execute([(int)$id]);$doc=$s->fetch();
        if(!$doc||!is_file((string)$doc['local_path'])){http_response_code(404);exit('Document not found.');}
        header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.basename((string)$doc['original_name']).'"');header('Content-Length: '.filesize((string)$doc['local_path']));readfile((string)$doc['local_path']);exit;
    }
}
