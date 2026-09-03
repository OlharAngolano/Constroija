<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$query = input('q') ? trim((string)input('q')) : '';

if ($query === '') {
    json_ok(['users' => [], 'projects' => []], 'Pesquisa vazia.');
}

$db = db();

try {
    // 1. Procurar Utilizadores por nome ou username
    $users = $db->fetchAll(
        "SELECT id, name, username, avatar_url 
         FROM profiles 
         WHERE (name LIKE ? OR username LIKE ?) AND status = 'active'
         LIMIT 5",
        ['%' . $query . '%', '%' . $query . '%']
    );

    // Formatar avatars
    foreach ($users as $key => $u) {
        $users[$key]['avatar_url'] = get_avatar_url($u['avatar_url'], $u['name']);
    }

    // 2. Procurar Projetos onde o utilizador é membro
    $projects = $db->fetchAll(
        "SELECT p.id, p.title, p.location 
         FROM projects p
         JOIN project_managers pm ON p.id = pm.project_id
         WHERE pm.user_id = ? AND (p.title LIKE ? OR p.location LIKE ?)
         LIMIT 5",
        [$user['id'], '%' . $query . '%', '%' . $query . '%']
    );

    json_ok([
        'users' => $users,
        'projects' => $projects
    ], 'Pesquisa concluída.');

} catch (PDOException $e) {
    json_internal_error('Erro técnico na pesquisa: ', $e);
}
