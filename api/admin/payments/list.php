<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/middleware.php';

// Listagem de pedidos de pagamento (GET — sem CSRF, apenas admin)

$adminUser = middleware_api_admin();

$status = trim((string)input('status', 'pending'));
if (!in_array($status, ['pending', 'paid', 'all'], true)) {
    $status = 'pending';
}

$db = db();
try {
    if ($status === 'all') {
        $orders = $db->fetchAll(
            "SELECT o.id, o.plan_key, o.plan_name, o.months, o.amount, o.currency,
                    o.status, o.gateway, o.notes, o.created_at, o.paid_at,
                    p.name, p.email, p.username
             FROM payment_orders o
             JOIN profiles p ON p.id = o.user_id
             ORDER BY o.id DESC LIMIT 50"
        );
    } else {
        $orders = $db->fetchAll(
            "SELECT o.id, o.plan_key, o.plan_name, o.months, o.amount, o.currency,
                    o.status, o.gateway, o.notes, o.created_at, o.paid_at,
                    p.name, p.email, p.username
             FROM payment_orders o
             JOIN profiles p ON p.id = o.user_id
             WHERE o.status = ?
             ORDER BY o.id DESC LIMIT 50",
            [$status]
        );
    }

    json_ok(['orders' => $orders], 'Pedidos de pagamento carregados.');
} catch (PDOException $e) {
    json_internal_error('listar pedidos de pagamento', $e);
}
