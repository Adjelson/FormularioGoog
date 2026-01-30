<?php

namespace App\Controllers;

class BaseController
{
    protected function ok($data = null, int $status = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function fail(string $code, string $message, int $status = 400, array $details = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function requireFields(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data) || $data[$f] === null || $data[$f] === '') {
                $errors[] = ['field' => $f, 'message' => 'Campo obrigatório'];
            }
        }
        return $errors;
    }

    protected function currentUserId(): int
    {
        return (int)($_REQUEST['user']['id'] ?? 0);
    }
}
