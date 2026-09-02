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

$id = (int)input('id', 0);
if ($id <= 0) {
    json_error('ID de item inválido.');
}

$db = db();

try {
    // 1. Procurar o item e validar a sua existência
    $item = $db->fetch("SELECT * FROM pre_budgets WHERE id = ?", [$id]);
    if (!$item) {
        json_error('Item de planeamento não encontrado.');
    }

    $projectId = (int)$item['project_id'];

    // 2. Validar se o utilizador é gestor ou dono do projeto
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores do projeto podem remover itens do pré-orçamento.', 403);
    }

    // 3. Apagar o registo
    $deleted = $db->execute("DELETE FROM pre_budgets WHERE id = ?", [$id]);
    if (!$deleted) {
        json_error('Erro ao remover o item de planeamento.');
    }

    set_flash_message('success', 'Item removido do planeamento com sucesso!');
    json_ok([], 'Item removido.');

} catch (PDOException $e) {
    json_error('Erro técnico ao remover o item de planeamento.', 500);
}
