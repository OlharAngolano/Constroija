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
 * Middleware para validar chamadas API com autenticação.
 *
 * O CSRF é validado em cada endpoint de escrita (métodos POST/PUT/DELETE) —
 * nunca em leituras GET, que são idempotentes (auditoria CJ-18).
 */
function middleware_api_auth(): array {
    // Verificar se o user está logado
    $user = current_user();
    if (!$user) {
        json_error('Não autorizado. Sessão expirada.', 401);
    }

    // Distinguir moderação de conta de expiração de subscrição (auditoria CJ-11):
    // - status 'suspended' só é definido por administração;
    // - premium expirado NÃO suspende a conta: apenas bloqueia o acesso com mensagem própria.
    if ((int)($user['is_admin'] ?? 0) === 0) {
        if (($user['status'] ?? '') === 'suspended') {
            json_error('A sua conta foi suspensa pela administração. Contacte o suporte.', 403);
        }
        $isExpired = !empty($user['subscription_expires_at']) && $user['subscription_expires_at'] < date('Y-m-d H:i:s');
        if ($isExpired) {
            json_error('A sua subscrição expirou. Renove em /subscription para continuar.', 403);
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
