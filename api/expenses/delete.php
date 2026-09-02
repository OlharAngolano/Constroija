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

$expenseId = (int)input('expense_id');
if ($expenseId <= 0) {
    $expenseId = (int)input('id', 0);
}

if ($expenseId <= 0) {
    json_error('ID de despesa inválido.');
}

$db = db();

try {
    // Obter despesa para validação
    $expense = $db->fetch("SELECT project_id, name FROM expenses WHERE id = ? AND deleted_at IS NULL", [$expenseId]);
    if (!$expense) {
        json_error('Despesa não encontrada.', 404);
    }

    $projectId = (int)$expense['project_id'];

    // 1. Validar permissões (apenas proprietário ou gestor)
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores podem eliminar despesas.', 403);
    }

    // 2. Soft-delete: atualizar deleted_at para a data atual
    $now = date('Y-m-d H:i:s');
    $db->execute("UPDATE expenses SET deleted_at = ? WHERE id = ?", [$now, $expenseId]);

    set_flash_message('success', "Compra \"{$expense['name']}\" anulada com sucesso!");
    json_ok([], 'Compra anulada.');

} catch (PDOException $e) {
    json_error('Erro técnico ao remover despesa.', 500);
}
