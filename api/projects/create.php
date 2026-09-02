<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/upload.php';

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

$title       = trim((string)input('title', ''));
$description = trim((string)input('description', ''));
$location    = trim((string)input('location', ''));
$isPublic    = (int)input('is_public', 0) === 1 ? 1 : 0;
$budget      = (float)input('budget', 0.00);
$startDate   = input('start_date') ?: null;
$endDate     = input('end_date') ?: null;

// Fases personalizáveis (espera-se um array em JSON, ex: ["Fundação","Paredes"])
$rawPhases = input('phases');
$phasesList = ['Fundação', 'Estrutura', 'Alvenaria', 'Cobertura', 'Acabamentos'];

if (!empty($rawPhases)) {
    if (is_array($rawPhases)) {
        $phasesList = array_map('trim', $rawPhases);
    } elseif (is_string($rawPhases)) {
        $decoded = json_decode($rawPhases, true);
        if (is_array($decoded)) {
            $phasesList = array_map('trim', $decoded);
        } else {
            // Suporte para vírgula
            $phasesList = array_filter(array_map('trim', explode(',', $rawPhases)));
        }
    }
}
$phasesJson = json_encode($phasesList, JSON_UNESCAPED_UNICODE);

if ($title === '') {
    json_error('O título do projeto é obrigatório.');
}

$db = db();
$coverImageUrl = null;

// Processar upload de imagem de capa opcional
if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
    $uploadedPath = Upload::file($_FILES['cover'], 'projects');
    if ($uploadedPath) {
        $coverImageUrl = $uploadedPath;
    } else {
        json_error('Falha no upload da imagem de capa. Escolha um formato válido (JPG, PNG, WEBP).');
    }
}

try {
    // Iniciar Transação
    $db->beginTransaction();

    // 1. Inserir projeto
    $db->execute(
        "INSERT INTO projects (user_id, title, description, location, is_public, cover_image_url, budget, phases, start_date, end_date) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$user['id'], $title, $description, $location, $isPublic, $coverImageUrl, $budget, $phasesJson, $startDate, $endDate]
    );

    $projectId = (int)$db->lastInsertId();

    // 2. Associar criador como 'owner' na equipa de gestão
    $db->execute(
        "INSERT INTO project_managers (project_id, user_id, role) VALUES (?, ?, 'owner')",
        [$projectId, $user['id']]
    );

    // Confirmar Transação
    $db->commit();

    set_flash_message('success', 'Projeto de obra criado com sucesso!');
    json_ok(['project_id' => $projectId], 'Projeto de obra criado.');

} catch (PDOException $e) {
    $db->rollBack();
    json_error('Erro técnico ao registar o projeto no servidor: ' . $e->getMessage(), 500);
}
