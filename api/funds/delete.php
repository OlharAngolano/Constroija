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

$fundId = (int)input('id', 0);
if ($fundId <= 0) {
    json_error('ID de aporte inválido.');
}

$db = db();

try {
    // 1. Procurar o aporte e verificar se existe
    $fund = $db->fetch("SELECT * FROM project_funds WHERE id = ?", [$fundId]);
    if (!$fund) {
        json_error('Aporte não encontrado.', 404);
    }

    $projectId = (int)$fund['project_id'];

    // 2. Validar se o utilizador tem permissão de proprietário ou gestor daquele projeto
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores do projeto podem eliminar fundos.', 403);
    }

    // 3. Eliminar do banco de dados
    $deleted = $db->execute("DELETE FROM project_funds WHERE id = ?", [$fundId]);

    if (!$deleted) {
        json_error('Erro ao eliminar o aporte financeiro.');
    }

    set_flash_message('success', 'Aporte financeiro eliminado com sucesso!');
    json_ok([], 'Aporte eliminado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao eliminar aporte financeiro.', 500);
}
