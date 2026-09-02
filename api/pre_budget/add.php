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

$projectId = (int)input('project_id', 0);
$name      = trim((string)input('name', ''));
$price     = (float)input('price', 0.00);
$quantity  = (float)input('quantity', 1.00);
$unit      = trim((string)input('unit', 'un'));
$phase     = trim((string)input('phase', ''));

if ($projectId <= 0) {
    json_error('ID de projeto inválido.');
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
    // 1. Validar se o utilizador é gestor ou dono do projeto
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores do projeto podem adicionar itens ao pré-orçamento.', 403);
    }

    // 2. Inserir o item de pré-orçamento
    $inserted = $db->execute(
        "INSERT INTO pre_budgets (project_id, user_id, name, price, quantity, unit, phase) 
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$projectId, $user['id'], $name, $price, $quantity, $unit, $phase]
    );

    if (!$inserted) {
        json_error('Erro ao registar o item de planeamento.');
    }

    set_flash_message('success', 'Item planeado adicionado com sucesso!');
    json_ok([], 'Item adicionado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao registar o item de planeamento.', 500);
}
