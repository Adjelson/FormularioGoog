<?php

namespace App\Controllers;

use App\Core\Database;

class FormController extends BaseController
{
    public function index(): void
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) $this->fail('AUTH_REQUIRED', 'Não autenticado', 401);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM forms WHERE user_id = ? AND is_archived = 0 ORDER BY created_at DESC");

        $stmt->execute([$userId]);
        $this->ok($stmt->fetchAll());
    }
    public function archived(): void
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) $this->fail('AUTH_REQUIRED', 'Não autenticado', 401);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
        SELECT * FROM forms
        WHERE user_id = ?
          AND is_archived = 1
        ORDER BY created_at DESC
    ");
        $stmt->execute([$userId]);
        $forms = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->ok(['data' => $forms]);
    }
    public function archive($params)
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->fail('VALIDATION_ERROR', 'ID obrigatório', 400);
            return;
        }

        $userId = $_REQUEST['user']['id'];
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
        UPDATE forms
        SET is_archived = 1,
            is_published = 0
        WHERE id = ? AND user_id = ?
    ");

        $stmt->execute([$id, $userId]);

        if ($stmt->rowCount() === 0) {
            $this->fail('FORM_NOT_FOUND', 'Formulário não encontrado', 404);
            return;
        }

        $this->ok(['message' => 'Formulário enviado para reciclagem']);
    }
    public function archiveQuestion($params)
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->fail('VALIDATION_ERROR', 'ID obrigatório', 400);
            return;
        }

        $userId = $_REQUEST['user']['id'];
        $db = Database::getInstance()->getConnection();

        // garantir que a pergunta pertence a um form do utilizador
        $stmt = $db->prepare("
        UPDATE form_questions q
        JOIN forms f ON f.id = q.form_id
        SET q.is_archived = 1
        WHERE q.id = ? AND f.user_id = ?
    ");
        $stmt->execute([$id, $userId]);

        if ($stmt->rowCount() === 0) {
            $this->fail('FORM_NOT_FOUND', 'Pergunta não encontrada', 404);
            return;
        }

        $this->ok(['message' => 'Pergunta enviada para reciclagem']);
    }

    public function unpublish(array $params): void
    {
        $userId = $this->currentUserId();
        $formId = (int)($params['id'] ?? 0);
        if ($formId <= 0) $this->fail('VALIDATION_ERROR', 'ID inválido', 422);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM forms WHERE id = ? AND user_id = ?");
        $stmt->execute([$formId, $userId]);
        if (!$stmt->fetch()) $this->fail('FORM_NOT_FOUND', 'Formulário não encontrado', 404);

        $upd = $db->prepare("UPDATE forms SET is_published = 0 WHERE id = ? AND user_id = ?");
        $upd->execute([$formId, $userId]);

        $this->ok(['message' => 'Publicação desativada', 'is_published' => false]);
    }
    public function store(): void
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) $this->fail('AUTH_REQUIRED', 'Não autenticado', 401);

        $data = $this->getJsonBody();
        $errors = $this->requireFields($data, ['title']);
        if ($errors) $this->fail('VALIDATION_ERROR', 'Campos inválidos', 422, $errors);

        $slug = bin2hex(random_bytes(8));

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO forms (user_id, title, description, slug, theme_settings, is_published)
            VALUES (?, ?, ?, ?, ?, 0)
        ");

        try {
            $stmt->execute([
                $userId,
                (string)$data['title'],
                (string)($data['description'] ?? ''),
                $slug,
                json_encode($data['theme'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE),
            ]);

            $id = (int)$db->lastInsertId();
            $this->ok(['id' => $id, 'slug' => $slug, 'is_published' => false], 201);
        } catch (\Throwable $e) {
            $this->fail('FORM_CREATE_FAILED', 'Erro ao criar formulário', 500, [['message' => $e->getMessage()]]);
        }
    }

    public function show(array $params): void
    {
        $userId = $this->currentUserId();
        $formId = (int)($params['id'] ?? 0);
        if ($formId <= 0) $this->fail('VALIDATION_ERROR', 'ID inválido', 422);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM forms WHERE id = ? AND user_id = ?");
        $stmt->execute([$formId, $userId]);
        $form = $stmt->fetch();

        if (!$form) $this->fail('FORM_NOT_FOUND', 'Formulário não encontrado', 404);

        // perguntas
        $q = $db->prepare("
    SELECT *
    FROM form_questions
    WHERE form_id = ?
      AND is_archived = 0
    ORDER BY position ASC
");
        $q->execute([$formId]);
        $questions = $q->fetchAll();

        foreach ($questions as &$question) {
            if ($question['type'] === 'checkbox' || $question['type'] === 'radio') {
                $o = $db->prepare("
            SELECT id, option_label, position
            FROM question_options
            WHERE question_id = ?
            ORDER BY position ASC
        ");
                $o->execute([(int)$question['id']]);
                $question['options'] = $o->fetchAll();
            }
        }


        $form['questions'] = $questions;
        $this->ok($form);
    }

    public function update(array $params): void
    {
        $userId = $this->currentUserId();
        $formId = (int)($params['id'] ?? 0);
        if ($formId <= 0) $this->fail('VALIDATION_ERROR', 'ID inválido', 422);

        $data = $this->getJsonBody();

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM forms WHERE id = ? AND user_id = ?");
        $stmt->execute([$formId, $userId]);
        if (!$stmt->fetch()) $this->fail('FORM_NOT_FOUND', 'Formulário não encontrado', 404);

        $title = isset($data['title']) ? (string)$data['title'] : null;
        $desc  = isset($data['description']) ? (string)$data['description'] : null;
        $theme = array_key_exists('theme', $data) ? json_encode($data['theme'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE) : null;

        $fields = [];
        $values = [];
        if ($title !== null) {
            $fields[] = "title = ?";
            $values[] = $title;
        }
        if ($desc !== null) {
            $fields[] = "description = ?";
            $values[] = $desc;
        }
        if ($theme !== null) {
            $fields[] = "theme_settings = ?";
            $values[] = $theme;
        }

        if (!$fields) $this->ok(['message' => 'Nada para atualizar']);

        $values[] = $formId;
        $values[] = $userId;

        $sql = "UPDATE forms SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
        $upd = $db->prepare($sql);

        try {
            $upd->execute($values);
            $this->ok(['message' => 'Formulário atualizado']);
        } catch (\Throwable $e) {
            $this->fail('FORM_UPDATE_FAILED', 'Erro ao atualizar', 500, [['message' => $e->getMessage()]]);
        }
    }

    public function publish(array $params): void
    {
        $userId = $this->currentUserId();
        $formId = (int)($params['id'] ?? 0);
        if ($formId <= 0) $this->fail('VALIDATION_ERROR', 'ID inválido', 422);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, slug, is_published FROM forms WHERE id = ? AND user_id = ?");
        $stmt->execute([$formId, $userId]);
        $form = $stmt->fetch();

        if (!$form) $this->fail('FORM_NOT_FOUND', 'Formulário não encontrado', 404);

        if ((int)$form['is_published'] === 1) {
            $this->ok(['message' => 'Já publicado', 'slug' => $form['slug'], 'is_published' => true]);
        }

        $upd = $db->prepare("UPDATE forms SET is_published = 1 WHERE id = ? AND user_id = ?");
        $upd->execute([$formId, $userId]);

        $this->ok([
            'message' => 'Formulário publicado',
            'slug' => $form['slug'],
            'public_url' => rtrim((string)($_ENV['APP_URL'] ?? ''), '/') . '/api/public/forms/' . $form['slug']
        ]);
    }

    public function getPublic(array $params): void
    {
        $slug = (string)($params['slug'] ?? '');
        if ($slug === '') $this->fail('VALIDATION_ERROR', 'Slug inválido', 422);

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
    SELECT id, title, description, theme_settings
    FROM forms
    WHERE slug = ?
      AND is_published = 1
      AND is_archived = 0
");
        $stmt->execute([$slug]);
        $form = $stmt->fetch();

        if (!$form) {
            $this->fail('FORM_NOT_FOUND', 'Formulário não encontrado, desativado ou arquivado', 404);
        }


        $stmtQ = $db->prepare("
    SELECT id, type, label, placeholder, is_required, position, config
    FROM form_questions
    WHERE form_id = ?
      AND is_archived = 0
    ORDER BY position ASC
");
        $stmtQ->execute([(int)$form['id']]);
        $questions = $stmtQ->fetchAll();

        foreach ($questions as &$q) {
            $q['is_required'] = (bool)$q['is_required'];
            $q['config'] = $q['config'] ? json_decode($q['config'], true) : null;

            if ($q['type'] === 'checkbox' || $q['type'] === 'radio') {
                $stmtOpt = $db->prepare("
            SELECT id, option_label, position
            FROM question_options
            WHERE question_id = ?
            ORDER BY position ASC
        ");
                $stmtOpt->execute([(int)$q['id']]);
                $q['options'] = $stmtOpt->fetchAll();
            }
        }


        $form['theme_settings'] = $form['theme_settings'] ? json_decode($form['theme_settings'], true) : null;
        $form['questions'] = $questions;

        $this->ok($form);
    }

    public function submitResponse(array $params): void
    {
        $slug = (string)($params['slug'] ?? '');
        if ($slug === '') $this->fail('VALIDATION_ERROR', 'Slug inválido', 422);

        $db = Database::getInstance()->getConnection();

        // 1) carregar form
        $stmt = $db->prepare("SELECT id FROM forms WHERE slug = ? AND is_published = 1");
        $stmt->execute([$slug]);
        $form = $stmt->fetch();
        if (!$form) $this->fail('FORM_NOT_FOUND', 'Formulário inválido', 404);

        $formId = (int)$form['id'];

        // 2) carregar perguntas + opções
        $qStmt = $db->prepare("
    SELECT *
    FROM form_questions
    WHERE form_id = ?
      AND is_archived = 0
    ORDER BY position ASC
");
        $qStmt->execute([$formId]);
        $questions = $qStmt->fetchAll();


        $questionMap = [];
        $checkboxIds = [];
        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $q['is_required'] = (bool)$q['is_required'];
            $q['config'] = $q['config'] ? json_decode($q['config'], true) : null;
            $questionMap[$qid] = $q;
            if ($q['type'] === 'checkbox') $checkboxIds[] = $qid;
        }

        $optionsByQuestion = [];
        if ($checkboxIds) {
            $in = implode(',', array_fill(0, count($checkboxIds), '?'));
            $oStmt = $db->prepare("SELECT id, question_id, option_label FROM question_options WHERE question_id IN ($in)");
            $oStmt->execute($checkboxIds);
            $opts = $oStmt->fetchAll();

            foreach ($opts as $opt) {
                $qid = (int)$opt['question_id'];
                $oid = (int)$opt['id'];
                $optionsByQuestion[$qid][$oid] = (string)$opt['option_label'];
            }
        }

        // 3) ler payload (aceita dois formatos)
        $data = $this->getJsonBody();
        $answers = $data['answers'] ?? [];

        // formato A: {"answers": {"12":"abc","13":[1,2],"14":{"upload_id":5}}}
        // formato B: {"answers":[{"question_id":12,"value":"abc"},{"question_id":13,"value":[1,2]},{"question_id":14,"upload_id":5}]}
        $normalized = [];

        if (is_array($answers) && isset($answers[0]) && is_array($answers[0]) && array_key_exists('question_id', $answers[0])) {
            foreach ($answers as $row) {
                if (!is_array($row)) continue;
                $qid = (int)($row['question_id'] ?? 0);
                if ($qid <= 0) continue;
                if (array_key_exists('upload_id', $row)) {
                    $normalized[$qid] = ['upload_id' => (int)$row['upload_id']];
                } else {
                    $normalized[$qid] = $row['value'] ?? null;
                }
            }
        } elseif (is_array($answers)) {
            foreach ($answers as $qid => $val) {
                $qid = (int)$qid;
                if ($qid <= 0) continue;
                $normalized[$qid] = $val;
            }
        }


        // 4) validar required + tipos
        $errors = [];

        foreach ($questionMap as $qid => $q) {
            $type = (string)$q['type'];
            $required = (bool)$q['is_required'];

            $hasAnswer = array_key_exists($qid, $normalized);

            if ($required && !$hasAnswer) {
                $errors[] = ['question_id' => $qid, 'message' => 'Pergunta obrigatória'];
                continue;
            }

            if (!$hasAnswer) continue;

            $val = $normalized[$qid];

            if ($type === 'text' || $type === 'long_text') {
                $str = is_string($val) ? trim($val) : '';
                if ($required && $str === '') {
                    $errors[] = ['question_id' => $qid, 'message' => 'Resposta obrigatória'];
                }
                $normalized[$qid] = $str;
            }

            if ($type === 'radio') {
                $optId = 0;
                if (is_array($val) && isset($val['id'])) {
                    $optId = (int)$val['id'];
                } elseif (is_numeric($val) || (is_string($val) && ctype_digit($val))) {
                    $optId = (int)$val;
                }

                if ($required && ($optId <= 0)) {
                    $errors[] = ['question_id' => $qid, 'message' => 'Selecione uma opção'];
                    $normalized[$qid] = null;
                } elseif ($optId > 0) {
                    $o = $db->prepare("SELECT id FROM question_options WHERE id = ? AND question_id = ?");
                    $o->execute([$optId, $qid]);
                    if (!$o->fetch()) {
                        $errors[] = ['question_id' => $qid, 'message' => 'Opção inválida', 'option_id' => $optId];
                        $normalized[$qid] = null;
                    } else {
                        $normalized[$qid] = $optId;
                    }
                } else {
                    $normalized[$qid] = null;
                }
            }

            if ($type === 'checkbox') {
                if (!is_array($val)) {
                    $errors[] = ['question_id' => $qid, 'message' => 'Checkbox deve ser lista'];
                    continue;
                }
                if ($required && count($val) < 1) {
                    $errors[] = ['question_id' => $qid, 'message' => 'Selecione pelo menos 1 opção'];
                    continue;
                }
                // validar opções (IDs)
                $validOpts = $optionsByQuestion[$qid] ?? [];
                foreach ($val as $optId) {
                    $oid = (int)$optId;
                    if ($oid <= 0 || !isset($validOpts[$oid])) {
                        $errors[] = ['question_id' => $qid, 'message' => 'Opção inválida', 'option_id' => $optId];
                    }
                }
                $normalized[$qid] = array_map('intval', $val);
            }

            if ($type === 'upload') {
                $uploadId = null;
                if (is_array($val) && isset($val['upload_id'])) $uploadId = (int)$val['upload_id'];
                if (is_numeric($val)) $uploadId = (int)$val;

                if ($required && (!$uploadId || $uploadId <= 0)) {
                    $errors[] = ['question_id' => $qid, 'message' => 'Upload obrigatório'];
                    continue;
                }

                if ($uploadId && $uploadId > 0) {
                    // checar upload existe e está TEMP e não expirou
                    $u = $db->prepare("SELECT id, storage_key, status, expires_at FROM uploads WHERE id = ?");
                    $u->execute([$uploadId]);
                    $up = $u->fetch();
                    if (!$up) {
                        $errors[] = ['question_id' => $qid, 'message' => 'Upload não encontrado'];
                    } else {
                        if (($up['status'] ?? '') !== 'TEMP') {
                            $errors[] = ['question_id' => $qid, 'message' => 'Upload já utilizado'];
                        }
                        if (!empty($up['expires_at']) && strtotime($up['expires_at']) < time()) {
                            $errors[] = ['question_id' => $qid, 'message' => 'Upload expirado'];
                        }
                    }
                }

                $normalized[$qid] = ['upload_id' => (int)$uploadId];
            }
        }

        // impedir question_id que não pertence ao form
        foreach ($normalized as $qid => $_) {
            if (!isset($questionMap[(int)$qid])) {
                $errors[] = ['question_id' => (int)$qid, 'message' => 'Pergunta não pertence ao formulário'];
            }
        }

        if ($errors) $this->fail('VALIDATION_ERROR', 'Respostas inválidas', 422, $errors);

        // 5) gravar (transação)
        try {
            $db->beginTransaction();

            $stmtRes = $db->prepare("INSERT INTO form_responses (form_id, ip_address, user_agent) VALUES (?, ?, ?)");
            $stmtRes->execute([
                $formId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            $responseId = (int)$db->lastInsertId();

            $stmtAns = $db->prepare("
                INSERT INTO response_answers (response_id, question_id, answer_value, file_path)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($questionMap as $qid => $q) {
                if (!array_key_exists($qid, $normalized)) continue;

                $type = (string)$q['type'];
                $answerValue = null;
                $filePath = null;

                if ($type === 'text' || $type === 'long_text') {
                    $answerValue = (string)$normalized[$qid];
                } elseif ($type === 'checkbox') {
                    $answerValue = json_encode($normalized[$qid], JSON_UNESCAPED_UNICODE);
                } elseif ($type === 'radio') {
                    $optId = (int)($normalized[$qid] ?? 0);
                    $answerValue = $optId > 0 ? (string)$optId : null;
                } elseif ($type === 'upload') {
                    $uploadId = (int)($normalized[$qid]['upload_id'] ?? 0);
                    if ($uploadId > 0) {
                        $u = $db->prepare("SELECT id, storage_key FROM uploads WHERE id = ? FOR UPDATE");
                        $u->execute([$uploadId]);
                        $up = $u->fetch();
                        $answerValue = (string)$uploadId;
                        $filePath = $up ? (string)$up['storage_key'] : null;

                        $upd = $db->prepare("UPDATE uploads SET status='ATTACHED', response_id=? WHERE id=?");
                        $upd->execute([$responseId, $uploadId]);
                    }
                }

                $stmtAns->execute([$responseId, $qid, $answerValue, $filePath]);
            }

            $db->commit();
            $this->ok(['message' => 'Resposta enviada com sucesso', 'response_id' => $responseId], 201);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->fail('SUBMIT_FAILED', 'Erro ao processar resposta', 500, [['message' => $e->getMessage()]]);
        }
    }
}
