<?php
namespace App\Services;
use RuntimeException;
final class PdfService
{
    public static function available(): bool
    {
        $autoload=dirname(__DIR__,2).'/vendor/autoload.php';
        if(!is_file($autoload)) return false;
        require_once $autoload;
        return class_exists('Dompdf\\Dompdf');
    }
    public static function assertAvailable(): void
    {
        if(!self::available()) throw new RuntimeException('Dompdf is required. Run composer install in the project directory.');
    }
}
