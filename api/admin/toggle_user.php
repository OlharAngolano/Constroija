<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Apenas administradores
$adminUser = middleware_api_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$targetUserId = (int)input('user_id', 0);
$status = trim((string)input('status', ''));

if ($targetUserId <= 0 || !in_array($status, ['active', 'suspended'])) {
    json_error('ID de utilizador inválido ou estado incorreto.');
}

if ($targetUserId === (int)$adminUser['id']) {
    json_error('Não pode suspender ou alterar o seu próprio estado de administrador.');
}

$db = db();

try {
    // Verificar se o utilizador existe
    $target = $db->fetch("SELECT id, name, is_admin FROM profiles WHERE id = ?", [$targetUserId]);
    if (!$target) {
        json_error('Utilizador não encontrado.', 404);
    }

    if ((int)$target['is_admin'] === 1) {
        json_error('Não pode alterar o estado de outro administrador do sistema.');
    }

    // Atualizar o estado do utilizador e subscrição se for ativo
    if ($status === 'active') {
        $months = (int)input('months', 1);
        if ($months <= 0) $months = 1;
        
        // Obter expiração atual
        $userProfile = $db->fetch("SELECT subscription_expires_at FROM profiles WHERE id = ?", [$targetUserId]);
        $currentExpiry = $userProfile['subscription_expires_at'] ?? null;
        
        $baseDate = new DateTime();
        if ($currentExpiry) {
            $expiryDate = new DateTime($currentExpiry);
            if ($expiryDate > $baseDate) {
                $baseDate = $expiryDate; // Prorrogar a partir da data futura
            }
        }
        
        $baseDate->modify("+{$months} months");
        $newExpiry = $baseDate->format('Y-m-d H:i:s');
        
        $db->execute(
            "UPDATE profiles SET status = 'active', subscription_expires_at = ? WHERE id = ?",
            [$newExpiry, $targetUserId]
        );
        $msg = "Conta de '{$target['name']}' ativada e subscrição VIP prorrogada por {$months} mês/meses (Expira em: " . date('d/m/Y', strtotime($newExpiry)) . ").";
    } else {
        $db->execute("UPDATE profiles SET status = ? WHERE id = ?", [$status, $targetUserId]);
        $msg = "Conta de '{$target['name']}' suspensa com sucesso.";
    }

    json_ok([
        'user_id' => $targetUserId,
        'status' => $status
    ], $msg);

} catch (PDOException $e) {
    json_error('Erro técnico ao atualizar estado do utilizador: ' . $e->getMessage(), 500);
}
