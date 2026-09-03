<?php
declare(strict_types=1);

// Bootstrap mínimo: configuração global + classes de segurança + BD.
// (config.php é idempotente via require_once e define APP_ENV/APP_DEBUG/LOG_DIR...)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/../config/database.php';

// --- BASE DE DADOS ---

/**
 * Retorna o wrapper PDO Singleton da Base de Dados
 */
function db(): Database {
    return Database::getInstance();
}

// --- AUTENTICAÇÃO ---

/**
 * Lista positiva de campos do perfil que podem ser guardados na sessão e
 * devolvidos ao cliente (auditoria CJ-04).
 *
 * Hashes, tokens de recuperação/verificação e tokens "remember me" NUNCA
 * saem da base de dados para a sessão ou para respostas JSON.
 */
function user_session_dto(array $profile): array {
    $allowed = [
        'id', 'name', 'email', 'username', 'bio', 'location', 'avatar_url',
        'whatsapp', 'website', 'language', 'currency', 'status', 'is_verified',
        'is_admin', 'portfolio_data', 'portfolio_views', 'subscription_expires_at',
        'last_login_at', 'created_at'
    ];
    $dto = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $profile)) {
            $dto[$field] = $profile[$field];
        }
    }
    return $dto;
}

/**
 * DTO público para respostas JSON de autenticação/perfil.
 * Igual ao da sessão — nunca contém tokens nem hashes.
 */
function user_public_dto(array $profile): array {
    return user_session_dto($profile);
}

/**
 * Regista uma exceção interna com identificador de correlação e devolve
 * o código a mostrar ao utilizador (a mensagem real fica só no log protegido).
 */
