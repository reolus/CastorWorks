<?php
declare(strict_types=1);
use App\Core\Env;use App\Controllers\BillingController;
require __DIR__.'/../app/core/Env.php';Env::load(__DIR__.'/../.env');spl_autoload_register(function($c){$p='App\\';if(str_starts_with($c,$p)){ $f=__DIR__.'/../app/'.str_replace('\\','/',substr($c,strlen($p))).'.php';if(is_file($f))require $f;}});require __DIR__.'/../app/helpers/functions.php';echo (new BillingController())->generateDue()." invoices generated\n";
