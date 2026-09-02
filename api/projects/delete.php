<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

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

$projectId = (int)input('project_id');
if ($projectId <= 0) {
    $projectId = (int)input('id', 0);
}

if ($projectId <= 0) {
    json_error('ID de projeto inválido.');
}

$db = db();

try {
    // 1. Verificar permissão de eliminação (proprietário ou admin)
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    $isOwner = $manager && $manager['role'] === 'owner';
    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

    if (!$isOwner && !$isAdmin) {
        json_error('Apenas o proprietário principal da obra ou um administrador pode eliminar este projeto.', 403);
    }

    // Obter capa do projeto para limpar
    $project = $db->fetch("SELECT cover_image_url FROM projects WHERE id = ?", [$projectId]);
    if ($project && $project['cover_image_url'] && file_exists(ROOT_DIR . '/' . $project['cover_image_url'])) {
        unlink(ROOT_DIR . '/' . $project['cover_image_url']);
    }

    // 2. Eliminar projeto (o cascade limpa automaticamente project_managers, expenses, project_funds, etc)
    $db->execute("DELETE FROM projects WHERE id = ?", [$projectId]);

    set_flash_message('success', 'Projeto de obra eliminado com sucesso.');
    json_ok([], 'Projeto eliminado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao eliminar projeto de obra.', 500);
}
