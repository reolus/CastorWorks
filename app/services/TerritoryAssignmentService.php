<?php
namespace App\Services;
use App\Core\Database;
final class TerritoryAssignmentService
{
 public static function assign(int $propertyId): ?int
 {
  $pdo=Database::connection();$q=$pdo->prepare('SELECT * FROM properties WHERE id=?');$q->execute([$propertyId]);$p=$q->fetch();if(!$p)return null;
  $territories=$pdo->query('SELECT * FROM service_territories WHERE active=1 ORDER BY priority,id')->fetchAll();
  foreach($territories as $t){$match=false;if($t['match_type']==='postal_code')$match=strcasecmp(trim((string)$p['postal_code']),trim((string)$t['match_value']))===0;elseif($t['match_type']==='city')$match=strcasecmp(trim((string)$p['city']),trim((string)$t['match_value']))===0;elseif($t['match_type']==='radius'&&$p['latitude']!==null&&$p['longitude']!==null&&$t['latitude']!==null&&$t['longitude']!==null){$match=self::distance((float)$p['latitude'],(float)$p['longitude'],(float)$t['latitude'],(float)$t['longitude'])<=(float)$t['radius_miles'];}if($match){$pdo->prepare('UPDATE properties SET service_territory_id=? WHERE id=?')->execute([(int)$t['id'],$propertyId]);return (int)$t['id'];}}
  $pdo->prepare('UPDATE properties SET service_territory_id=NULL WHERE id=?')->execute([$propertyId]);return null;
 }
 private static function distance(float $a,float $b,float $c,float $d):float{$r=3958.8;$x=deg2rad($c-$a);$y=deg2rad($d-$b);$h=sin($x/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin($y/2)**2;return 2*$r*asin(min(1,sqrt($h)));}
}
