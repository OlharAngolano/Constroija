<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

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

$conversationId = (int)input('conversation_id', 0);
$recipientId    = (int)input('recipient_id', 0);
$messageText    = trim((string)input('message', ''));

if ($messageText === '') {
    json_error('Não é possível enviar mensagens vazias.');
}

$db = db();

try {
    // 1. Se não houver conversa ativa, resolver ou criar
    if ($conversationId <= 0) {
        if ($recipientId <= 0) {
            json_error('Necessita de indicar um ID de conversa ou um destinatário válido.');
        }
        
        if ($recipientId === $user['id']) {
            json_error('Não pode iniciar uma conversa consigo mesmo.');
        }

        // Verificar se já existe uma conversa entre estes dois utilizadores
        $existing = $db->fetch(
            "SELECT cp1.conversation_id 
             FROM conversation_participants cp1
             JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
             WHERE cp1.user_id = ? AND cp2.user_id = ?",
            [$user['id'], $recipientId]
        );

        if ($existing) {
            $conversationId = (int)$existing['conversation_id'];
        } else {
            // Criar nova conversa (Transação)
            $db->beginTransaction();
            
            $db->execute("INSERT INTO conversations () VALUES ()");
            $conversationId = (int)$db->lastInsertId();
            
            $db->execute("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)", [$conversationId, $user['id']]);
            $db->execute("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)", [$conversationId, $recipientId]);
            
            $db->commit();
        }
    } else {
        // (CJ-03) Autorização rigorosa: o remetente TEM de ser participante da
        // conversa; o destinatário é o OUTRO participante, confirmado na mesma
        // consulta (dois aliases + condição explícita cp_me.user_id = ?).
        $participant = $db->fetch(
            "SELECT cp_other.user_id
             FROM conversation_participants cp_me
             JOIN conversation_participants cp_other
               ON cp_other.conversation_id = cp_me.conversation_id
              AND cp_other.user_id != cp_me.user_id
             WHERE cp_me.conversation_id = ? AND cp_me.user_id = ?
             LIMIT 1",
            [$conversationId, $user['id']]
        );

        if (!$participant) {
            json_error('Acesso negado. Não faz parte desta conversa.', 403);
        }
        
        $recipientId = (int)$participant['user_id'];
    }

    // 2. Inserir a nova mensagem no chat
    $db->execute(
        "INSERT INTO messages (conversation_id, sender_id, message) VALUES (?, ?, ?)",
        [$conversationId, $user['id'], $messageText]
    );

    $messageId = $db->lastInsertId();

    // 3. Criar uma notificação de nova mensagem para o destinatário (se não houver já uma por ler)
    $hasUnreadNotif = $db->fetch(
        "SELECT id FROM notifications 
         WHERE user_id = ? AND sender_id = ? AND type = 'message' AND entity_id = ? AND is_read = 0",
        [$recipientId, $user['id'], $conversationId]
    );

    if (!$hasUnreadNotif) {
        $db->execute(
            "INSERT INTO notifications (user_id, sender_id, type, entity_id) VALUES (?, ?, 'message', ?)",
            [$recipientId, $user['id'], $conversationId]
        );
    }

    // Obter dados da mensagem acabada de enviar
    $newMsg = $db->fetch("SELECT * FROM messages WHERE id = ?", [$messageId]);

    json_ok(['message' => $newMsg, 'conversation_id' => $conversationId], 'Mensagem enviada.');

} catch (PDOException $e) {
    // Reverter transações pendentes se houver falhas
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    json_internal_error('Erro técnico ao enviar mensagem: ', $e);
}
