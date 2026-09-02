<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$notificationId = (int)input('id', 0);
$all = (bool)input('all', false);

$db = db();

try {
    if ($all) {
        $db->query(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ?",
            [$user['id']]
        );
        json_ok([], 'Todas as notificações foram marcadas como lidas.');
    } elseif ($notificationId > 0) {
        $db->query(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
            [$notificationId, $user['id']]
        );
        json_ok([], 'Notificação marcada como lida.');
    } else {
        json_error('Parâmetros inválidos.');
    }
} catch (PDOException $e) {
    json_error('Erro técnico ao atualizar notificações: ' . $e->getMessage(), 500);
}
