<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';

// (CJ-01) Webhook de pagamentos — autenticação CRIPTOGRÁFICA (assinatura HMAC),
// sem sessão nem CSRF. Ativa a subscrição apenas para pedidos criados no
// servidor (payment_orders), validando plano/valor/moeda e com idempotência.
//
// Cabeçalho esperado:  X-ConstróiJá-Signature: sha256=<hex HMAC do corpo>
// Ativado apenas quando PAYMENTS_WEBHOOK_SECRET está definido no .env.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (PAYMENTS_WEBHOOK_SECRET === '') {
    json_error('Webhook de pagamentos não configurado.', 503);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    json_error('Corpo do pedido vazio.', 400);
}

$signatureHeader = $_SERVER['HTTP_X_CONSTROIJA_SIGNATURE'] ?? '';
if ($signatureHeader === '') {
    json_error('Assinatura ausente.', 401);
}

// Formato: sha256=<hex>
if (!preg_match('/^sha256=([a-f0-9]{64})$/i', $signatureHeader, $m)) {
    json_error('Assinatura com formato inválido.', 401);
}

$expected = hash_hmac('sha256', $rawBody, PAYMENTS_WEBHOOK_SECRET);
if (!hash_equals($expected, strtolower($m[1]))) {
    error_log('Webhook de pagamentos: assinatura inválida recebida.');
    json_error('Assinatura inválida.', 401);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    json_error('Payload JSON inválido.', 400);
}

$db = db();
try {
    // Identificar o pedido criado no servidor (nunca confiar em user_id/valores do cliente)
    $order = null;
    if (!empty($payload['order_id'])) {
        $order = $db->fetch("SELECT * FROM payment_orders WHERE id = ?", [(int)$payload['order_id']]);
    } elseif (!empty($payload['idempotency_key'])) {
        $order = $db->fetch("SELECT * FROM payment_orders WHERE idempotency_key = ?", [$payload['idempotency_key']]);
    }
    if (!$order) {
        json_error('Pedido de pagamento desconhecido.', 404);
    }

    // Idempotência: eventos repetidos são aceites sem efeitos secundários
    if ($order['status'] === 'paid') {
        json_ok(['order_id' => (int)$order['id'], 'status' => 'paid'], 'Evento já processado (idempotente).');
    }
    if ($order['status'] !== 'pending') {
        json_error('Pedido de pagamento não está pendente.', 409);
    }

    // Validar valores contra o pedido do servidor
    $receivedAmount = (float)($payload['amount'] ?? 0.0);
    $receivedMonths = (int)($payload['months'] ?? 0);
    $receivedPlan   = trim((string)($payload['plan_key'] ?? $payload['plan'] ?? ''));
    $receivedCurrency = strtoupper(trim((string)($payload['currency'] ?? 'AOA')));

    if ($receivedAmount !== (float)$order['amount']
        || $receivedMonths !== (int)$order['months']
        || $receivedPlan !== $order['plan_key']
        || $receivedCurrency !== $order['currency']) {
        error_log(sprintf(
            'Webhook de pagamentos: divergência de valores no pedido #%d.',
            (int)$order['id']
        ));
        json_error('Valores do evento não correspondem ao pedido.', 422);
    }

    // Ativar a subscrição numa transação
    $now = date('Y-m-d H:i:s');
    $db->beginTransaction();

    $profile = $db->fetch("SELECT subscription_expires_at FROM profiles WHERE id = ?", [(int)$order['user_id']]);
    if (!$profile) {
        $db->rollBack();
        json_error('Utilizador do pedido não encontrado.', 404);
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

    $gatewayRef = trim((string)($payload['provider_tx_id'] ?? $payload['gateway_ref'] ?? ''));
    $db->execute(
        "UPDATE payment_orders
         SET status = 'paid', paid_at = ?, gateway = ?, gateway_ref = ?, provider_tx_id = ?, idempotency_key = COALESCE(idempotency_key, ?), notes = ?
         WHERE id = ?",
        [$now, 'webhook', $gatewayRef, $gatewayRef, $payload['idempotency_key'] ?? null, $gatewayRef, (int)$order['id']]
    );

    // Notificação de confirmação
    $db->execute(
        "INSERT INTO notifications (user_id, sender_id, type, entity_id, is_read, created_at) VALUES (?, 1, 'message', ?, 0, NOW())",
        [(int)$order['user_id'], (int)$order['id']]
    );

    $db->commit();

    json_ok(['order_id' => (int)$order['id'], 'status' => 'paid', 'new_expiry' => $newExpiry], 'Subscrição ativada.');

} catch (PDOException $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    log_internal_error($e, 'webhook de pagamentos');
    json_error('Falha interna ao processar o evento.', 500);
}
