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
$username  = trim((string)input('username', ''));
$role      = trim((string)input('role', 'manager'));

if ($projectId <= 0 || $username === '') {
    json_error('ID de projeto e username são campos obrigatórios.');
}

if (!in_array($role, ['manager', 'viewer'])) {
    $role = 'manager';
}

$db = db();

try {
    // 1. Validar que o utilizador atual é o proprietário (owner) do projeto
    $checkOwner = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$checkOwner || $checkOwner['role'] !== 'owner') {
        json_error('Acesso negado. Apenas o proprietário da obra pode convidar colaboradores.', 403);
    }

    // 2. Procurar utilizador a convidar pelo username ou ID
    $invitee = $db->fetch(
        "SELECT id, name FROM profiles WHERE (username = ? OR id = ?) AND status = 'active'",
        [$username, $username]
    );

    if (!$invitee) {
        json_error('Utilizador não encontrado ou inativo no Constrói Já.');
    }

    if ((int)$invitee['id'] === $user['id']) {
        json_error('Não pode convidar-se a si próprio.');
    }

    // 3. Verificar se já é colaborador do projeto
    $existing = $db->fetch(
        "SELECT id FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $invitee['id']]
    );

    if ($existing) {
        json_error('Este utilizador já faz parte da equipa deste projeto.');
    }

    // 4. Inserir novo colaborador
    $db->execute(
        "INSERT INTO project_managers (project_id, user_id, role) VALUES (?, ?, ?)",
        [$projectId, $invitee['id'], $role]
    );

    // 5. Enviar uma notificação ao utilizador convidado
    $db->execute(
        "INSERT INTO notifications (user_id, sender_id, type, entity_id) VALUES (?, ?, 'follow', ?)",
        [$invitee['id'], $user['id'], $projectId] // Usamos o tipo follow de forma flexível ou tratamos na UI
    );

    set_flash_message('success', "{$invitee['name']} foi adicionado à equipa da obra.");
    json_ok([], 'Colaborador adicionado com sucesso.');

} catch (PDOException $e) {
    json_internal_error('Erro técnico ao adicionar colaborador: ', $e);
}