function log_internal_error(Throwable $e, string $context = ''): string {
    $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    error_log(sprintf(
        '[%s] Erro interno (%s): %s em %s:%d',
        $ref,
        $context,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    return $ref;
}

/**
 * Resposta JSON de erro interno genérica (auditoria CJ-14): nunca expõe
 * mensagens da base de dados ao cliente; o detalhe vai para o log com o mesmo ID.
 */
function json_internal_error(string $context, Throwable $e): never {
    $ref = log_internal_error($e, $context);
    json_error('Erro interno do servidor. Guarde o código ' . $ref . ' e contacte o suporte.', 500);
}

/**
 * Página de erro genérica para falhas fatais de páginas HTML (auditoria CJ-14):
 * a mensagem real fica no log; o utilizador vê apenas o código de referência.
 */
function page_error(string $context, Throwable $e): never {
    $ref = log_internal_error($e, $context);
    http_response_code(500);
    echo "<!DOCTYPE html><html lang='pt-AO'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Erro — Constrói Já</title></head>"
        . "<body style='font-family:sans-serif; text-align:center; padding:50px 20px; background:#0a0f1e; color:#f1f5f9; min-height:100vh; margin:0;'>"
        . "<h1 style='color:#f97316;'>Constrói Já</h1>"
        . "<h2 style='color:#ef4444;'>Ocorreu um erro inesperado</h2>"
        . "<p>Guarde o código <strong>{$ref}</strong> e contacte o suporte.</p>"
        . "<p><a href='/' style='color:#f97316;'>Voltar ao Início</a></p>"
        . "</body></html>";
    exit;
}

/**
 * Retorna os dados do utilizador atualmente autenticado na sessão
 */
function current_user(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $user = $_SESSION['user'] ?? null;
    if ($user) {
        // Atualizar data/hora de última atividade se o último update foi há mais de 60 segundos.
        // Sem qualquer DDL em runtime (auditoria CJ-09): se a coluna não existir na base,
        // a escrita falha silenciosamente e o processo continua (a migração é executada
        // pelo operador via bin/migrate.php).
        $now = time();
        $lastUpdate = $_SESSION['last_activity_update'] ?? 0;
        if ($now - $lastUpdate > 60) {
            try {
                db()->execute("UPDATE profiles SET last_activity_at = NOW() WHERE id = ?", [$user['id']]);
                $_SESSION['last_activity_update'] = $now;
            } catch (PDOException $e) {
                // Ignora silenciosamente (coluna ainda não migrada)
            }
        }
    }
    
    return $user;
}

/**
 * Verifica se existe um utilizador autenticado
 */
function is_logged_in(): bool {
    return current_user() !== null;
}

/**
 * Verifica se o utilizador atual tem privilégios de administrador
 */
function is_admin(): bool {
    $user = current_user();
    return $user !== null && (int)($user['is_admin'] ?? 0) === 1;
}

/**
 * Exige autenticação. Redireciona para o login se não autenticado.
 *
 * Semântica revista (auditoria CJ-11):
 * - profiles.status ('active'/'suspended') fica reservado à moderação da conta.
 * - Uma subscrição/premium expirada NUNCA muda o status para 'suspended':
 *   o membro é redirecionado para /subscription e pode renovar ou usar o plano gratuito.
 * - Apenas um administrador pode suspender uma conta (status='suspended').
 */
function require_auth(): void {
    if (!is_logged_in()) {
        set_flash_message('warning', 'Por favor, inicie sessão para aceder a esta página.');
        redirect('/login');
    }
    
    $user = current_user();
    
    // Sincronizar o estado do utilizador a partir da Base de Dados para evitar sessões obsoletas
    try {
        $fresh = db()->fetch("SELECT status, subscription_expires_at, is_admin FROM profiles WHERE id = ?", [$user['id']]);
        if ($fresh) {
            $_SESSION['user']['status'] = $fresh['status'];
            $_SESSION['user']['subscription_expires_at'] = $fresh['subscription_expires_at'];
            $_SESSION['user']['is_admin'] = (int)$fresh['is_admin'];
            $user = $_SESSION['user']; // Atualiza a variável local
        }
    } catch (PDOException $e) {
        // Ignora falhas na BD
    }
    
    $uri = current_uri();
    
    // Permitir acesso a subscrição, suspensão, pagamentos e logout mesmo com conta suspensa
    if (
        strpos($uri, '/subscription') === 0 || 
        strpos($uri, '/api/payments') === 0 || 
        $uri === '/suspended' || 
        strpos($uri, '/api/auth/logout') === 0 || 
        $uri === '/login' || 
        $uri === '/register'
    ) {
        return;
    }
    
    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;
    $expired = !empty($user['subscription_expires_at']) && $user['subscription_expires_at'] < date('Y-m-d H:i:s');

    // Conta suspensa por moderação (apenas admin): acesso restrito à página de subscrição/contacto
    if (!$isAdmin && ($user['status'] ?? '') === 'suspended') {
        redirect('/subscription?reason=moderation');
    }

    // Premium expirado: redireciona para renovação SEM suspender a conta
    if (!$isAdmin && $expired) {
        redirect('/subscription?reason=expired');
    }
}

/**
 * Exige privilégios de administrador
 */
function require_admin(): void {
    require_auth();
    if (!is_admin()) {
        set_flash_message('danger', 'Acesso restrito apenas a administradores.');
        redirect('/feed');
    }
}

// --- HTTP & API ---

/**
 * Redireciona o utilizador e encerra a execução do script
 */
function redirect(string $url, int $code = 302): never {
    if (!headers_sent()) {
        header("Location: " . $url, true, $code);
    } else {
        echo "<script>window.location.href = '" . addslashes($url) . "';</script>";
    }
    exit;
}

/**
 * Retorna a URI da requisição atual sem query parameters, suportando subdiretórios
 */
function current_uri(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($pos = strpos($uri, '?')) {
        $uri = substr($uri, 0, $pos);
    }
    
    // Suportar subdiretórios se definidos na APP_URL
    if (defined('APP_URL')) {
        $appUrl = APP_URL;
        if (!empty($appUrl)) {
            $parsed = parse_url($appUrl);
            if (isset($parsed['path'])) {
                $path = rtrim($parsed['path'], '/');
                if (!empty($path) && strpos($uri, $path) === 0) {
                    $uri = substr($uri, strlen($path));
                }
            }
        }
    }
    
    return '/' . ltrim($uri, '/');
}

/**
 * Obtém parâmetros de entrada de forma segura (limpa do GET, POST ou JSON payload)
 */
function input(string $key, mixed $default = null): mixed {
    // Tenta obter do POST
    if (isset($_POST[$key])) {
        return Security::cleanInput($_POST[$key]);
    }
    // Tenta obter do GET
    if (isset($_GET[$key])) {
        return Security::cleanInput($_GET[$key]);
    }
    
    // Tenta obter do JSON raw body
    static $jsonData = null;
    if ($jsonData === null) {
        $raw = file_get_contents('php://input');
        $jsonData = json_decode($raw, true) ?: [];
    }
    
    if (isset($jsonData[$key])) {
        return Security::cleanInput($jsonData[$key]);
    }
    
    return $default;
}

/**
 * Envia uma resposta JSON de sucesso (200 OK) e aborta
 */
function json_ok(mixed $data = [], string $msg = ''): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => $msg
    ]);
    exit;
}

/**
 * Envia uma resposta JSON de erro e aborta
 */
