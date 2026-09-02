<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Validar se é admin e está autenticado
$adminUser = middleware_api_admin();

$db = db();

try {
    // 1. Contagem de utilizadores por estado
    $usersCount = $db->fetch("SELECT COUNT(*) as total FROM profiles")['total'] ?? 0;
    $activeUsers = $db->fetch("SELECT COUNT(*) as total FROM profiles WHERE status = 'active'")['total'] ?? 0;
    $pendingUsers = $db->fetch("SELECT COUNT(*) as total FROM profiles WHERE status = 'pending'")['total'] ?? 0;
    
    // 2. Contagem de projetos
    $projectsCount = $db->fetch("SELECT COUNT(*) as total FROM projects")['total'] ?? 0;
    
    // 3. Contagem de despesas e valor total gasto em obras (convertido em AOA)
    $expensesCount = $db->fetch("SELECT COUNT(*) as total FROM expenses WHERE deleted_at IS NULL")['total'] ?? 0;
    $expensesSum = $db->fetch("SELECT SUM(price * quantity) as total FROM expenses WHERE deleted_at IS NULL")['total'] ?? 0.00;

    // 4. Contagem de posts, comentários e Q&A
    $postsCount = $db->fetch("SELECT COUNT(*) as total FROM posts")['total'] ?? 0;
    $questionsCount = $db->fetch("SELECT COUNT(*) as total FROM questions")['total'] ?? 0;

    // 5. Últimos 5 utilizadores registados
    $recentUsers = $db->fetchAll(
        "SELECT id, name, username, email, status, created_at FROM profiles ORDER BY created_at DESC LIMIT 5"
    );

    json_ok([
        'metrics' => [
            'total_users' => (int)$usersCount,
            'active_users' => (int)$activeUsers,
            'pending_users' => (int)$pendingUsers,
            'total_projects' => (int)$projectsCount,
            'total_expenses' => (int)$expensesCount,
            'total_spent' => (float)$expensesSum,
            'total_posts' => (int)$postsCount,
            'total_questions' => (int)$questionsCount
        ],
        'recent_users' => $recentUsers
    ], 'Métricas de administração carregadas com sucesso.');

} catch (PDOException $e) {
    json_error('Erro técnico ao consultar métricas de administração: ' . $e->getMessage(), 500);
}
