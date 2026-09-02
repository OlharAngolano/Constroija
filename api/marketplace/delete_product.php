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

$id = (int)input('id', 0);
if ($id <= 0) {
    json_error('ID de produto inválido.');
}

$db = db();

try {
    // 1. Procurar o produto
    $product = $db->fetch("SELECT id, user_id FROM marketplace_products WHERE id = ?", [$id]);
    if (!$product) {
        json_error('Produto não encontrado.', 404);
    }

    // 2. Verificar se o utilizador é o criador ou admin
    $isOwner = (int)$product['user_id'] === (int)$user['id'];
    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

    if (!$isOwner && !$isAdmin) {
        json_error('Permissão negada. Apenas o criador ou um administrador pode remover este anúncio.', 403);
    }

    // 3. Eliminar o produto
    $db->execute("DELETE FROM marketplace_products WHERE id = ?", [$id]);

    set_flash_message('success', 'Anúncio removido com sucesso!');
    json_ok([], 'Anúncio eliminado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao eliminar o anúncio.', 500);
}
