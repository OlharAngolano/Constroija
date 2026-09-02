<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/upload.php';

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

$projectId   = (int)input('project_id', 0);
$title       = trim((string)input('title', ''));
$description = trim((string)input('description', ''));
$location    = trim((string)input('location', ''));
$isPublic    = (int)input('is_public', 0) === 1 ? 1 : 0;
$budget      = (float)input('budget', 0.00);
$status      = trim((string)input('status', 'planning'));
$startDate   = input('start_date') ?: null;
$endDate     = input('end_date') ?: null;

if ($projectId <= 0) {
    json_error('ID de projeto inválido.');
}

if ($title === '') {
    json_error('O título do projeto é obrigatório.');
}

$db = db();

try {
    // 1. Validar cargo e permissão na equipa do projeto
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores do projeto podem editar definições.', 403);
    }

    // Obter dados atuais
    $project = $db->fetch("SELECT cover_image_url FROM projects WHERE id = ?", [$projectId]);
    if (!$project) {
        json_error('Projeto não encontrado.', 404);
    }

    $coverImageUrl = $project['cover_image_url'];

    // Processar novo upload se enviado
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $uploadedPath = Upload::file($_FILES['cover'], 'projects');
        if ($uploadedPath) {
            // Remover imagem anterior se aplicável
            if ($coverImageUrl && file_exists(ROOT_DIR . '/' . $coverImageUrl)) {
                unlink(ROOT_DIR . '/' . $coverImageUrl);
            }
            $coverImageUrl = $uploadedPath;
        }
    }

    // 2. Executar atualização
    $db->execute(
        "UPDATE projects SET 
            title = ?, 
            description = ?, 
            location = ?, 
            is_public = ?, 
            cover_image_url = ?, 
            budget = ?, 
            status = ?, 
            start_date = ?, 
            end_date = ? 
         WHERE id = ?",
        [$title, $description, $location, $isPublic, $coverImageUrl, $budget, $status, $startDate, $endDate, $projectId]
    );

    set_flash_message('success', 'Projeto atualizado com sucesso!');
    json_ok([], 'Projeto atualizado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao atualizar projeto.', 500);
}
