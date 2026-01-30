<?php

namespace App\Controllers;

use App\Core\Database;
use Firebase\JWT\JWT;

class AuthController extends BaseController
{
    public function login(): void
    {
        $data = $this->getJsonBody();
        $errors = $this->requireFields($data, ['email', 'password']);
        if ($errors) $this->fail('VALIDATION_ERROR', 'Campos inválidos', 422, $errors);

        $email = trim((string)$data['email']);
        $password = (string)$data['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('VALIDATION_ERROR', 'Email inválido', 422, [['field' => 'email', 'message' => 'Formato inválido']]);
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, email, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $this->fail('AUTH_INVALID_CREDENTIALS', 'Credenciais inválidas', 401);
        }

        $issuedAt = time();
        $ttl = (int)($_ENV['JWT_EXPIRY'] ?? 86400);
        $expiresAt = $issuedAt + $ttl;

        $payload = [
            'iat'  => $issuedAt,
            'exp'  => $expiresAt,
            'data' => [
                'id'    => (int)$user['id'],
                'email' => (string)$user['email'],
            ]
        ];

        $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        $this->ok([
            'token' => $jwt,
            'expires_in' => $ttl,
            'expires_at' => $expiresAt
        ]);
    }

    // DEV ONLY (controlado por env)
    public function register(): void
    {
        $allow = strtolower((string)($_ENV['ALLOW_REGISTER'] ?? 'false')) === 'true';
        if (!$allow) {
            $this->fail('REGISTER_DISABLED', 'Registo desativado neste ambiente', 403);
        }

        $data = $this->getJsonBody();
        $errors = $this->requireFields($data, ['name', 'email', 'password']);
        if ($errors) $this->fail('VALIDATION_ERROR', 'Campos inválidos', 422, $errors);

        $name = trim((string)$data['name']);
        $email = trim((string)$data['email']);
        $passRaw = (string)$data['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('VALIDATION_ERROR', 'Email inválido', 422, [['field' => 'email', 'message' => 'Formato inválido']]);
        }

        if (strlen($passRaw) < 6) {
            $this->fail('VALIDATION_ERROR', 'Senha fraca', 422, [['field' => 'password', 'message' => 'Mínimo 6 caracteres']]);
        }

        $password = password_hash($passRaw, PASSWORD_BCRYPT);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");

        try {
            $stmt->execute([$name, $email, $password]);
            $this->ok(['message' => 'Utilizador registado com sucesso'], 201);
        } catch (\Throwable $e) {
            $this->fail('REGISTER_FAILED', 'Erro ao registar', 500, [['message' => $e->getMessage()]]);
        }
    }
}
