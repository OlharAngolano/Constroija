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

$relationId = (int)input('id', 0);
if ($relationId <= 0) {
    json_error('ID de relação de colaborador inválido.');
}

$db = db();

try {
    // 1. Obter informações da relação para saber o projeto e o utilizador afetado
    $relation = $db->fetch(
        "SELECT pm.project_id, pm.user_id, pm.role, pr.name 
         FROM project_managers pm
         JOIN profiles pr ON pm.user_id = pr.id
         WHERE pm.id = ?",
        [$relationId]
    );

    if (!$relation) {
        json_error('Colaborador não encontrado nesta obra.', 404);
    }

    $projectId = (int)$relation['project_id'];
    $targetUserId = (int)$relation['user_id'];

    // 2. Validar que o utilizador atual é o proprietário (owner) do projeto
    $checkOwner = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$checkOwner || $checkOwner['role'] !== 'owner') {
        json_error('Acesso negado. Apenas o proprietário da obra pode remover colaboradores.', 403);
    }

    // 3. Impedir que o proprietário se remova a si próprio (ou remova outro proprietário)
    if ($relation['role'] === 'owner') {
        json_error('Não é possível remover o proprietário da obra da equipa.');
    }

    // 4. Remover colaborador
    $db->execute("DELETE FROM project_managers WHERE id = ?", [$relationId]);

    // 5. Enviar uma notificação ao utilizador removido
    $db->execute(
        "INSERT INTO notifications (user_id, sender_id, type, entity_id) VALUES (?, ?, 'follow', ?)",
        [$targetUserId, $user['id'], $projectId]
    );

    set_flash_message('success', "{$relation['name']} foi removido da equipa da obra.");
    json_ok([], 'Colaborador removido da equipa com sucesso.');

} catch (PDOException $e) {
    json_internal_error('Erro técnico ao remover colaborador: ', $e);
}
