<?php
declare(strict_types=1);
$root=dirname(__DIR__);$errors=[];
$required=['app/controllers/MapController.php','app/views/portal/map/index.php','public/assets/js/dispatch-map.js','public/assets/css/dispatch-map.css','database/migrate_phase32_2.sql'];
foreach($required as $file){if(!is_file($root.'/'.$file))$errors[]='Missing '.$file;}
$map=file_get_contents($root.'/app/controllers/MapController.php')?:'';
if(!str_contains($map,'updatePropertyCoordinates'))$errors[]='Coordinate correction endpoint missing.';
$health=file_get_contents($root.'/app/services/IntegrationHealthService.php')?:'';
foreach(['Map & Geocoding','AWS Communications','Azure Communications'] as $label){if(!str_contains($health,$label))$errors[]='Health monitor missing: '.$label;}
$js=file_get_contents($root.'/public/assets/js/site.js')?:'';
if(!str_contains($js,"group.classList.toggle('is-open',opening)"))$errors[]='Expanded sidebar toggle repair missing.';
if($errors){fwrite(STDERR,"ServiceOS 0.32.2 regression failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "ServiceOS 0.32.2 regression passed.\n";
