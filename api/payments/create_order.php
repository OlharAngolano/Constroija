<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// (CJ-01) Criação de pedido de pagamento no SERVIDOR.
// O navegador apenas envia a chave do plano; meses/valor/plano são definidos
// no servidor. A ativação da subscrição acontece apenas por:
//   1. confirmação manual de um administrador (api/admin/payments/confirm.php), ou
//   2. webhook de gateway com assinatura validada (api/payments/webhook.php).
// Não existe qualquer caminho de auto-ativação pelo utilizador.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido ou expirado.', 403);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado. Sessão expirada.', 401);
}

// Tabela de planos definida exclusivamente no servidor
$plans = [
    'pro1' => ['name' => 'Mestre de Obra', 'months' => 1,  'amount' => 5000.00],
    'pro3' => ['name' => 'Empreiteiro Pro', 'months' => 3, 'amount' => 12000.00],
    'pro6' => ['name' => 'Construtor VIP', 'months' => 6, 'amount' => 20000.00],
];

$planKey = trim((string)input('plan', ''));
if (!isset($plans[$planKey])) {
    json_error('Plano inválido.', 400);
}
$plan = $plans[$planKey];

$db = db();
try {
    // Reutilizar um pedido pendente do mesmo plano (evita duplicados)
    $existing = $db->fetch(
        "SELECT id, plan_name, amount, status FROM payment_orders
         WHERE user_id = ? AND plan_key = ? AND status = 'pending' LIMIT 1",
        [$user['id'], $planKey]
    );
    if ($existing) {
        json_ok([
            'order_id' => (int)$existing['id'],
            'plan' => $planKey,
            'amount' => (float)$existing['amount'],
            'status' => $existing['status'],
            'message' => 'Já existe um pedido pendente para este plano.'
        ], 'Pedido de pagamento já registado. Aguarde a confirmação da equipa.');
    }

    // Novo pedido pendente
    $db->execute(
        "INSERT INTO payment_orders (user_id, plan_key, plan_name, months, amount, currency, status)
         VALUES (?, ?, ?, ?, ?, 'AOA', 'pending')",
        [(int)$user['id'], $planKey, $plan['name'], $plan['months'], $plan['amount']]
    );
    $orderId = (int)$db->lastInsertId();

    json_ok([
        'order_id' => $orderId,
        'plan' => $planKey,
        'plan_name' => $plan['name'],
        'months' => $plan['months'],
        'amount' => $plan['amount'],
        'currency' => 'AOA',
        'status' => 'pending'
    ], 'Pedido registado! Envie o comprovativo pelo WhatsApp; a ativação é confirmada pela equipa.');
} catch (PDOException $e) {
    json_internal_error('criar pedido de pagamento', $e);
}
