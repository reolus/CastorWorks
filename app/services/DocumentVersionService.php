<?php
namespace App\Services;
use App\Core\Database; use App\Core\Auth;
final class DocumentVersionService
{
 public static function capture(int $documentId,string $path,string $notes=''):int{$pdo=Database::connection();$q=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM document_versions WHERE document_id=?');$q->execute([$documentId]);$v=(int)$q->fetchColumn();$hash=is_file($path)?hash_file('sha256',$path):null;$size=is_file($path)?filesize($path):null;$mime=is_file($path)?(mime_content_type($path)?:null):null;$pdo->prepare('INSERT INTO document_versions(document_id,version_number,storage_path,sha256,mime_type,file_size,change_notes,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute([$documentId,$v,$path,$hash,$mime,$size,$notes,Auth::id()]);$pdo->prepare('UPDATE documents SET current_version=? WHERE id=?')->execute([$v,$documentId]);return $v;}
}
