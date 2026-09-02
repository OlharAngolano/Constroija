<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$conversationId = (int)input('conversation_id', 0);
if ($conversationId <= 0) {
    json_error('ID de conversa inválido.');
}

$db = db();

try {
    // 1. Validar se o utilizador atual é participante da conversa
    $participant = $db->fetch(
        "SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?",
        [$conversationId, $user['id']]
    );

    if (!$participant) {
        json_error('Acesso negado a esta conversa de chat.', 403);
    }

    // 2. Atualizar estado de "lida" na conversa para o utilizador atual
    $now = date('Y-m-d H:i:s');
    $db->execute(
        "UPDATE conversation_participants SET last_read_at = ? WHERE conversation_id = ? AND user_id = ?",
        [$now, $conversationId, $user['id']]
    );

    // Marcar quaisquer notificações de mensagens deste chat como lidas
    $db->execute(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'message' AND entity_id = ?",
        [$user['id'], $conversationId]
    );

    // 3. Obter todas as mensagens
    $messages = $db->fetchAll(
        "SELECT m.*, pr.name, pr.username, pr.avatar_url 
         FROM messages m
         JOIN profiles pr ON m.sender_id = pr.id
         WHERE m.conversation_id = ?
         ORDER BY m.created_at ASC",
        [$conversationId]
    );

    json_ok(['messages' => $messages], 'Mensagens carregadas.');

} catch (PDOException $e) {
    json_error('Erro técnico ao consultar histórico de mensagens.', 500);
}
