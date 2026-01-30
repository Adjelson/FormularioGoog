<?php

namespace App\Controllers;

use App\Core\Database;

class QuestionController extends BaseController
{
    public function store(array $params): void
    {
        $userId = $this->currentUserId();
        $formId = (int)($params['id'] ?? 0);
        if ($formId <= 0) $this->fail('VALIDATION_ERROR', 'Form ID inválido', 422);

        $db = Database::getInstance()->getConnection();

        // ownership
        $own = $db->prepare("SELECT id FROM forms WHERE id=? AND user_id=?");
        $own->execute([$formId, $userId]);
        if (!$own->fetch()) $this->fail('FORBIDDEN', 'Sem permissão', 403);

        $data = $this->getJsonBody();
        $errors = $this->requireFields($data, ['type', 'label']);
        if ($errors) $this->fail('VALIDATION_ERROR', 'Campos inválidos', 422, $errors);

        $type = (string)$data['type'];
        if (!in_array($type, ['text', 'long_text', 'checkbox', 'radio', 'upload'], true)) {
            $this->fail('VALIDATION_ERROR', 'Tipo inválido', 422, [['field' => 'type', 'message' => 'Tipos: text,long_text,checkbox,radio,upload']]);
        }

        $label = (string)$data['label'];
        $placeholder = (string)($data['placeholder'] ?? '');
        $isRequired = !empty($data['is_required']) ? 1 : 0;
        $position = isset($data['position']) ? (int)$data['position'] : 0;
        $config = array_key_exists('config', $data) ? json_encode($data['config'], JSON_UNESCAPED_UNICODE) : null;

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO form_questions (form_id, type, label, placeholder, is_required, position, config)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$formId, $type, $label, $placeholder, $isRequired, $position, $config]);
            $questionId = (int)$db->lastInsertId();

            // criar opções se checkbox OU radio e vierem no payload
            if (in_array($type, ['checkbox', 'radio'], true) && !empty($data['options']) && is_array($data['options'])) {
                $ins = $db->prepare("INSERT INTO question_options (question_id, option_label, position) VALUES (?, ?, ?)");
                $pos = 0;
                foreach ($data['options'] as $opt) {
                    $pos++;
                    $labelOpt = is_array($opt) ? (string)($opt['option_label'] ?? '') : (string)$opt;
                    if (trim($labelOpt) === '') continue;
                    $ins->execute([$questionId, $labelOpt, $pos]);
                }
            }


            $db->commit();
            $this->ok(['id' => $questionId], 201);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->fail('QUESTION_CREATE_FAILED', 'Erro ao criar pergunta', 500, [['message' => $e->getMessage()]]);
        }
    }

    public function update(array $params): void
    {
        $userId = $this->currentUserId();
        $questionId = (int)($params['id'] ?? 0);
        if ($questionId <= 0) $this->fail('VALIDATION_ERROR', 'Question ID inválido', 422);

        $db = Database::getInstance()->getConnection();

        // ownership via form
        $stmt = $db->prepare("
            SELECT q.id, q.form_id
            FROM form_questions q
            JOIN forms f ON f.id = q.form_id
            WHERE q.id = ? AND f.user_id = ?
        ");
        $stmt->execute([$questionId, $userId]);
        $row = $stmt->fetch();
        if (!$row) $this->fail('FORBIDDEN', 'Sem permissão', 403);

        $data = $this->getJsonBody();

        $fields = [];
        $values = [];

        if (isset($data['label'])) {
            $fields[] = "label=?";
            $values[] = (string)$data['label'];
        }
        if (isset($data['placeholder'])) {
            $fields[] = "placeholder=?";
            $values[] = (string)$data['placeholder'];
        }
        if (isset($data['is_required'])) {
            $fields[] = "is_required=?";
            $values[] = !empty($data['is_required']) ? 1 : 0;
        }
        if (isset($data['position'])) {
            $fields[] = "position=?";
            $values[] = (int)$data['position'];
        }
        if (array_key_exists('config', $data)) {
            $fields[] = "config=?";
            $values[] = $data['config'] === null ? null : json_encode($data['config'], JSON_UNESCAPED_UNICODE);
        }

        if (!$fields) $this->ok(['message' => 'Nada para atualizar']);

        $values[] = $questionId;

        try {
            $sql = "UPDATE form_questions SET " . implode(', ', $fields) . " WHERE id=?";
            $upd = $db->prepare($sql);
            $upd->execute($values);
            $this->ok(['message' => 'Pergunta atualizada']);
        } catch (\Throwable $e) {
            $this->fail('QUESTION_UPDATE_FAILED', 'Erro ao atualizar pergunta', 500, [['message' => $e->getMessage()]]);
        }
    }
}
