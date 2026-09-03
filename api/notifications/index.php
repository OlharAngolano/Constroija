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
    // 1. Obter número de não lidas
    $unreadRes = $db->fetch(
        "SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0",
        [$user['id']]
    );
    $unreadCount = (int)($unreadRes['count'] ?? 0);

    // 2. Obter notificações recentes
    $notifications = $db->fetchAll(
        "SELECT n.*, pr.name AS sender_name, pr.username AS sender_username, pr.avatar_url AS sender_avatar
         FROM notifications n
         JOIN profiles pr ON n.sender_id = pr.id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC
         LIMIT 50",
        [$user['id']]
    );

    // Formatar avatars
    foreach ($notifications as $key => $n) {
        $notifications[$key]['sender_avatar'] = get_avatar_url($n['sender_avatar'], $n['sender_name']);
        $notifications[$key]['is_read'] = (int)$n['is_read'];
    }

    json_ok([
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ], 'Notificações carregadas.');

} catch (PDOException $e) {
    json_internal_error('Erro técnico ao carregar notificações: ', $e);
}
