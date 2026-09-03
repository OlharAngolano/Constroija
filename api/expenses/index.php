<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$projectId = (int)input('project_id', 0);
if ($projectId <= 0) {
    json_error('ID de projeto inválido.');
}

$db = db();

try {
    // 1. Validar se o utilizador faz parte da equipa do projeto
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager) {
        json_error('Acesso negado. Não faz parte da equipa deste projeto.', 403);
    }

    // Filtros adicionais opcionais
    $phase    = input('phase') ? trim((string)input('phase')) : null;
    $type     = input('type') ? trim((string)input('type')) : null;
    $supplier = input('supplier') ? trim((string)input('supplier')) : null;
    
    // Construção básica da Query
    // (CJ-07) Os ficheiros privados são referenciados pelo ID em documents;
    // nunca são devolvidas URLs públicas de recibos/fotos.
    $query = "SELECT e.*, pr.name as creator_name,
                     (SELECT d.id FROM documents d WHERE d.expense_id = e.id AND d.kind = 'receipt' ORDER BY d.id DESC LIMIT 1) AS receipt_file_id,
                     (SELECT d.id FROM documents d WHERE d.expense_id = e.id AND d.kind = 'expense_photo' ORDER BY d.id DESC LIMIT 1) AS photo_file_id
              FROM expenses e
              JOIN profiles pr ON e.user_id = pr.id
              WHERE e.project_id = :project_id AND e.deleted_at IS NULL";
              
    $params = ['project_id' => $projectId];
    
    if ($phase) {
        $query .= " AND e.phase = :phase";
        $params['phase'] = $phase;
    }
    
    if ($type) {
        $query .= " AND e.type = :type";
        $params['type'] = $type;
    }
    
    if ($supplier) {
        $query .= " AND e.supplier LIKE :supplier";
        $params['supplier'] = '%' . $supplier . '%';
    }
    
    $query .= " ORDER BY e.purchase_date DESC, e.created_at DESC";
    
    $expenses = $db->fetchAll($query, $params);
    
    // (CJ-07) URLs diretas removidas: as fotos/recibos são servidos apenas por
    // /api/files/{id} com autorização. Limpar os caminhos internos da resposta.
    foreach ($expenses as $key => $e) {
        $expenses[$key]['photo_url'] = null;
        $expenses[$key]['receipt_url'] = null;
        $expenses[$key]['photo_file_id'] = $e['photo_file_id'] ? (int)$e['photo_file_id'] : null;
        $expenses[$key]['receipt_file_id'] = $e['receipt_file_id'] ? (int)$e['receipt_file_id'] : null;
    }

    json_ok(['expenses' => $expenses], 'Despesas carregadas.');

} catch (PDOException $e) {
    json_error('Erro técnico ao consultar despesas da obra.', 500);
}
