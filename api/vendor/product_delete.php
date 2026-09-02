<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido ou expirado.', 403);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$productId = (int)input('id', 0);
if ($productId <= 0) {
    json_error('ID de produto inválido.');
}

$db = db();

try {
    $existing = $db->fetch("SELECT id, user_id FROM marketplace_products WHERE id = ?", [$productId]);
    if (!$existing) {
        json_error('Produto não encontrado.', 404);
    }

    $isOwner = (int)$existing['user_id'] === (int)$user['id'];
    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

    if (!$isOwner && !$isAdmin) {
        json_error('Permissão negada. Apenas o proprietário ou um administrador pode remover este produto.', 403);
    }

    $db->execute("DELETE FROM marketplace_products WHERE id = ?", [$productId]);

    set_flash_message('success', 'Produto eliminado da loja com sucesso!');
    json_ok([], 'Produto eliminado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao eliminar o produto.', 500);
}
