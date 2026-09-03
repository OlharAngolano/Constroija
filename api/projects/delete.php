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

// (CJ-13) Confirmação reforçada no servidor: o cliente tem de declarar
// explicitamente a intenção de eliminação definitiva (o modal da interface
// pede a confirmação antes de enviar este parâmetro).
if ((int)input('confirm', 0) !== 1) {
    json_error('Confirmação de eliminação necessária. Envie confirm=1 para eliminar definitivamente a obra.', 400);
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

    // 2. Eliminação definitiva dentro de transação: se algo falhar, nada é apagado.
    //    A capa física só é removida DEPOIS do commit (CJ-13).
    $project = $db->fetch("SELECT cover_image_url FROM projects WHERE id = ?", [$projectId]);
    if (!$project) {
        json_error('Projeto de obra não encontrado.', 404);
    }

    $db->beginTransaction();
    $deleted = $db->execute("DELETE FROM projects WHERE id = ?", [$projectId]);
    if (!$deleted) {
        $db->rollBack();
        json_error('Erro técnico ao eliminar projeto de obra.', 500);
    }
    $db->commit();

    // 3. Após commit: limpeza física da capa + auditoria (CJ-13)
    $coverPath = $project['cover_image_url'] ?? '';
    if ($coverPath !== '' && strpos($coverPath, '..') === false && file_exists(ROOT_DIR . '/' . $coverPath)) {
        @unlink(ROOT_DIR . '/' . $coverPath);
    }
    error_log(sprintf(
        'CJ-13: projeto #%d eliminado definitivamente pelo utilizador #%d (admin=%d)',
        $projectId,
        (int)$user['id'],
        (int)$isAdmin
    ));

    set_flash_message('success', 'Projeto de obra eliminado com sucesso.');
    json_ok([], 'Projeto eliminado.');

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    json_internal_error('Erro técnico ao eliminar projeto de obra: ', $e);
}
