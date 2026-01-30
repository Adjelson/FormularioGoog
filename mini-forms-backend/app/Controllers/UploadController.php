<?php

namespace App\Controllers;

use App\Core\Database;

class UploadController extends BaseController
{
    public function store(): void
    {
        if (!isset($_FILES['file'])) {
            $this->fail('UPLOAD_MISSING', 'Envie o ficheiro no campo "file"', 400);
        }

        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->fail('UPLOAD_FAILED', 'Falha no upload', 400, [['php_error' => $file['error']]]);
        }

        $maxBytes = (int)($_ENV['UPLOAD_MAX_BYTES'] ?? (10 * 1024 * 1024)); // 10MB
        if (($file['size'] ?? 0) > $maxBytes) {
            $this->fail('UPLOAD_TOO_LARGE', 'Ficheiro excede o limite', 422, [['max_bytes' => $maxBytes]]);
        }

        // Detect MIME real
        $tmp = $file['tmp_name'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: 'application/octet-stream';

        $allowed = trim((string)($_ENV['UPLOAD_ALLOWED_MIME'] ?? 'application/pdf,image/png,image/jpeg,application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $allowedList = array_filter(array_map('trim', explode(',', $allowed)));

        if ($allowedList && !in_array($mime, $allowedList, true)) {
            $this->fail('UPLOAD_MIME_NOT_ALLOWED', 'Tipo de ficheiro não permitido', 422, [['mime' => $mime]]);
        }

        // Extensão segura (bloqueia php etc.)
        $origName = (string)($file['name'] ?? 'file');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $blocked = ['php', 'phtml', 'phar', 'htaccess', 'exe', 'sh', 'bat', 'cmd', 'js'];
        if ($ext && in_array($ext, $blocked, true)) {
            $this->fail('UPLOAD_EXTENSION_BLOCKED', 'Extensão bloqueada', 422, [['ext' => $ext]]);
        }

        $safeName = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
        $uploadDir = realpath(__DIR__ . '/../../uploads');
        if (!$uploadDir) {
            // tentar criar
            $base = __DIR__ . '/../../uploads';
            if (!is_dir($base) && !mkdir($base, 0755, true)) {
                $this->fail('UPLOAD_DIR_FAILED', 'Não foi possível criar diretório uploads', 500);
            }
            $uploadDir = realpath($base);
        }

        $dest = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($tmp, $dest)) {
            $this->fail('UPLOAD_MOVE_FAILED', 'Não foi possível guardar o ficheiro', 500);
        }

        // registar no DB como TEMP (expira em 6h)
        $db = Database::getInstance()->getConnection();
        $expiresAt = date('Y-m-d H:i:s', time() + (6 * 3600));

        try {
            $ins = $db->prepare("
                INSERT INTO uploads (storage_key, original_name, mime_type, size_bytes, status, expires_at)
                VALUES (?, ?, ?, ?, 'TEMP', ?)
            ");
            $ins->execute([$safeName, $origName, $mime, (int)$file['size'], $expiresAt]);

            $uploadId = (int)$db->lastInsertId();

            $this->ok([
                'upload_id' => $uploadId,
                'original_name' => $origName,
                'mime_type' => $mime,
                'size_bytes' => (int)$file['size'],
                'expires_at' => $expiresAt
            ], 201);
        } catch (\Throwable $e) {
            // se falhar DB, apagar ficheiro para não ficar órfão
            @unlink($dest);
            $this->fail('UPLOAD_DB_FAILED', 'Erro ao registar upload', 500, [['message' => $e->getMessage()]]);
        }
    }
}
