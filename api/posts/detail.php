<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$postId = (int)input('post_id', 0);
if ($postId <= 0) {
    json_error('ID de publicação inválido.');
}

$db = db();

try {
    $post = $db->fetch(
        "SELECT p.*, pr.name, pr.username, pr.avatar_url
         FROM posts p
         JOIN profiles pr ON p.user_id = pr.id
         WHERE p.id = ?",
         [$postId]
    );

    if (!$post) {
        json_error('Publicação não encontrada.', 404);
    }

    // Obter comentários
    $comments = $db->fetchAll(
        "SELECT c.*, pr.name, pr.username, pr.avatar_url 
         FROM comments c 
         JOIN profiles pr ON c.user_id = pr.id 
         WHERE c.post_id = ? 
         ORDER BY c.created_at ASC",
        [$postId]
    );

    foreach ($comments as $k => $c) {
        $comments[$k]['avatar_url'] = get_avatar_url($c['avatar_url'], $c['name']);
    }

    $post['comments'] = $comments;
    $post['avatar_url'] = get_avatar_url($post['avatar_url'], $post['name']);

    json_ok(['post' => $post], 'Detalhes da publicação carregados.');

} catch (PDOException $e) {
    json_error('Erro técnico ao aceder à publicação: ' . $e->getMessage(), 500);
}
