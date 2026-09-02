<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Apenas utilizadores autenticados podem ver o feed
$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$page = (int)input('page', 1);
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$filter = (string)input('filter', 'all'); // 'all' ou 'following'
$search = trim((string)input('search', ''));

$db = db();

$whereClauses = [];
$params = [
    'my_user_id' => $user['id']
];

if ($filter === 'following') {
    $whereClauses[] = "p.user_id IN (SELECT following_id FROM followers WHERE follower_id = :follower_id)";
    $params['follower_id'] = $user['id'];
}

if ($filter === 'reels') {
    $whereClauses[] = "p.is_reel = 1";
} else {
    $whereClauses[] = "p.is_reel = 0";
}

if ($search !== '') {
    $whereClauses[] = "p.content LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

$sql = "SELECT p.*, pr.name, pr.username, pr.avatar_url,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS likes_count,
               (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comments_count,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = :my_user_id) AS is_liked
        FROM posts p
        JOIN profiles pr ON p.user_id = pr.id
        $whereSql
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset";

$params['limit'] = $limit;
$params['offset'] = $offset;

try {
    $posts = $db->fetchAll($sql, $params);
    
    // Obter comentários de cada post
    foreach ($posts as $key => $post) {
        $postComments = $db->fetchAll(
            "SELECT c.*, pr.name, pr.username, pr.avatar_url 
             FROM comments c 
             JOIN profiles pr ON c.user_id = pr.id 
             WHERE c.post_id = ? 
             ORDER BY c.created_at ASC",
            [$post['id']]
        );
        
        // Formatar avatars
        foreach ($postComments as $k => $c) {
            $postComments[$k]['avatar_url'] = get_avatar_url($c['avatar_url'], $c['name']);
        }
        
        $posts[$key]['comments'] = $postComments;
        $posts[$key]['avatar_url'] = get_avatar_url($post['avatar_url'], $post['name']);
        
        if (!empty($post['video_qualities'])) {
            $posts[$key]['video_qualities'] = json_decode($post['video_qualities'], true);
        } else {
            $posts[$key]['video_qualities'] = null;
        }
    }
    
    // Obter stories ativos das últimas 24 horas (todos os utilizadores ativos)
    // No nosso MVP social, trataremos stories como posts que contêm imagens criados nas últimas 24 horas
    $yesterday = date('Y-m-d H:i:s', time() - (24 * 60 * 60));
    $stories = $db->fetchAll(
        "SELECT DISTINCT pr.id, pr.name, pr.username, pr.avatar_url
         FROM posts p
         JOIN profiles pr ON p.user_id = pr.id
         WHERE p.file_url IS NOT NULL AND p.file_type LIKE 'image%' AND p.created_at >= ?
         ORDER BY p.created_at DESC
         LIMIT 15",
        [$yesterday]
    );

    foreach ($stories as $k => $st) {
        $stories[$k]['avatar_url'] = get_avatar_url($st['avatar_url'], $st['name']);
    }

    json_ok([
        'posts' => $posts,
        'stories' => $stories,
        'page' => $page,
        'filter' => $filter
    ], 'Feed carregado.');

} catch (PDOException $e) {
    json_error('Erro técnico ao aceder ao feed de posts.', 500);
}
