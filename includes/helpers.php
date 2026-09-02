<?php
declare(strict_types=1);

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
 * Retorna os dados do utilizador atualmente autenticado na sessão
 */
function current_user(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $user = $_SESSION['user'] ?? null;
    if ($user) {
        // Auto-migração para garantir a coluna 'last_activity_at' na tabela profiles
        static $migrationRun = false;
        if (!$migrationRun) {
            try {
                db()->query("SELECT last_activity_at FROM profiles LIMIT 1");
            } catch (PDOException $e) {
                try {
                    db()->execute("ALTER TABLE profiles ADD COLUMN last_activity_at TIMESTAMP NULL DEFAULT NULL");
                } catch (PDOException $e2) {
                    // Ignora silenciosamente
                }
            }
            
            // Auto-migração para posts (is_reel, video_qualities)
            try {
                db()->query("SELECT is_reel FROM posts LIMIT 1");
            } catch (PDOException $e) {
                try {
                    db()->execute("ALTER TABLE posts ADD COLUMN is_reel TINYINT DEFAULT 0");
                } catch (PDOException $e2) {}
            }
            try {
                db()->query("SELECT video_qualities FROM posts LIMIT 1");
            } catch (PDOException $e) {
                try {
                    db()->execute("ALTER TABLE posts ADD COLUMN video_qualities TEXT NULL DEFAULT NULL");
                } catch (PDOException $e2) {}
            }
            
            $migrationRun = true;
        }

        // Atualizar data/hora de última atividade se o último update foi há mais de 60 segundos
        $now = time();
        $lastUpdate = $_SESSION['last_activity_update'] ?? 0;
        if ($now - $lastUpdate > 60) {
            try {
                db()->execute("UPDATE profiles SET last_activity_at = NOW() WHERE id = ?", [$user['id']]);
                $_SESSION['last_activity_update'] = $now;
            } catch (PDOException $e) {
                // Ignora silenciosamente
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
 * Exige autenticação. Redireciona para o login se não autenticado
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
    
    if ((int)($user['is_admin'] ?? 0) === 0 && !empty($user['subscription_expires_at'])) {
        if ($user['subscription_expires_at'] < date('Y-m-d H:i:s')) {
            if (($user['status'] ?? '') !== 'suspended') {
                db()->execute("UPDATE profiles SET status = 'suspended' WHERE id = ?", [$user['id']]);
                $_SESSION['user']['status'] = 'suspended';
            }
            redirect('/subscription');
        } elseif (($user['status'] ?? '') === 'suspended') {
            redirect('/subscription');
        }
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
