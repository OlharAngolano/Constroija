<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Aceita DELETE ou POST (com _method=DELETE fallback)
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$db = db();

try {
    // Obter post para verificação de permissões
    $post = $db->fetch("SELECT user_id, file_url FROM posts WHERE id = ?", [$postId]);
    if (!$post) {
        json_error('Publicação não encontrada.', 404);
    }

    // Apenas o dono ou um administrador pode eliminar o post
    $isOwner = (int)$post['user_id'] === (int)$user['id'];
    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

    if (!$isOwner && !$isAdmin) {
        json_error('Permissão negada. Apenas o proprietário ou um administrador pode eliminar este post.', 403);
    }

    // Eliminar ficheiro físico se existir
    if ($post['file_url'] && file_exists(ROOT_DIR . '/' . $post['file_url'])) {
        unlink(ROOT_DIR . '/' . $post['file_url']);
    }

    // Eliminar do banco de dados (cascade limpa likes e comentários automaticamente)
    $db->execute("DELETE FROM posts WHERE id = ?", [$postId]);

    json_ok([], 'Publicação eliminada com sucesso.');

} catch (PDOException $e) {
    json_error('Erro técnico ao eliminar publicação.', 500);
}
