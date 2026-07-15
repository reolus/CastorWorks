<?php
namespace App\Services;
use App\Core\Database;
final class AssetActivityService{
 public static function record(string $assetType,int $assetId,string $activityType,string $summary,array $details=[],?int $userId=null):void{
  if($assetId<1||!in_array($assetType,['vehicle','inventory'],true))return;
  Database::connection()->prepare('INSERT INTO asset_activity(asset_type,asset_id,activity_type,summary,details_json,performed_by) VALUES(?,?,?,?,?,?)')->execute([$assetType,$assetId,$activityType,$summary,$details?json_encode($details,JSON_UNESCAPED_SLASHES):null,$userId]);
 }
}
