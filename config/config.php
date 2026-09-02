<?php
declare(strict_types=1);

// Definir timezone padrão para Angola (Luanda)
date_default_timezone_set('Africa/Luanda');

// Carregar variáveis de ambiente do ficheiro .env se existir
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignorar comentários
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        // Dividir por '='
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remover aspas se existirem
            if (preg_match('/^"(.+)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.+)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

/**
 * Helper para obter variáveis de ambiente com valor padrão
 */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        $value = getenv($key);
        if ($value === false) {
            if (isset($_ENV[$key])) {
                $value = $_ENV[$key];
            } elseif (isset($_SERVER[$key])) {
                $value = $_SERVER[$key];
            } else {
                return $default;
            }
        }
        if ($value === 'true' || $value === true) return true;
        if ($value === 'false' || $value === false) return false;
        return $value;
    }
}

// Configurar Constantes Globais de Ambiente
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_DEBUG', env('APP_DEBUG', true));

$appUrl = env('APP_URL', 'http://localhost:8000');
if ($appUrl !== '' && strpos($appUrl, 'http://') !== 0 && strpos($appUrl, 'https://') !== 0) {
    $isHttps = false;
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) {
        $isHttps = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $isHttps = true;
    }
    $appUrl = ($isHttps ? 'https://' : 'http://') . $appUrl;
}
define('APP_URL', rtrim($appUrl, '/'));

// Configurações de Base de Dados
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'constroija'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Configurações de SMTP para Envio de Emails
define('SMTP_HOST', env('SMTP_HOST', ''));
define('SMTP_PORT', (int)env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_SECURE', env('SMTP_SECURE', 'tls')); // 'ssl', 'tls' ou vazio
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Constrói Já'));


// Diretórios principais
define('ROOT_DIR', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_DIR . '/uploads');
define('LOG_DIR', ROOT_DIR . '/logs');

// Gestão de Erros baseada no ambiente
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0750, true);
    }
    ini_set('error_log', LOG_DIR . '/php_errors.log');
}
