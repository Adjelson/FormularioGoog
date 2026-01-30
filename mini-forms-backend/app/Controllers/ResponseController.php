<?php

namespace App\Controllers;

use App\Core\Database;

class ResponseController extends BaseController
{
    public function index(array $params): void
    {
        $userId = $this->currentUserId();
        $formId = (int)($params['id'] ?? 0);
        if ($formId <= 0) $this->fail('VALIDATION_ERROR', 'Form ID inválido', 422);

        $db = Database::getInstance()->getConnection();

        // ownership
        $own = $db->prepare("SELECT id FROM forms WHERE id=? AND user_id=?");
        $own->execute([$formId, $userId]);
        if (!$own->fetch()) $this->fail('FORBIDDEN', 'Sem permissão', 403);

        $stmt = $db->prepare("
            SELECT r.id, r.submitted_at, r.ip_address
            FROM form_responses r
            WHERE r.form_id = ?
            ORDER BY r.submitted_at DESC
        ");
        $stmt->execute([$formId]);

        $this->ok($stmt->fetchAll());
    }

    public function show(array $params): void
    {
        $userId = $this->currentUserId();
        $rid = (int)($params['rid'] ?? 0);
        if ($rid <= 0) $this->fail('VALIDATION_ERROR', 'Response ID inválido', 422);

        $db = Database::getInstance()->getConnection();

        // garantir ownership via form
        $stmt = $db->prepare("
            SELECT r.id, r.form_id, r.submitted_at
            FROM form_responses r
            JOIN forms f ON f.id = r.form_id
            WHERE r.id = ? AND f.user_id = ?
        ");
        $stmt->execute([$rid, $userId]);
        $resp = $stmt->fetch();
        if (!$resp) $this->fail('FORBIDDEN', 'Sem permissão', 403);

        // buscar respostas com label e type
        $a = $db->prepare("
            SELECT a.id, a.question_id, a.answer_value, a.file_path, q.label, q.type
            FROM response_answers a
            JOIN form_questions q ON q.id = a.question_id
            WHERE a.response_id = ?
            ORDER BY q.position ASC
        ");
        $a->execute([$rid]);
        $answers = $a->fetchAll();

        // para checkbox, mapear ids => labels
        foreach ($answers as &$ans) {
            if ($ans['type'] === 'checkbox' && $ans['answer_value']) {
                $ids = json_decode($ans['answer_value'], true);
                if (is_array($ids) && $ids) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $o = $db->prepare("SELECT id, option_label FROM question_options WHERE id IN ($in)");
                    $o->execute(array_map('intval', $ids));
                    $map = [];
                    foreach ($o->fetchAll() as $row) $map[(int)$row['id']] = (string)$row['option_label'];

                    $labels = [];
                    foreach ($ids as $id) {
                        $id = (int)$id;
                        $labels[] = $map[$id] ?? ("#" . $id);
                    }
                    $ans['answer_parsed'] = $labels;
                }
            }

            if ($ans['type'] === 'upload') {
                // answer_value guarda upload_id (string)
                $uploadId = (int)($ans['answer_value'] ?? 0);
                $ans['upload_id'] = $uploadId;
                $ans['download_endpoint'] = $uploadId > 0 ? "/api/admin/files/{$uploadId}" : null;
            }
        }

        $this->ok([
            'response' => $resp,
            'answers' => $answers
        ]);
    }
}
