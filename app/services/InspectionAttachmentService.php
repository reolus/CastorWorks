<?php
namespace App\Services;
use App\Core\Database;
final class InspectionAttachmentService{
 public static function policy(string $type):array{
  $q=Database::connection()->prepare("SELECT * FROM inspection_attachment_policies WHERE active=1 AND attachment_type IN (?, 'all') ORDER BY attachment_type='all',id LIMIT 1");$q->execute([$type]);return $q->fetch()?:['retain_days'=>null,'create_thumbnail'=>0,'thumbnail_width'=>480];
 }
 public static function thumbnail(string $absolutePath,string $relativePath,int $width):?string{
  if(!extension_loaded('gd')||!is_file($absolutePath))return null;$info=@getimagesize($absolutePath);if(!$info||$info[0]<=$width)return null;
  [$w,$h,$kind]=$info;$src=match($kind){IMAGETYPE_JPEG=>@imagecreatefromjpeg($absolutePath),IMAGETYPE_PNG=>@imagecreatefrompng($absolutePath),IMAGETYPE_WEBP=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($absolutePath):false,default=>false};if(!$src)return null;
  $newH=max(1,(int)round($h*($width/$w)));$dst=imagecreatetruecolor($width,$newH);imagealphablending($dst,false);imagesavealpha($dst,true);imagecopyresampled($dst,$src,0,0,0,0,$width,$newH,$w,$h);
  $dir=dirname($absolutePath);$base=pathinfo($absolutePath,PATHINFO_FILENAME).'-thumb.jpg';$target=$dir.'/'.$base;imagejpeg($dst,$target,82);imagedestroy($src);imagedestroy($dst);return dirname($relativePath).'/'.$base;
 }
}
