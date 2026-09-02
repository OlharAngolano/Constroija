<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Endpoint para processamento de webhook de faturação (simulador)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

// Obter utilizador atual (pode estar suspenso, pelo que não usamos middleware_api_auth direto)
$user = current_user();
if (!$user) {
    json_error('Não autorizado. Sessão expirada.', 401);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido ou expirado.', 403);
}

$months = (int)input('months', 1);
$amount = (float)input('amount', 5000.0);
$planName = trim((string)input('plan_name', 'Mestre de Obra (1 Mês)'));

// Validar plano
if (!in_array($months, [1, 3, 6], true)) {
    $months = 1;
}

$db = db();
$now = date('Y-m-d H:i:s');

try {
    // Buscar o estado mais recente do utilizador na BD
    $profile = $db->fetch("SELECT * FROM profiles WHERE id = ?", [$user['id']]);
    if (!$profile) {
        json_error('Utilizador não encontrado no sistema.', 404);
    }

    $currentExpiry = $profile['subscription_expires_at'];
    
    // Calcular nova data de expiração
    if ($currentExpiry && $currentExpiry > $now) {
        // Se a assinatura ainda está ativa, adiciona os novos meses ao final do prazo atual
        $newExpiry = date('Y-m-d H:i:s', strtotime($currentExpiry . " + {$months} months"));
    } else {
        // Se já expirou ou está suspensa, inicia a contagem a partir de agora
        $newExpiry = date('Y-m-d H:i:s', strtotime("+{$months} months"));
    }

    // Atualizar perfil na base de dados
    $db->execute(
        "UPDATE profiles 
         SET status = 'active', subscription_expires_at = ?, failed_login_attempts = 0, locked_until = NULL 
         WHERE id = ?",
        [$newExpiry, $user['id']]
    );

    // Sincronizar dados da sessão
    $updatedProfile = $db->fetch("SELECT * FROM profiles WHERE id = ?", [$user['id']]);
    $_SESSION['user'] = $updatedProfile;

    // Inserir notificação de confirmação de pagamento
    // O remetente é o próprio Admin (ID: 1)
    $db->execute(
        "INSERT INTO notifications (user_id, sender_id, type, entity_id, is_read, created_at) 
         VALUES (?, 1, 'message', ?, 0, NOW())",
        [$user['id'], $user['id']]
    );

    json_ok([
        'new_expiry' => $newExpiry,
        'plan_name' => $planName,
        'amount' => $amount
    ], 'Subscrição premium ativada com sucesso!');

} catch (PDOException $e) {
    json_error('Erro interno na confirmação do pagamento: ' . $e->getMessage(), 500);
}
