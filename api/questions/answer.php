<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$questionId = (int)input('question_id', 0);
$content = trim((string)input('content', ''));

if ($questionId <= 0 || $content === '') {
    json_error('A identificação da pergunta e a resposta são obrigatórias.');
}

$db = db();

try {
    // Verificar se a pergunta existe
    $question = $db->fetch("SELECT * FROM questions WHERE id = ?", [$questionId]);
    if (!$question) {
        json_error('Pergunta não encontrada.', 404);
    }

    // Se o respondente for um administrador ou verificado, pode ter is_expert = 1
    $isExpert = $user['is_admin'] || $user['is_verified'] ? 1 : 0;

    $db->query(
        "INSERT INTO answers (question_id, user_id, content, is_expert) VALUES (?, ?, ?, ?)",
        [$questionId, $user['id'], $content, $isExpert]
    );

    // Notificar o autor da pergunta se não for o próprio autor a responder
    if ((int)$question['user_id'] !== (int)$user['id']) {
        $db->query(
            "INSERT INTO notifications (user_id, sender_id, type, entity_id) VALUES (?, ?, 'answer', ?)",
            [$question['user_id'], $user['id'], $questionId]
        );
    }

    $answer = [
        'user_id' => $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'avatar_url' => get_avatar_url($user['avatar_url'], $user['name']),
        'content' => sanitize($content),
        'is_expert' => $isExpert,
        'created_at' => date('Y-m-d H:i:s')
    ];

    json_ok(['answer' => $answer], 'Resposta enviada com sucesso!');
} catch (PDOException $e) {
    json_error('Erro técnico ao submeter resposta: ' . $e->getMessage(), 500);
}
