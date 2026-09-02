<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Tenta autenticar um utilizador por email e password
 */
function attempt_login(string $email, string $password, bool $rememberMe = false): array {
    $db = db();
    
    // 1. Procurar perfil pelo email
    $profile = $db->fetch("SELECT * FROM profiles WHERE email = ?", [$email]);
    
    if (!$profile) {
        return ['success' => false, 'error' => 'Credenciais incorretas ou conta inexistente.'];
    }
    
    $now = date('Y-m-d H:i:s');
    
    // 2. Verificar se a conta está suspensa ou período de teste expirou
    if ((int)$profile['is_admin'] === 0 && !empty($profile['subscription_expires_at']) && $profile['subscription_expires_at'] < $now) {
        if ($profile['status'] !== 'suspended') {
            $db->execute("UPDATE profiles SET status = 'suspended' WHERE id = ?", [$profile['id']]);
        }
        $profile['status'] = 'suspended';
    }

    // Contas suspensas podem entrar mas são redirecionadas para a página de subscrição
    $isSuspended = ($profile['status'] === 'suspended');
    
    // 3. Verificar bloqueio por tentativas falhadas (Lockout)
    if ($profile['locked_until'] && $profile['locked_until'] > $now) {
        $lockedTime = strtotime($profile['locked_until']) - time();
        $minutes = ceil($lockedTime / 60);
        return ['success' => false, 'error' => "A conta está temporariamente bloqueada. Tente novamente em {$minutes} minuto(s)."];
    }
    
    // 4. Verificar password
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
    
    // 5. Autenticação bem sucedida - Resetar bloqueios
    $db->execute("UPDATE profiles SET failed_login_attempts = 0, locked_until = NULL, last_login_at = ? WHERE id = ?", [
        $now, $profile['id']
    ]);
    
    // Se a conta está suspensa, permitir login mas redirecionar para subscrição
    if ($isSuspended) {
        // Configurar sessão mesmo para suspensos (necessário para ver a página de subscrição)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        unset($profile['password_hash']);
        $_SESSION['user'] = $profile;
        
        return [
            'success' => true, 
            'user' => $profile,
            'redirect' => '/subscription',
            'message' => 'A sua conta está suspensa. Regularize o pagamento para reativar o acesso.'
        ];
    }
    
    // 6. Configurar sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerar ID de sessão para mitigar Session Fixation
    session_regenerate_id(true);
    
    unset($profile['password_hash']); // Não guardar a hash de password na sessão
    $_SESSION['user'] = $profile;
    
    // 7. Processar Remember Me Cookie (30 dias)
    if ($rememberMe) {
        $token = bin2hex(random_bytes(32));
        $db->execute("UPDATE profiles SET remember_token = ? WHERE id = ?", [$token, $profile['id']]);
        
        $cookieOptions = [
            'expires' => time() + (30 * 24 * 60 * 60), // 30 dias
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            $cookieOptions['secure'] = true;
        }
        
        setcookie('remember_me', $token, $cookieOptions);
    }
    
    return ['success' => true, 'user' => $profile];
}

/**
 * Regista um novo utilizador no sistema
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
    if (!preg_match('/^[a-zA-Z0-9_\.]{3,30}$/', $username)) {
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
    
    // Inserir perfil na DB
    // O primeiro utilizador a registar-se torna-se Admin automaticamente (para facilidade de setup)
    $hasAdmin = $db->fetch("SELECT id FROM profiles LIMIT 1");
    $isAdmin = $hasAdmin ? 0 : 1;
    $status = 'active'; // Inicia ativo para aproveitar o trial
    $isVerified = $isAdmin ? 1 : 0;
    
    // Configurar Trial de 3 Dias
    $subscriptionExpires = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    // Fases de projeto padrão do utilizador
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
        'message' => 'Conta criada com sucesso! Por favor aguarde aprovação ou verifique o seu email.',
        'verification_token' => $verificationToken
    ];
}

/**
 * Encerra a sessão atual e apaga cookies
 */
function logout_user(): void {
    $db = db();
    $user = current_user();
    
    if ($user) {
        // Remover token da DB
        $db->execute("UPDATE profiles SET remember_token = NULL WHERE id = ?", [$user['id']]);
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
    setcookie('remember_me', '', time() - 3600, '/');
    
    // Destruir a sessão em si
    session_destroy();
}

/**
 * Tenta reautenticar o utilizador via Cookie "Remember Me"
 */
function check_remember_me(): void {
    if (is_logged_in()) {
        return;
    }
    
    if (!isset($_COOKIE['remember_me'])) {
        return;
    }
    
    $token = $_COOKIE['remember_me'];
    $db = db();
    
    // Procurar utilizador com o token correspondente
    $profile = $db->fetch("SELECT * FROM profiles WHERE remember_token = ? AND status = 'active'", [$token]);
    
    if ($profile) {
        // Iniciar sessão
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_regenerate_id(true);
        unset($profile['password_hash']);
        $_SESSION['user'] = $profile;
    } else {
        // Cookie inválido - Apagar
        setcookie('remember_me', '', time() - 3600, '/');
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
    $expires = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 horas de validade
    
    $db->execute("UPDATE profiles SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?", [
        $token, $expires, $user['id']
    ]);
    
    return $token;
}

/**
 * Reseta a password com um token válido
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
    
    return $db->execute(
        "UPDATE profiles SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL, failed_login_attempts = 0, locked_until = NULL WHERE id = ?",
        [$hash, $user['id']]
    );
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
