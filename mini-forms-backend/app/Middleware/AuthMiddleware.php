<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    public function handle(): void
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->deny('AUTH_TOKEN_MISSING', 'Token não fornecido', 401);
        }

        $jwt = $matches[1];
        $key = $_ENV['JWT_SECRET'] ?? '';

        if ($key === '') {
            $this->deny('AUTH_SERVER_MISCONFIG', 'JWT_SECRET não configurado', 500);
        }

        try {
            $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
            $_REQUEST['user'] = (array)($decoded->data ?? []);
        } catch (\Throwable $e) {
            $this->deny('AUTH_TOKEN_INVALID', 'Token inválido ou expirado', 401, ['reason' => $e->getMessage()]);
        }
    }

    private function deny(string $code, string $message, int $status, array $details = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details ? [$details] : []
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
