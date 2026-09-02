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

$projectId    = (int)input('project_id', 0);
$amount       = (float)input('amount', 0.00);
$currency     = trim((string)input('currency', 'AOA'));
$exchangeRate = (float)input('exchange_rate', 1.0000);
$description  = trim((string)input('description', ''));

if ($projectId <= 0) {
    json_error('ID de projeto inválido.');
}

if ($amount <= 0.00) {
    json_error('O valor do fundo tem de ser superior a zero.');
}

if ($exchangeRate <= 0.0000) {
    json_error('A taxa de câmbio tem de ser superior a zero.');
}

$db = db();

try {
    // 1. Validar se o utilizador tem permissões de gestão (proprietário ou gestor)
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores do projeto podem adicionar fundos.', 403);
    }

    // 2. Registar aporte de fundos
    $inserted = $db->execute(
        "INSERT INTO project_funds (project_id, user_id, amount, currency, exchange_rate, description) 
         VALUES (?, ?, ?, ?, ?, ?)",
        [$projectId, $user['id'], $amount, $currency, $exchangeRate, $description]
    );

    if (!$inserted) {
        json_error('Erro ao registar o aporte financeiro.');
    }

    set_flash_message('success', 'Aporte de fundos adicionado com sucesso!');
    json_ok([], 'Aporte adicionado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao registar entrada de fundos.', 500);
}
