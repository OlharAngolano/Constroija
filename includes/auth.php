<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Autenticação — revisão de segurança (auditoria CJ-04, CJ-11, CJ-15, CJ-16).
 *
 * - A sessão e as respostas JSON usam sempre user_session_dto()/user_public_dto()
 *   (lista positiva: nunca hashes nem tokens de recuperação/verificação).
 * - "remember me" usa a tabela auth_tokens (seletor + hash do validador,
 *   expiração, rotação por utilização) em vez de um token de texto simples
 *   guardado em profiles.remember_token.
 * - O registo público nunca cria administradores (ver bin/create_admin.php).
 * - O estado 'suspended' fica reservado à moderação: a expiração do premium
 *   não suspende contas.
 */

const REMEMBER_ME_COOKIE = 'remember_me';
const REMEMBER_ME_DAYS = 30;

/**
 * Indica se a tabela auth_tokens está disponível (resultado em cache por processo).
 */
function auth_tokens_available(): bool {
    static $available = null;
    if ($available === null) {
        try {
            db()->query("SELECT id FROM auth_tokens LIMIT 1");
            $available = true;
        } catch (PDOException $e) {
            $available = false;
        }
    }
    return $available;
}

/**
 * Cria (ou roda) um token "remember me" para o utilizador.
 * Devolve o par "seletor:validador" a colocar no cookie, ou null se indisponível.
 */
function remember_me_issue(int $userId): ?string {
    if (!auth_tokens_available()) {
        return null;
    }
    $db = db();
    $now = date('Y-m-d H:i:s');

    // Limpar tokens expirados e manter no máximo 5 dispositivos por utilizador
    try {
        $db->execute("DELETE FROM auth_tokens WHERE expires_at < ? OR user_id = ? AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM auth_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 4
            ) recent
        )", [$now, $userId, $userId]);
    } catch (PDOException $e) {
        return null;
    }

    $selector  = bin2hex(random_bytes(9));   // público, não sensível
    $validator = bin2hex(random_bytes(32));  // segredo, guardado apenas com hash
    $validatorHash = hash('sha256', $validator);

    $ok = $db->execute(
        "INSERT INTO auth_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)",
        [$userId, $selector, $validatorHash, date('Y-m-d H:i:s', time() + REMEMBER_ME_DAYS * 86400)]
    );

    return $ok ? $selector . ':' . $validator : null;
}

/**
 * Remove o token "remember me" do dispositivo atual (identificado pelo cookie).
 */
function remember_me_revoke(?string $cookieValue = null): void {
    if (!auth_tokens_available()) {
        return;
    }
    $cookieValue = $cookieValue ?? ($_COOKIE[REMEMBER_ME_COOKIE] ?? null);
    if ($cookieValue && strpos($cookieValue, ':') !== false) {
        $selector = substr($cookieValue, 0, strpos($cookieValue, ':'));
        try {
            db()->execute("DELETE FROM auth_tokens WHERE selector = ?", [$selector]);
        } catch (PDOException $e) {
            // silencioso
        }
    }
    // Apagar cookie em qualquer cenário de logout explícito
    setcookie(REMEMBER_ME_COOKIE, '', time() - 3600, '/');
}

/**
 * Remove todos os tokens "remember me" do utilizador (ex.: reset de password).
 */
function remember_me_revoke_all(int $userId): void {
    if (!auth_tokens_available()) {
        return;
    }
    try {
        db()->execute("DELETE FROM auth_tokens WHERE user_id = ?", [$userId]);
    } catch (PDOException $e) {
        // silencioso
    }
}

/**
 * Define o cookie "remember me" seguro.
 */
function remember_me_set_cookie(string $pair): void {
    $cookieOptions = [
        'expires' => time() + REMEMBER_ME_DAYS * 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        $cookieOptions['secure'] = true;
    }
    setcookie(REMEMBER_ME_COOKIE, $pair, $cookieOptions);
}

/**
 * Tenta autenticar um utilizador por email e password
 */
