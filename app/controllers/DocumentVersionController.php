<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View;
final class DocumentVersionController
{
 public function index():void{Auth::requireLogin();$rows=Database::connection()->query('SELECT dv.*,d.document_type,d.reference_type,d.reference_id,u.name created_by_name FROM document_versions dv JOIN documents d ON d.id=dv.document_id LEFT JOIN users u ON u.id=dv.created_by ORDER BY dv.created_at DESC LIMIT 250')->fetchAll();View::render('portal/document-versions/index',['title'=>'Document versions','versions'=>$rows],'portal');}
}
