<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$q = input('q') ? trim((string)input('q')) : '';
$db = db();

try {
    if ($q !== '') {
        // Pesquisa Fulltext
        $sql = "SELECT q.*, pr.name, pr.username, pr.avatar_url,
                       (SELECT COUNT(*) FROM answers WHERE question_id = q.id) AS answers_count
                FROM questions q
                JOIN profiles pr ON q.user_id = pr.id
                WHERE MATCH(q.title, q.content) AGAINST(:query IN NATURAL LANGUAGE MODE)
                ORDER BY q.created_at DESC";
        $params = ['query' => $q];
    } else {
        // Listagem geral por recentes
        $sql = "SELECT q.*, pr.name, pr.username, pr.avatar_url,
                       (SELECT COUNT(*) FROM answers WHERE question_id = q.id) AS answers_count
                FROM questions q
                JOIN profiles pr ON q.user_id = pr.id
                ORDER BY q.created_at DESC";
        $params = [];
    }

    $questions = $db->fetchAll($sql, $params);

    // Formatar avatars e tags JSON
    foreach ($questions as $key => $qn) {
        $questions[$key]['avatar_url'] = get_avatar_url($qn['avatar_url'], $qn['name']);
        
        if ($qn['tags']) {
            $questions[$key]['tags'] = json_decode($qn['tags'], true);
        } else {
            $questions[$key]['tags'] = [];
        }
    }

    json_ok(['questions' => $questions], 'Perguntas carregadas.');

} catch (PDOException $e) {
    json_internal_error('Erro técnico ao consultar a base de dados Q&A: ', $e);
}
