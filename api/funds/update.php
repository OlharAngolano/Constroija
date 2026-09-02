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

$fundId       = (int)input('id', 0);
$amount       = (float)input('amount', 0.00);
$currency     = trim((string)input('currency', 'AOA'));
$exchangeRate = (float)input('exchange_rate', 1.0000);
$description  = trim((string)input('description', ''));

if ($fundId <= 0) {
    json_error('ID de aporte inválido.');
}

if ($amount <= 0.00) {
    json_error('O valor do fundo tem de ser superior a zero.');
}

if ($exchangeRate <= 0.0000) {
    json_error('A taxa de câmbio tem de ser superior a zero.');
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
        json_error('Permissão negada. Apenas gestores do projeto podem editar fundos.', 403);
    }

    // 3. Atualizar no banco de dados
    $updated = $db->execute(
        "UPDATE project_funds 
         SET amount = ?, currency = ?, exchange_rate = ?, description = ? 
         WHERE id = ?",
        [$amount, $currency, $exchangeRate, $description, $fundId]
    );

    if (!$updated) {
        json_error('Erro ao atualizar o aporte financeiro.');
    }

    set_flash_message('success', 'Aporte financeiro atualizado com sucesso!');
    json_ok([], 'Aporte atualizado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao atualizar aporte financeiro.', 500);
}
