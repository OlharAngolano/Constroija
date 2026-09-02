<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Middleware para bloquear utilizadores não autenticados
 */
function middleware_require_auth(): void {
    require_auth();
}

/**
 * Middleware para bloquear utilizadores que não são administradores
 */
function middleware_require_admin(): void {
    require_admin();
}

/**
 * Middleware para validar chamadas API com autenticação e CSRF
 */
function middleware_api_auth(): array {
    // 1. Validar CSRF
    if (!validate_csrf()) {
        json_error('Token CSRF inválido ou ausente.', 403);
    }
    
    // 2. Verificar se o user está logado
    $user = current_user();
    if (!$user) {
        json_error('Não autorizado. Sessão expirada.', 401);
    }
    
    // 3. Verificar suspensão / trial expirado
    if ((int)($user['is_admin'] ?? 0) === 0) {
        $isExpired = !empty($user['subscription_expires_at']) && $user['subscription_expires_at'] < date('Y-m-d H:i:s');
        if ($isExpired || ($user['status'] ?? '') === 'suspended') {
            json_error('A sua conta está suspensa. Por favor, regularize o pagamento.', 403);
        }
    }
    
    return $user;
}

/**
 * Middleware para validar privilégios admin em chamadas de API
 */
function middleware_api_admin(): array {
    $user = middleware_api_auth();
    if ((int)($user['is_admin'] ?? 0) !== 1) {
        json_error('Permissão negada. Apenas para administradores.', 403);
    }
    return $user;
}
