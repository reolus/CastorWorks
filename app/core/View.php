<?php
namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'public'): void
    {
        $base = dirname(__DIR__) . '/views';
        $viewFile = $base . '/' . $view . '.php';
        $layoutFile = $base . '/layouts/' . $layout . '.php';
        if (!is_file($viewFile) || !is_file($layoutFile)) {
            throw new RuntimeException("View or layout not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require $layoutFile;
    }
}
