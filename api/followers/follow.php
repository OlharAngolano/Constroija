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

$followingId = (int)input('following_id', 0);
if ($followingId <= 0) {
    json_error('ID de utilizador inválido.');
}

if ($followingId === $user['id']) {
    json_error('Não pode seguir-se a si mesmo.');
}

$db = db();

try {
    // Verificar se o utilizador a seguir existe
    $target = $db->fetch("SELECT id, name FROM profiles WHERE id = ?", [$followingId]);
    if (!$target) {
        json_error('Utilizador não encontrado.', 404);
    }

    // Verificar se já segue
    $follow = $db->fetch(
        "SELECT id FROM followers WHERE follower_id = ? AND following_id = ?",
        [$user['id'], $followingId]
    );

    if ($follow) {
        // Deixar de seguir (Unfollow)
        $db->execute("DELETE FROM followers WHERE id = ?", [$follow['id']]);
        $following = false;

        // Remover notificação correspondente
        $db->execute(
            "DELETE FROM notifications WHERE user_id = ? AND sender_id = ? AND type = 'follow'",
            [$followingId, $user['id']]
        );
    } else {
        // Seguir (Follow)
        $db->execute(
            "INSERT INTO followers (follower_id, following_id) VALUES (?, ?)",
            [$user['id'], $followingId]
        );
        $following = true;

        // Criar notificação para o utilizador que foi seguido
        $db->execute(
            "INSERT INTO notifications (user_id, sender_id, type, entity_id) VALUES (?, ?, 'follow', ?)",
            [$followingId, $user['id'], $user['id']]
        );
    }

    // Obter estatísticas atualizadas
    $followersCount = $db->fetch(
        "SELECT COUNT(*) AS total FROM followers WHERE following_id = ?",
        [$followingId]
    );

    json_ok([
        'following' => $following,
        'followers_count' => (int)$followersCount['total']
    ], $following ? "Começou a seguir {$target['name']}." : "Deixou de seguir {$target['name']}.");

} catch (PDOException $e) {
    json_error('Erro técnico ao processar ligação de seguidor.', 500);
}
