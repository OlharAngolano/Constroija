<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Apenas aceita pedidos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

// Validar Token CSRF
if (!validate_csrf()) {
    json_error('Sessão expirada. Por favor, recarregue a página e tente de novo.', 403);
}

// Aplicar Rate Limiting (máximo 3 registos a cada 10 minutos para evitar criação abusiva de contas)
if (!Security::rateLimit('auth_register', 3, 600)) {
    json_error('Demasiadas tentativas de registo de conta a partir deste endereço IP. Por favor, tente novamente mais tarde.', 429);
}

$name = trim((string)input('name', ''));
$email = trim((string)input('email', ''));
$username = trim((string)input('username', ''));
$password = (string)input('password', '');

if ($name === '' || $email === '' || $username === '' || $password === '') {
    json_error('Todos os campos obrigatórios têm de ser preenchidos.');
}

// Chamar função de registo seguro
$result = register_user($name, $email, $username, $password);

if ($result['success']) {
    // Configurar mensagem flash para o redirecionamento
    set_flash_message('success', 'Registo concluído! A sua conta foi ativada. Inicie sessão para começar.');
    json_ok($result, 'Registo bem sucedido.');
} else {
    json_error($result['error']);
}
