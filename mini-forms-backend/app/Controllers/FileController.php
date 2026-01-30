<?php

namespace App\Controllers;

use App\Core\Database;

class FileController extends BaseController
{
    public function download(array $params): void
    {
        $userId = $this->currentUserId();
        $uploadId = (int)($params['uploadId'] ?? 0);
        if ($uploadId <= 0) $this->fail('VALIDATION_ERROR', 'Upload ID inválido', 422);

        $db = Database::getInstance()->getConnection();

        // Garantir que o upload está ligado a uma resposta de um formulário do user
        $stmt = $db->prepare("
            SELECT u.id, u.storage_key, u.original_name, u.mime_type, u.response_id
            FROM uploads u
            JOIN form_responses r ON r.id = u.response_id
            JOIN forms f ON f.id = r.form_id
            WHERE u.id = ? AND u.status='ATTACHED' AND f.user_id = ?
        ");
        $stmt->execute([$uploadId, $userId]);
        $up = $stmt->fetch();

        if (!$up) $this->fail('FORBIDDEN', 'Sem permissão ou ficheiro não encontrado', 403);

        $path = realpath(__DIR__ . '/../../uploads/' . $up['storage_key']);
        if (!$path || !is_file($path)) $this->fail('FILE_NOT_FOUND', 'Ficheiro não existe no servidor', 404);

        // Resposta binária (stream)
        header_remove('Content-Type');
        header('Content-Type: ' . ($up['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . addslashes($up['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        http_response_code(200);
        readfile($path);
        exit;
    }
}