function json_error(string $msg, int $code = 400): never {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $msg,
        'code' => $code
    ]);
    exit;
}

/**
 * Adiciona cabeçalhos CORS básicos (para APIs públicas se necessário)
 */
function cors_headers(): void {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

// --- SEGURANÇA ---

/**
 * Sanitiza valores de strings contra ataques XSS
 */
function sanitize(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Atalho para gerar token CSRF
 */
function generate_csrf_token(): string {
    return Security::generateToken();
}

/**
 * Valida o token CSRF recebido (dos forms via _token ou header HTTP-X-CSRF-Token)
 */
function validate_csrf(): bool {
    $token = input('_token');
    if (!$token) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    return Security::validateToken($token);
}

// --- UI / AUXILIARES ---

/**
 * Obtém a URL do avatar de forma segura. Se nulo, cria iniciais do utilizador
 */
function get_avatar_url(?string $url, string $name): string {
    if (!empty($url)) {
        // Se for caminho local absoluto ou relativo, garante URL correta
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        return APP_URL . '/' . ltrim($url, '/');
    }
    
    // Fallback: Gerador de avatar por iniciais
    $initials = urlencode(mb_substr($name, 0, 2));
    return "https://ui-avatars.com/api/?name={$initials}&background=1a2235&color=f97316&bold=true&size=128";
}

/**
 * Formata valores monetários para Kwanzas (AOA) ou outras suportadas
 */
function format_currency(float $amount, string $currency = 'AOA'): string {
    switch (strtoupper($currency)) {
        case 'USD':
            return '$ ' . number_format($amount, 2, '.', ',');
        case 'EUR':
            return '€ ' . number_format($amount, 2, ',', '.');
        case 'AOA':
        default:
            return number_format($amount, 2, ',', '.') . ' Kz';
    }
}

/**
 * Converte data de timestamp para formato relativo em Português
 */
function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    if (!$time) {
        return "agora mesmo";
    }
    
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 5) {
        return "agora mesmo";
    }
    
    $units = [
        31536000 => 'ano',
        2592000  => 'mês',
        604800   => 'semana',
        86400    => 'dia',
        3600     => 'hora',
        60       => 'minuto',
        1        => 'segundo'
    ];
    
    foreach ($units as $secs => $label) {
        $div = $diff / $secs;
        if ($div >= 1) {
            $value = round($div);
            
            // Pluralização simples em PT
            if ($label === 'mês') {
                return 'há ' . $value . ' ' . ($value > 1 ? 'meses' : 'mês');
            }
            
            return 'há ' . $value . ' ' . $label . ($value > 1 ? 's' : '');
        }
    }
    
    return "há pouco tempo";
}

/**
 * Obtém mensagem de flash de sessão atual e limpa a sessão
 */
function get_flash_message(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $msg;
    }
    return null;
}

/**
 * Define uma mensagem do tipo toast / flash
 */
function set_flash_message(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $msg
    ];
}

/**
 * Retorna o caminho para o executável do FFmpeg (global ou fallback local do winget)
 */
function get_ffmpeg_path(): ?string {
    if (!function_exists('exec')) {
        return null;
    }
    
    // 1. Verificar se está globalmente disponível no PATH
    $command = PHP_OS_FAMILY === 'Windows' ? 'where ffmpeg 2>nul' : 'which ffmpeg 2>/dev/null';
    $output = [];
    $returnVar = -1;
    try {
        @exec($command, $output, $returnVar);
        if ($returnVar === 0 && !empty($output)) {
            return 'ffmpeg';
        }
    } catch (Throwable $e) {}

    // 2. Se for Windows, verificar fallback na pasta do WinGet Packages
    if (PHP_OS_FAMILY === 'Windows') {
        $userProfile = getenv('USERPROFILE') ?: ($_SERVER['USERPROFILE'] ?? null);
        if ($userProfile) {
            $wingetPath = rtrim(str_replace('\\', '/', $userProfile), '/') . '/AppData/Local/Microsoft/WinGet/Packages/';
            if (is_dir($wingetPath)) {
                $matches = glob($wingetPath . 'Gyan.FFmpeg*/ffmpeg-*/bin/ffmpeg.exe');
                if (!empty($matches) && file_exists($matches[0])) {
                    return '"' . str_replace('/', DIRECTORY_SEPARATOR, $matches[0]) . '"';
                }
            }
        }
    }
    
    return null;
}

/**
 * Verifica se o executável do FFmpeg está disponível no sistema
 */
function is_ffmpeg_available(): bool {
    return get_ffmpeg_path() !== null;
}
