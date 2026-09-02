<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$db = db();

try {
    // Buscar todos os projetos que o utilizador é proprietário, gestor ou observador
    $projects = $db->fetchAll(
        "SELECT DISTINCT p.*, COALESCE(pm.role, 'owner') AS role,
                (SELECT SUM(price * quantity) FROM expenses WHERE project_id = p.id AND deleted_at IS NULL) AS total_spent,
                (SELECT SUM(price * quantity) FROM pre_budgets WHERE project_id = p.id) AS total_planned,
                (SELECT SUM(amount * exchange_rate) FROM project_funds WHERE project_id = p.id) AS total_funds
         FROM projects p
         LEFT JOIN project_managers pm ON p.id = pm.project_id AND pm.user_id = ?
         WHERE p.user_id = ? OR pm.user_id = ?
         ORDER BY p.created_at DESC",
        [$user['id'], $user['id'], $user['id']]
    );

    // Formatar cover URLs
    foreach ($projects as $key => $p) {
        if ($p['cover_image_url']) {
            $projects[$key]['cover_image_url'] = APP_URL . '/' . ltrim($p['cover_image_url'], '/');
        } else {
            $projects[$key]['cover_image_url'] = null;
        }
        
        // Decodificar fases JSON se houver
        if ($p['phases']) {
            $projects[$key]['phases'] = json_decode($p['phases'], true);
        } else {
            $projects[$key]['phases'] = [];
        }
    }

    json_ok(['projects' => $projects], 'Projetos carregados.');

} catch (PDOException $e) {
    json_error('Erro técnico ao procurar projetos.', 500);
}
