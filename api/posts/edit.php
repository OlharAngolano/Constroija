<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Aceita apenas método POST
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

$postId = (int)input('post_id');
if ($postId <= 0) {
    $postId = (int)input('id', 0);
}

if ($postId <= 0) {
    json_error('ID de publicação inválido.');
}

$content = trim((string)input('content', ''));
if ($content === '') {
    json_error('O conteúdo da publicação não pode estar vazio.');
}

$db = db();

try {
    // Obter post para verificação de permissões
    $post = $db->fetch("SELECT * FROM posts WHERE id = ?", [$postId]);
    if (!$post) {
        json_error('Publicação não encontrada.', 404);
    }

    // Apenas o dono ou um administrador pode editar o post
    $isOwner = (int)$post['user_id'] === (int)$user['id'];
    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

    if (!$isOwner && !$isAdmin) {
        json_error('Permissão negada. Apenas o proprietário ou um administrador pode editar este post.', 403);
    }

    // Atualizar conteúdo da publicação na base de dados
    $db->execute(
        "UPDATE posts SET content = ? WHERE id = ?",
        [$content, $postId]
    );

    // Obter dados atualizados do post
    $updatedPost = $db->fetch("SELECT * FROM posts WHERE id = ?", [$postId]);

    json_ok(['post' => $updatedPost], 'Publicação atualizada com sucesso.');

} catch (PDOException $e) {
    json_error('Erro técnico ao atualizar publicação.', 500);
}
