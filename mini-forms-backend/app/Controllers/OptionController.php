<?php

namespace App\Controllers;

use App\Core\Database;

class OptionController extends BaseController
{
    public function store(array $params): void
    {
        $userId = $this->currentUserId();
        $questionId = (int)($params['id'] ?? 0);
        if ($questionId <= 0) $this->fail('VALIDATION_ERROR', 'Question ID inválido', 422);

        $db = Database::getInstance()->getConnection();

        // ownership
        $stmt = $db->prepare("
    SELECT q.id
    FROM form_questions q
    JOIN forms f ON f.id = q.form_id
    WHERE q.id = ? AND f.user_id = ? AND q.type IN ('checkbox','radio')
");
        $stmt->execute([$questionId, $userId]);
        if (!$stmt->fetch()) {
            $this->fail('FORBIDDEN', 'Sem permissão ou pergunta não é checkbox/radio', 403);
        }

        $data = $this->getJsonBody();
        $errors = $this->requireFields($data, ['option_label']);
        if ($errors) $this->fail('VALIDATION_ERROR', 'Campos inválidos', 422, $errors);

        $label = trim((string)$data['option_label']);
        $pos = isset($data['position']) ? (int)$data['position'] : 0;

        try {
            $ins = $db->prepare("INSERT INTO question_options (question_id, option_label, position) VALUES (?, ?, ?)");
            $ins->execute([$questionId, $label, $pos]);
            $this->ok(['id' => (int)$db->lastInsertId()], 201);
        } catch (\Throwable $e) {
            $this->fail('OPTION_CREATE_FAILED', 'Erro ao criar opção', 500, [['message' => $e->getMessage()]]);
        }
    }

    public function update(array $params): void
    {
        $userId = $this->currentUserId();
        $optionId = (int)($params['id'] ?? 0);
        if ($optionId <= 0) $this->fail('VALIDATION_ERROR', 'Option ID inválido', 422);

        $db = Database::getInstance()->getConnection();

        // ownership
        $stmt = $db->prepare("
            SELECT o.id
            FROM question_options o
            JOIN form_questions q ON q.id = o.question_id
            JOIN forms f ON f.id = q.form_id
            WHERE o.id = ? AND f.user_id = ?
        ");
        $stmt->execute([$optionId, $userId]);
        if (!$stmt->fetch()) $this->fail('FORBIDDEN', 'Sem permissão', 403);

        $data = $this->getJsonBody();
        $fields = [];
        $values = [];

        if (isset($data['option_label'])) {
            $fields[] = "option_label=?";
            $values[] = (string)$data['option_label'];
        }
        if (isset($data['position'])) {
            $fields[] = "position=?";
            $values[] = (int)$data['position'];
        }

        if (!$fields) $this->ok(['message' => 'Nada para atualizar']);

        $values[] = $optionId;

        try {
            $sql = "UPDATE question_options SET " . implode(', ', $fields) . " WHERE id=?";
            $upd = $db->prepare($sql);
            $upd->execute($values);
            $this->ok(['message' => 'Opção atualizada']);
        } catch (\Throwable $e) {
            $this->fail('OPTION_UPDATE_FAILED', 'Erro ao atualizar opção', 500, [['message' => $e->getMessage()]]);
        }
    }

    public function destroy(array $params): void
    {
        $userId = $this->currentUserId();
        $optionId = (int)($params['id'] ?? 0);
        if ($optionId <= 0) $this->fail('VALIDATION_ERROR', 'Option ID inválido', 422);

        $db = Database::getInstance()->getConnection();

        // ownership
        $stmt = $db->prepare("
            SELECT o.id
            FROM question_options o
            JOIN form_questions q ON q.id = o.question_id
            JOIN forms f ON f.id = q.form_id
            WHERE o.id = ? AND f.user_id = ?
        ");
        $stmt->execute([$optionId, $userId]);
        if (!$stmt->fetch()) $this->fail('FORBIDDEN', 'Sem permissão', 403);

        $del = $db->prepare("DELETE FROM question_options WHERE id=?");
        $del->execute([$optionId]);

        $this->ok(['message' => 'Opção removida']);
    }
}
