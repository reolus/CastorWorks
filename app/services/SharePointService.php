<?php
namespace App\Services;
use App\Core\Env;use RuntimeException;
final class SharePointService{
 private GraphService $graph;
 public function __construct(){ $this->graph=new GraphService(); }
 public function configured():bool{return (string)Env::get('M365_SHAREPOINT_SITE_ID','')!==''&&(string)Env::get('M365_SHAREPOINT_DRIVE_ID','')!=='';}
 public function upload(string $localPath,string $remotePath):array{
  if(!$this->configured())throw new RuntimeException('SharePoint storage is not configured.');
  if(!is_file($localPath))throw new RuntimeException('Local document does not exist.');
  $site=(string)Env::get('M365_SHAREPOINT_SITE_ID');$drive=(string)Env::get('M365_SHAREPOINT_DRIVE_ID');
  return $this->graph->putBinary('/sites/'.rawurlencode($site).'/drives/'.rawurlencode($drive).'/root:/'.str_replace('%2F','/',rawurlencode(trim($remotePath,'/'))).':/content',(string)file_get_contents($localPath),mime_content_type($localPath)?:'application/octet-stream');
 }
}
