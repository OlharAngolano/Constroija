<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$postId = (int)input('post_id', 0);
$content = trim((string)input('content', ''));

if ($postId <= 0) {
    json_error('ID de publicação inválido.');
}

if ($content === '') {
    json_error('O comentário não pode ser enviado vazio.');
}

$db = db();

try {
    // Verificar se o post existe e obter o proprietário
    $post = $db->fetch("SELECT user_id FROM posts WHERE id = ?", [$postId]);
    if (!$post) {
        json_error('Publicação não encontrada.', 404);
    }

    // Inserir comentário
    $inserted = $db->execute(
        "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)",
        [$postId, $user['id'], $content]
    );

    if (!$inserted) {
        json_error('Erro ao registar o comentário.');
    }

    $commentId = $db->lastInsertId();

    // Notificar proprietário do post se não for o próprio utilizador
    if ((int)$post['user_id'] !== (int)$user['id']) {
        $db->execute(
            "INSERT INTO notifications (user_id, sender_id, type, entity_id) VALUES (?, ?, 'comment', ?)",
            [$post['user_id'], $user['id'], $postId]
        );
    }

    // Obter dados populados do comentário criado
    $comment = $db->fetch(
        "SELECT c.*, pr.name, pr.username, pr.avatar_url 
         FROM comments c 
         JOIN profiles pr ON c.user_id = pr.id 
         WHERE c.id = ?",
        [$commentId]
    );

    $comment['avatar_url'] = get_avatar_url($comment['avatar_url'], $comment['name']);

    json_ok(['comment' => $comment], 'Comentário publicado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao registar o comentário.', 500);
}
