<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido ou expirado. Por favor, recarregue a página.', 403);
}

// Aplicar Rate Limiting (máximo 10 tentativas por minuto para evitar força bruta)
if (!Security::rateLimit('auth_login', 10, 60)) {
    json_error('Demasiadas tentativas de início de sessão. Por favor, aguarde um minuto antes de tentar novamente.', 429);
}

$email    = trim((string)input('email', ''));
$password = (string)input('password', '');
$remember = (bool)input('remember_me', false);

if ($email === '' || $password === '') {
    json_error('O email e a password são obrigatórios.');
}

// Tentar autenticar o utilizador
$result = attempt_login($email, $password, $remember);

if ($result['success']) {
    $data = ['user' => $result['user']];
    if (!empty($result['redirect'])) {
        $data['redirect'] = $result['redirect'];
    }
    json_ok($data, $result['message'] ?? 'Sessão iniciada com sucesso.');
} else {
    json_error($result['error']);
}
