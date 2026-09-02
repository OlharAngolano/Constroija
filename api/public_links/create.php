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
if ($projectId <= 0) {
    json_error('ID do projeto inválido.');
}

$db = db();

try {
    // Verificar se o utilizador é o criador do projeto (owner) ou se tem permissões de owner/manager em project_managers
    $projectCheck = $db->fetch(
        "SELECT p.id 
         FROM projects p 
         LEFT JOIN project_managers pm ON p.id = pm.project_id AND pm.user_id = ?
         WHERE p.id = ? AND (p.user_id = ? OR pm.role IN ('owner', 'manager'))",
        [$user['id'], $projectId, $user['id']]
    );

    if (!$projectCheck) {
        json_error('Não tem permissões suficientes para gerar um link de partilha deste projeto.', 403);
    }

    // Gerar um token aleatório e seguro
    $token = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    // Inserir na base de dados
    $db->query(
        "INSERT INTO public_links (project_id, token, expires_at) VALUES (?, ?, ?)",
        [$projectId, $token, $expiresAt]
    );

    $shareUrl = APP_URL . '/report?token=' . $token;

    json_ok([
        'token' => $token,
        'expires_at' => $expiresAt,
        'share_url' => $shareUrl
    ], 'Link de partilha gerado com sucesso! Válido por 7 dias.');

} catch (PDOException $e) {
    json_error('Erro técnico ao gerar link de partilha: ' . $e->getMessage(), 500);
}
