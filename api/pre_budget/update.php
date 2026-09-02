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

$id        = (int)input('id', 0);
$name      = trim((string)input('name', ''));
$price     = (float)input('price', 0.00);
$quantity  = (float)input('quantity', 1.00);
$unit      = trim((string)input('unit', 'un'));
$phase     = trim((string)input('phase', ''));

if ($id <= 0) {
    json_error('ID de item inválido.');
}

if ($name === '') {
    json_error('O nome do material ou serviço é obrigatório.');
}

if ($price < 0.00) {
    json_error('O preço estimado não pode ser negativo.');
}

if ($quantity <= 0.00) {
    json_error('A quantidade estimada deve ser superior a zero.');
}

if ($phase === '') {
    json_error('A fase associada é obrigatória.');
}

$db = db();

try {
    // 1. Procurar o item existente
    $item = $db->fetch("SELECT id, project_id FROM pre_budgets WHERE id = ?", [$id]);
    if (!$item) {
        json_error('Item de pré-orçamento não encontrado.', 404);
    }

    $projectId = (int)$item['project_id'];

    // 2. Validar se o utilizador é gestor ou dono do projeto
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores do projeto podem editar o pré-orçamento.', 403);
    }

    // 3. Atualizar o item
    $updated = $db->execute(
        "UPDATE pre_budgets SET name = ?, price = ?, quantity = ?, unit = ?, phase = ? WHERE id = ?",
        [$name, $price, $quantity, $unit, $phase, $id]
    );

    set_flash_message('success', 'Item de pré-orçamento atualizado com sucesso!');
    json_ok([], 'Item atualizado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao atualizar o item de pré-orçamento.', 500);
}
