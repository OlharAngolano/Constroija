<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Validar se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

// 2. Validar Token CSRF
if (!validate_csrf()) {
    json_error('Token CSRF inválido ou em falta.', 403);
}

// 3. Aplicar Rate Limiting (máximo 10 tentativas por hora)
if (!Security::rateLimit('auth_reset', 10, 3600)) {
    json_error('Demasiadas tentativas. Por favor, tente novamente mais tarde.', 429);
}

// 4. Capturar inputs
$token = trim((string)input('token', ''));
$password = (string)input('password', '');
$passwordConfirm = (string)input('password_confirm', '');

// 5. Validar inputs
if (empty($token)) {
    json_error('Token de recuperação em falta.', 400);
}

if (strlen($password) < 8) {
    json_error('A palavra-passe deve conter pelo menos 8 caracteres.', 400);
}

if ($password !== $passwordConfirm) {
    json_error('As palavras-passe introduzidas não coincidem.', 400);
}

// 6. Procurar o utilizador pelo token correspondente
$db = db();
$user = $db->fetch(
    "SELECT id, password_reset_expires FROM profiles WHERE password_reset_token = ? LIMIT 1",
    [$token]
);

if ($user === null) {
    // (CJ-05) sem token em logs
    error_log("Password reset API: token não encontrado (utilizador " . ($user['id'] ?? '?') . ").");
    json_error('Token de recuperação inválido ou já utilizado.', 400);
}

// 7. Validar expiração temporal (1 hora)
$expires = $user['password_reset_expires'];
$now = date('Y-m-d H:i:s');

if ($expires === null || $expires < $now) {
    error_log("Password reset API: token expirado para o utilizador {$user['id']}.");
    json_error('O link de recuperação expirou. Por favor, solicite um novo.', 400);
}

$userId = (int)$user['id'];

// 8. Redefinir password, invalidar o token usado e revogar todos os
//    dispositivos "remember me" (CJ-05/CJ-16).
if (!reset_password_with_token((string)$token, $password)) {
    error_log("Password reset API: falha ao gravar nova password para o utilizador {$userId}.");
    json_error('Ocorreu um erro ao redefinir a palavra-passe. Tente novamente.', 500);
}

// 10. Retornar sucesso
json_ok([], 'A sua palavra-passe foi alterada com sucesso! Pode agora iniciar sessão.');
