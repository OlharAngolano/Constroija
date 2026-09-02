<?php
declare(strict_types=1);

class Security {
    private static ?string $nonce = null;

    /**
     * Rate limiting simples baseado em ficheiros
     */
    public static function rateLimit(string $key, int $max, int $window): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $hash = md5($ip . '_' . $key);
        $limitDir = LOG_DIR . '/rate_limits';
        
        if (!is_dir($limitDir)) {
            mkdir($limitDir, 0750, true);
        }
        
        $file = $limitDir . '/' . $hash . '.json';
        $now = time();
        $data = ['count' => 0, 'expires' => $now + $window];
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content) {
                $parsed = json_decode($content, true);
                if ($parsed && $parsed['expires'] > $now) {
                    $data = $parsed;
                }
            }
        }
        
        // Reiniciar se expirou
        if ($data['expires'] <= $now) {
            $data['count'] = 0;
            $data['expires'] = $now + $window;
        }
        
        $data['count']++;
        file_put_contents($file, json_encode($data));
        
        return $data['count'] <= $max;
    }

    /**
     * Define todos os cabeçalhos de segurança obrigatórios
     */
    public static function setHeaders(string $nonce): void {
        if (headers_sent()) {
            return;
        }
        
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // CSP com Nonce dinâmico
        $csp = "default-src 'self'; " .
               "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; " . // Lucide CDN e Chart.js
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        header("Content-Security-Policy: " . $csp);
        header("Permissions-Policy: geolocation=(), camera=(), microphone=(), interest-cohort=()");
    }

    /**
     * Deteta potenciais ameaças de XSS, SQLi, Null Bytes e Path Traversal
     */
    public static function detectThreats(array $input): bool {
        $xssPatterns = [
            '/<script[^>]*?>.*?<\/script>/is',
            '/<iframe[^>]*?>.*?<\/iframe>/is',
            '/<object[^>]*?>.*?<\/object>/is',
            '/<[^>]+on[a-z]+\s*=\s*[\'"].*?[\'"]/is', // Ex: <img onerror="..."
            '/javascript\s*:\s*(alert|eval|window|document|prompt|confirm)/i'
        ];
        
        $sqliPatterns = [
            '/union\s+all\s+select/i',
            '/union\s+select/i',
            '/[\'"]\s*\bor\b\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+/i',
            '/[\'"]\s*\bor\b\s+[\'"]?[a-zA-Z]+[\'"]?\s*=\s*[\'"]?[a-zA-Z]+/i',
            '/benchmark\s*\(\s*\d+\s*,\s*md5\s*\(/i',
            '/sleep\s*\(\s*\d+\s*\)/i',
            '/pg_sleep\s*\(/i',
            '/[\'"]\s*;?\s*--/i' // SQL comment block sequence
        ];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                if (self::detectThreats($value)) {
                    return true;
                }
                continue;
            }
            
            $valStr = (string)$value;
            
            // 1. Null Bytes
            if (strpos($valStr, "\x00") !== false) {
                return true;
            }
            
            // 2. Path Traversal
            if (strpos($valStr, '../') !== false || strpos($valStr, '..\\') !== false) {
                return true;
            }
            
            // 3. XSS
            foreach ($xssPatterns as $pattern) {
                if (preg_match($pattern, $valStr)) {
                    return true;
                }
            }
            
            // 4. SQL Injection
            foreach ($sqliPatterns as $pattern) {
                if (preg_match($pattern, $valStr)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Gera ou recupera um token CSRF único e forte para a sessão
     */
    public static function generateToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida se um token CSRF recebido é igual ao armazenado na sessão
     */
    public static function validateToken(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Obtém o nonce criptográfico da requisição atual
     */
    public static function getNonce(): string {
        if (self::$nonce === null) {
            self::$nonce = bin2hex(random_bytes(16));
        }
        return self::$nonce;
    }

    /**
     * Limpa e escapa dados do utilizador contra XSS
     */
    public static function cleanInput(mixed $data): mixed {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::cleanInput($value);
            }
            return $data;
        }
        
        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return $data;
    }

    /**
     * Inicialização global do Mini-WAF
     */
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurações de cookies de sessão seguros
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_samesite', 'Strict');
            
            // HTTPS obrigatório para Secure
            if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
                ini_set('session.cookie_secure', '1');
            }
            
            session_start();
        }
        
        // Criar Nonce
        $nonce = self::getNonce();
        
        // Aplicar Headers
        self::setHeaders($nonce);
        
        // Validar ameaças nos inputs recebidos
        if (self::detectThreats($_GET) || self::detectThreats($_POST) || self::detectThreats($_COOKIE)) {
            http_response_code(403);
            
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Acesso bloqueado pelo Mini-WAF de Segurança (ameaça detetada).',
                    'code' => 403
                ]);
            } else {
                echo "<div style='font-family:sans-serif; text-align:center; padding: 50px; background:#0a0f1e; color:#f1f5f9; height: 100vh;'>
                        <h1 style='color:#ef4444;'>Acesso Bloqueado</h1>
                        <p>Foi detetado um comportamento suspeito e o seu acesso foi bloqueado por motivos de segurança.</p>
                      </div>";
            }
            exit;
        }
    }
}