function attempt_login(string $email, string $password, bool $rememberMe = false): array {
    $db = db();

    // 1. Procurar perfil pelo email (SELECT * é filtrado pelo DTO antes de sair daqui)
    $profile = $db->fetch("SELECT * FROM profiles WHERE email = ?", [$email]);

    if (!$profile) {
        return ['success' => false, 'error' => 'Credenciais incorretas ou conta inexistente.'];
    }

    $now = date('Y-m-d H:i:s');
    $isAdmin = (int)($profile['is_admin'] ?? 0) === 1;

    // Contas suspensas por moderação entram apenas para ver a página de subscrição/contacto
    $isSuspended = ($profile['status'] === 'suspended');

    // 2. Verificar bloqueio por tentativas falhadas (Lockout)
    if ($profile['locked_until'] && $profile['locked_until'] > $now) {
        $lockedTime = strtotime($profile['locked_until']) - time();
        $minutes = ceil($lockedTime / 60);
        return ['success' => false, 'error' => "A conta está temporariamente bloqueada. Tente novamente em {$minutes} minuto(s)."];
    }

    // 3. Verificar password
    if (!password_verify($password, $profile['password_hash'])) {
        // Incrementar tentativas falhadas
        $attempts = (int)$profile['failed_login_attempts'] + 1;
        $lockedUntil = null;

        if ($attempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', time() + (15 * 60)); // Bloqueio de 15 minutos
            $db->execute("UPDATE profiles SET failed_login_attempts = ?, locked_until = ? WHERE id = ?", [
                $attempts, $lockedUntil, $profile['id']
            ]);
            return ['success' => false, 'error' => 'Demasiadas tentativas falhadas. A conta foi bloqueada por 15 minutos.'];
        } else {
            $db->execute("UPDATE profiles SET failed_login_attempts = ? WHERE id = ?", [
                $attempts, $profile['id']
            ]);
            return ['success' => false, 'error' => 'Credenciais incorretas. Tentativa ' . $attempts . ' de 5.'];
        }
    }

    // 4. Autenticação bem sucedida - Resetar bloqueios
    $db->execute("UPDATE profiles SET failed_login_attempts = 0, locked_until = NULL, last_login_at = ? WHERE id = ?", [
        $now, $profile['id']
    ]);

    // 5. Configurar sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerar ID de sessão para mitigar Session Fixation
    session_regenerate_id(true);

    // Guardar na sessão apenas o DTO seguro (sem hashes nem tokens)
    $sessionUser = user_session_dto($profile);
    $_SESSION['user'] = $sessionUser;

    // 6. Processar Remember Me (30 dias) — token com seletor/validador e rotação
    if ($rememberMe) {
        $pair = remember_me_issue((int)$profile['id']);
        if ($pair !== null) {
            remember_me_set_cookie($pair);
        }
    }

    // 7. Resposta (DTO público — nunca inclui password_hash nem tokens)
    $result = [
        'success' => true,
        'user' => user_public_dto($profile),
    ];

    // Conta suspensa por moderação: entra mas é redirecionada
    if ($isSuspended && !$isAdmin) {
        $result['redirect'] = '/subscription?reason=moderation';
        $result['message'] = 'A sua conta está suspensa. Contacte o suporte para regularizar a situação.';
    }

    return $result;
}

/**
 * Regista um novo utilizador no sistema.
 *
 * (CJ-15) Nenhum registo público cria administradores: a primeira conta de
 * administrador é criada por um operador com bin/create_admin.php.
 */
function register_user(string $name, string $email, string $username, string $password): array {
    $db = db();

    // Validações básicas de formato
    if (mb_strlen($name) < 2) {
        return ['success' => false, 'error' => 'O nome tem de ter pelo menos 2 caracteres.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Formato de email inválido.'];
    }
    if (!preg_match('/^[a-zA-Z0-9_\\.]{3,30}$/', $username)) {
        return ['success' => false, 'error' => 'O username só pode conter letras, números, pontos e underscores (3 a 30 caracteres).'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'A password tem de ter pelo menos 8 caracteres.'];
    }

    // Verificar se email já existe
    $existingEmail = $db->fetch("SELECT id FROM profiles WHERE email = ?", [$email]);
    if ($existingEmail) {
        return ['success' => false, 'error' => 'Este email já se encontra registado.'];
    }

    // Verificar se username já existe
    $existingUsername = $db->fetch("SELECT id FROM profiles WHERE username = ?", [$username]);
    if ($existingUsername) {
        return ['success' => false, 'error' => 'Este username já está a ser utilizado por outro utilizador.'];
    }

    // Encriptar password usando o algoritmo mais forte disponível
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    $passwordHash = password_hash($password, $algo);

    // Token de verificação de email
    $verificationToken = bin2hex(random_bytes(32));

    // Inserir perfil na DB — NUNCA admin. is_verified = 0.
    // Novo membro entra no plano gratuito com período de experiência premium.
    $status = 'active';
    $isAdmin = 0;
    $isVerified = 0;

    // Período de teste (trial) de 3 dias
    $subscriptionExpires = date('Y-m-d H:i:s', strtotime('+3 days'));
    $defaultCurrency = 'AOA';

    $inserted = $db->execute(
        "INSERT INTO profiles (name, email, password_hash, username, status, is_verified, is_admin, currency, email_verification_token, subscription_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$name, $email, $passwordHash, $username, $status, $isVerified, $isAdmin, $defaultCurrency, $verificationToken, $subscriptionExpires]
    );

    if (!$inserted) {
        return ['success' => false, 'error' => 'Ocorreu um erro ao guardar o seu registo. Tente novamente.'];
    }

    return [
        'success' => true,
        'message' => 'Conta criada com sucesso! A sua experiência premium começou agora. Inicie sessão para começar.'
    ];
}

