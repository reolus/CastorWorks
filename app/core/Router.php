<?php
namespace App\Core;

final class Router
{
    private array $routes = [];
    public function get(string $path, callable|array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void { $this->add('POST', $path, $handler); }
    private function add(string $method, string $path, callable|array $handler): void
    {
        $pattern = preg_replace('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', '(?P<$1>[^/]+)', '/' . trim($path, '/'));
        $this->routes[$method][] = ['pattern' => '#^' . ($pattern === '/' ? '/' : $pattern) . '$#', 'handler' => $handler];
    }
    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        if ($path === '//') $path = '/';
        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) continue;
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->{$action}(...array_values($params));
            } else {
                $handler(...array_values($params));
            }
            return;
        }
        http_response_code(404);
        View::render('errors/404', ['title' => 'Page Not Found'], 'public');
    }
}
