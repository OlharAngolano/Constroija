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
    // Buscar conversas ativas com dados da outra pessoa e da última mensagem
    $conversations = $db->fetchAll(
        "SELECT c.id, 
                (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message,
                (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_time,
                pr.name, pr.username, pr.avatar_url, pr.id AS participant_id
         FROM conversations c
         JOIN conversation_participants cp ON c.id = cp.conversation_id
         JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id != cp.user_id
         JOIN profiles pr ON cp2.user_id = pr.id
         WHERE cp.user_id = ?
         ORDER BY last_message_time DESC, c.created_at DESC",
        [$user['id']]
    );

    // Formatar avatars
    foreach ($conversations as $key => $convo) {
        $conversations[$key]['avatar_url'] = get_avatar_url($convo['avatar_url'], $convo['name']);
        if ($convo['last_message_time']) {
            $conversations[$key]['time_ago'] = time_ago($convo['last_message_time']);
        } else {
            $conversations[$key]['time_ago'] = '';
        }
    }

    json_ok(['conversations' => $conversations], 'Conversas carregadas.');

} catch (PDOException $e) {
    json_error('Erro técnico ao procurar conversas de chat.', 500);
}