/**
 * Encerra a sessão atual e apaga cookies
 */
function logout_user(): void {
    $user = current_user();

    // Remover o token "remember me" do dispositivo atual
    remember_me_revoke();
    if ($user) {
        // Limpeza do campo legado (versões anteriores da aplicação)
        try {
            db()->execute("UPDATE profiles SET remember_token = NULL WHERE id = ?", [$user['id']]);
        } catch (PDOException $e) {
            // silencioso
        }
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpar array da sessão
    $_SESSION = [];

    // Destruir cookie de sessão
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destruir cookie do Remember Me
    setcookie(REMEMBER_ME_COOKIE, '', time() - 3600, '/');

    // Destruir a sessão em si
    session_destroy();
}

/**
 * Tenta reautenticar o utilizador via Cookie "Remember Me"
 *
 * (CJ-16) O cookie contém "seletor:validador"; na base só existe o hash do
 * validador. Cada utilização roda o token (o antigo é eliminado).
 */
function check_remember_me(): void {
    if (is_logged_in()) {
        return;
    }

    if (!isset($_COOKIE[REMEMBER_ME_COOKIE]) || !auth_tokens_available()) {
        return;
    }

    $cookie = (string)$_COOKIE[REMEMBER_ME_COOKIE];
    if (strpos($cookie, ':') === false) {
        setcookie(REMEMBER_ME_COOKIE, '', time() - 3600, '/');
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    $validatorHash = hash('sha256', $validator);
    $db = db();

    try {
        $row = $db->fetch(
            "SELECT at.user_id, at.validator_hash, at.expires_at
             FROM auth_tokens at
             WHERE at.selector = ? AND at.expires_at > ?",
            [$selector, date('Y-m-d H:i:s')]
        );
    } catch (PDOException $e) {
        return;
    }

    // O cookie só é aceite se o validador corresponder ao hash guardado
    if (!$row || !hash_equals($row['validator_hash'], $validatorHash)) {
        setcookie(REMEMBER_ME_COOKIE, '', time() - 3600, '/');
        return;
    }

    // Buscar perfil completo para obter todos os campos do DTO
    $profile = $db->fetch("SELECT * FROM profiles WHERE id = ? AND status = 'active'", [$row['user_id']]);
    if (!$profile) {
        remember_me_revoke($cookie);
        return;
    }

    // Rodar o token: eliminar o usado e emitir um novo par
    try {
        $db->execute("DELETE FROM auth_tokens WHERE selector = ?", [$selector]);
    } catch (PDOException $e) {
        // silencioso
    }
    $pair = remember_me_issue((int)$profile['id']);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['user'] = user_session_dto($profile);

    if ($pair !== null) {
        remember_me_set_cookie($pair);
    }
}

/**
 * Cria token para reset de password
 */
function generate_password_reset(string $email): ?string {
    $db = db();
    $user = $db->fetch("SELECT id FROM profiles WHERE email = ? AND status = 'active'", [$email]);

    if (!$user) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hora de validade

    $db->execute("UPDATE profiles SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?", [
        $token, $expires, $user['id']
    ]);

    return $token;
}

/**
 * Reseta a password com um token válido.
 *
 * (CJ-05) Após a redefinição, todos os tokens "remember me" do utilizador
 * são revogados e a coluna legada é limpa. O token de reset é invalidado.
 */
function reset_password_with_token(string $token, string $newPassword): bool {
    $db = db();
    $now = date('Y-m-d H:i:s');

    $user = $db->fetch("SELECT id FROM profiles WHERE password_reset_token = ? AND password_reset_expires > ?", [
        $token, $now
    ]);

    if (!$user) {
        return false;
    }

    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    $hash = password_hash($newPassword, $algo);

    $ok = $db->execute(
        "UPDATE profiles SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL, failed_login_attempts = 0, locked_until = NULL, remember_token = NULL WHERE id = ?",
        [$hash, $user['id']]
    );

    if ($ok) {
        // Revogar todas as sessões persistentes ("remember me") do utilizador
        remember_me_revoke_all((int)$user['id']);
    }

    return $ok;
}

/**
 * Verifica o email de um utilizador utilizando um token de verificação
 */
function verify_email_with_token(string $token): bool {
    $db = db();
    $user = $db->fetch("SELECT id FROM profiles WHERE email_verification_token = ?", [$token]);

    if (!$user) {
        return false;
    }

    return $db->execute(
        "UPDATE profiles SET is_verified = 1, email_verification_token = NULL, status = 'active' WHERE id = ?",
        [$user['id']]
    );
}
