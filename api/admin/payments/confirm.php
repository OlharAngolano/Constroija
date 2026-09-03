<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/middleware.php';

// Confirmação manual de um pedido de pagamento (fluxo WhatsApp).
// Fluxo: o membro transfere e envia comprovativo -> a equipa confere ->
// o administrador confirma aqui -> a subscrição é ativada/prorrogada.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

$adminUser = middleware_api_admin();

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$orderId = (int)input('order_id', 0);
if ($orderId <= 0) {
    json_error('ID de pedido inválido.');
}

$db = db();
try {
    $order = $db->fetch("SELECT * FROM payment_orders WHERE id = ?", [$orderId]);
    if (!$order) {
        json_error('Pedido de pagamento não encontrado.', 404);
    }
    if ($order['status'] !== 'pending') {
        json_error('Este pedido já foi processado.', 409);
    }

    $now = date('Y-m-d H:i:s');
    $db->beginTransaction();

    // Calcular nova expiração (prorroga se ainda estiver ativo)
    $profile = $db->fetch("SELECT subscription_expires_at FROM profiles WHERE id = ?", [(int)$order['user_id']]);
    if (!$profile) {
        $db->rollBack();
        json_error('Utilizador associado ao pedido não encontrado.', 404);
    }

    $currentExpiry = $profile['subscription_expires_at'];
    if ($currentExpiry && $currentExpiry > $now) {
        $newExpiry = date('Y-m-d H:i:s', strtotime($currentExpiry . ' + ' . (int)$order['months'] . ' months'));
    } else {
        $newExpiry = date('Y-m-d H:i:s', strtotime('+' . (int)$order['months'] . ' months'));
    }

    $db->execute(
        "UPDATE profiles SET status = 'active', subscription_expires_at = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?",
        [$newExpiry, (int)$order['user_id']]
    );

    $notes = trim((string)input('notes', ''));
    $db->execute(
        "UPDATE payment_orders SET status = 'paid', paid_at = ?, gateway = 'manual', notes = ? WHERE id = ?",
        [$now, 'Confirmado por admin #' . (int)$adminUser['id'] . ($notes !== '' ? ' — ' . $notes : ''), $orderId]
    );

    $db->execute(
        "INSERT INTO notifications (user_id, sender_id, type, entity_id, is_read, created_at) VALUES (?, 1, 'message', ?, 0, NOW())",
        [(int)$order['user_id'], $orderId]
    );

    $db->commit();

    json_ok([
        'order_id' => $orderId,
        'status' => 'paid',
        'new_expiry' => $newExpiry
    ], 'Pagamento confirmado: subscrição ativada até ' . date('d/m/Y', strtotime($newExpiry)) . '.');
} catch (PDOException $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    json_internal_error('confirmar pagamento', $e);
}
