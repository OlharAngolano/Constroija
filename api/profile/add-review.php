<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$projectId = (int)input('project_id', 0);
$profileId = (int)input('profile_id', 0);
$rating    = (int)input('rating', 0);
$comment   = trim((string)input('comment', ''));

if ($projectId <= 0 || $profileId <= 0 || $rating < 1 || $rating > 5) {
    json_error('Dados de avaliação incompletos ou inválidos. A pontuação deve ser de 1 a 5 estrelas.');
}

if ($profileId === $user['id']) {
    json_error('Não pode avaliar-se a si próprio.');
}

$db = db();

try {
    // 1. Validar se o projeto existe e se o status é 'completed'
    $project = $db->fetch(
        "SELECT user_id, status FROM projects WHERE id = ?",
        [$projectId]
    );

    if (!$project) {
        json_error('Projeto não encontrado.', 404);
    }

    if ($project['status'] !== 'completed') {
        json_error('Apenas projetos concluídos podem ser avaliados.');
    }

    // 2. O utilizador atual (autor da review) deve ser o proprietário do projeto (role = 'owner')
    $ownerCheck = $db->fetch(
        "SELECT id FROM project_managers WHERE project_id = ? AND user_id = ? AND role = 'owner'",
        [$projectId, $user['id']]
    );

    if (!$ownerCheck) {
        json_error('Acesso negado. Apenas o proprietário da obra pode avaliar colaboradores.', 403);
    }

    // 3. O profissional avaliado deve ser colaborador (project_managers) no mesmo projeto
    $collaboratorCheck = $db->fetch(
        "SELECT id FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $profileId]
    );

    if (!$collaboratorCheck) {
        json_error('Este profissional não consta como colaborador na sua obra.', 403);
    }

    // 4. Garantir que não existe uma avaliação prévia deste proprietário para este profissional na mesma obra
    $existingReview = $db->fetch(
        "SELECT id FROM reviews WHERE project_id = ? AND reviewer_id = ? AND profile_id = ?",
        [$projectId, $user['id'], $profileId]
    );

    if ($existingReview) {
        json_error('Já avaliou este profissional para esta obra.', 409);
    }

    // 5. Inserir a avaliação
    $db->execute(
        "INSERT INTO reviews (project_id, reviewer_id, profile_id, rating, comment) VALUES (?, ?, ?, ?, ?)",
        [$projectId, $user['id'], $profileId, $rating, $comment !== '' ? $comment : null]
    );

    // Enviar notificação ao profissional avaliado
    $db->execute(
        "INSERT INTO notifications (user_id, sender_id, type, entity_id) VALUES (?, ?, 'follow', ?)",
        [$profileId, $user['id'], $projectId]
    );

    set_flash_message('success', 'Avaliação técnica submetida com sucesso!');
    json_ok([], 'Avaliação registada.');

} catch (PDOException $e) {
    json_error('Erro na base de dados ao registar avaliação: ' . $e->getMessage(), 500);
}
