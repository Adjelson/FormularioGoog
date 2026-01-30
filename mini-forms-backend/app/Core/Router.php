<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, string $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function dispatch(string $method, string $uri)
    {
        $method = strtoupper($method);
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = "#^" . $pattern . "$#";

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middlewares'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $middleware->handle();
                }

                [$controller, $action] = explode('@', $route['handler']);
                $controllerClass = "App\\Controllers\\$controller";

                if (!class_exists($controllerClass)) {
                    return $this->jsonError('ROUTER_CONTROLLER_NOT_FOUND', "Controller '$controller' não encontrado.", 500);
                }

                $controllerInstance = new $controllerClass();

                if (!method_exists($controllerInstance, $action)) {
                    return $this->jsonError('ROUTER_ACTION_NOT_FOUND', "Action '$action' não encontrada em '$controller'.", 500);
                }

                return $controllerInstance->$action($params);
            }
        }

        return $this->jsonError('ROUTE_NOT_FOUND', 'Rota não encontrada', 404);
    }

    private function jsonError(string $code, string $message, int $status): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => []
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
