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
if ($postId <= 0) {
    json_error('ID do post inválido.');
}

$db = db();

try {
    // Verificar se o post existe e obter o seu dono
    $post = $db->fetch("SELECT user_id FROM posts WHERE id = ?", [$postId]);
    if (!$post) {
        json_error('Publicação não encontrada.', 404);
    }

    // Verificar se já gostou
    $like = $db->fetch("SELECT id FROM likes WHERE post_id = ? AND user_id = ?", [$postId, $user['id']]);

    if ($like) {
        // Remover gosto (Unlike)
        $db->execute("DELETE FROM likes WHERE id = ?", [$like['id']]);
        $liked = false;
        
        // Remover notificação correspondente
        $db->execute(
            "DELETE FROM notifications WHERE user_id = ? AND sender_id = ? AND type = 'like' AND entity_id = ?",
            [$post['user_id'], $user['id'], $postId]
        );
    } else {
        // Adicionar gosto (Like)
        $db->execute("INSERT INTO likes (post_id, user_id) VALUES (?, ?)", [$postId, $user['id']]);
        $liked = true;

        // Criar notificação se não for gosto no próprio post
        if ((int)$post['user_id'] !== (int)$user['id']) {
            $db->execute(
                "INSERT INTO notifications (user_id, sender_id, type, entity_id) VALUES (?, ?, 'like', ?)",
                [$post['user_id'], $user['id'], $postId]
            );
        }
    }

    // Contar total de gostos atualizado
    $count = $db->fetch("SELECT COUNT(*) AS total FROM likes WHERE post_id = ?", [$postId]);

    json_ok([
        'liked' => $liked,
        'likes_count' => (int)$count['total']
    ], $liked ? 'Gosto registado.' : 'Gosto removido.');

} catch (PDOException $e) {
    json_error('Erro técnico ao registar gosto.', 500);
}
