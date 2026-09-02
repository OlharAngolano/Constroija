<?php
declare(strict_types=1);

/**
 * Classe Mail - Sistema de Envio de Emails profissional para Constrói Já
 * 
 * Implementação resiliente via SMTP raw socket (fsockopen) e fallback transparente para PHP mail().
 * Desenhada para máxima compatibilidade com a infraestrutura da Hostinger.
 */
class Mail {
    /**
     * Envia um email em formato HTML com design premium.
     * 
     * @param string $to Destinatário do email
     * @param string $subject Assunto do email
     * @param string $htmlContent Conteúdo do email em formato HTML
     * @return bool Retorna true se enviado com sucesso, false caso contrário
     */
    public static function send(string $to, string $subject, string $htmlContent): bool {
        // Envolver o conteúdo HTML no template premium do Constrói Já
        $body = self::getPremiumTemplate($subject, $htmlContent);

        // Se as configurações SMTP essenciais não estiverem definidas, usa o fallback mail()
        if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
            return self::sendViaMailFunction($to, $subject, $body);
        }

        // Tentar enviar via SMTP
        try {
            return self::sendViaSMTP($to, $subject, $body);
        } catch (Throwable $e) {
            // Em caso de erro na ligação SMTP, faz o log e recorre ao fallback mail()
            error_log("Erro no Envio SMTP (" . SMTP_HOST . "): " . $e->getMessage() . ". A tentar fallback para mail() nativo.");
            return self::sendViaMailFunction($to, $subject, $body);
        }
    }

    /**
     * Envia o email usando a função nativa mail() do PHP
     */
    private static function sendViaMailFunction(string $to, string $subject, string $body): bool {
        $fromEmail = !empty(SMTP_FROM_EMAIL) ? SMTP_FROM_EMAIL : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'constroija.com');
        $fromName = SMTP_FROM_NAME;

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Envia o email estabelecendo ligação SMTP de baixo nível via sockets
     */
    private static function sendViaSMTP(string $to, string $subject, string $body): bool {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $username = SMTP_USER;
        $password = SMTP_PASS;
        $secure = strtolower(SMTP_SECURE);
        $fromEmail = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;

        $socketPrefix = '';
        if ($secure === 'ssl') {
            $socketPrefix = 'ssl://';
        }

        // Criar um contexto SSL robusto para evitar falhas de validação de certificados
        // comuns em servidores partilhados como a Hostinger
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        // Ligar ao servidor SMTP usando stream_socket_client para passar o contexto SSL
        $socket = @stream_socket_client(
            $socketPrefix . $host . ':' . $port,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$socket) {
            throw new Exception("Falha ao ligar ao servidor SMTP {$host}:{$port} ({$errno} - {$errstr})");
        }

        // Ler a saudação inicial do servidor (deve retornar 220)
        self::smtpRead($socket, '220');

        // Cumprimentar o servidor
        self::smtpWrite($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        self::smtpRead($socket, '250');

        // Se TLS estiver configurado, inicia o protocolo de segurança
        if ($secure === 'tls') {
            self::smtpWrite($socket, "STARTTLS");
            self::smtpRead($socket, '220');

            // Habilitar a encriptação criptográfica no socket
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            // Compatibilidade adicional para PHP 8.x
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }

            if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                fclose($socket);
                throw new Exception("Falha ao iniciar encriptação TLS.");
            }

            // Repetir EHLO pós-criptografia
            self::smtpWrite($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            self::smtpRead($socket, '250');
        }

        // Autenticação SMTP
        self::smtpWrite($socket, "AUTH LOGIN");
        self::smtpRead($socket, '334');

        self::smtpWrite($socket, base64_encode($username));
        self::smtpRead($socket, '334');

        self::smtpWrite($socket, base64_encode($password));
        self::smtpRead($socket, '235');

        // Definir remetente e destinatário
        self::smtpWrite($socket, "MAIL FROM:<" . $fromEmail . ">");
        self::smtpRead($socket, '250');

        self::smtpWrite($socket, "RCPT TO:<" . $to . ">");
        self::smtpRead($socket, '250');

        // Solicitar envio do corpo de dados
        self::smtpWrite($socket, "DATA");
        self::smtpRead($socket, '354');

        // Construir os cabeçalhos de email em conformidade RFC
        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <" . $fromEmail . ">",
            "To: <" . $to . ">",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "X-Mailer: Constrói Já SMTP Mailer 1.0"
        ];

        // Escrever cabeçalhos e mensagem
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
        self::smtpWrite($socket, $message);
        self::smtpRead($socket, '250');

        // Terminar a sessão
        self::smtpWrite($socket, "QUIT");
        fclose($socket);

        return true;
    }

    /**
     * Escreve comandos ou dados no socket SMTP
     */
    private static function smtpWrite($socket, string $data): void {
        fwrite($socket, $data . "\r\n");
    }

    /**
     * Lê a resposta do socket SMTP e verifica o código de resposta esperado
     */
    private static function smtpRead($socket, string $expectedCode): string {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // Se a linha tem um espaço após o 4º caracter (ex: "250 "), terminou de receber a resposta daquele comando
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        
        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP erro: Esperado código {$expectedCode}, recebido: " . trim($response));
        }

        return $response;
    }

    /**
     * Retorna um design premium para o corpo do email HTML
     */
    private static function getPremiumTemplate(string $title, string $content): string {
        return '
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($title) . '</title>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background-color: #0a0f1d;
                    color: #f1f5f9;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 40px 20px;
                }
                .card {
                    background-color: #111827;
                    border: 1px solid #1f2937;
                    border-radius: 16px;
                    padding: 40px;
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .logo {
                    font-size: 24px;
                    font-weight: 800;
                    color: #f97316;
                    text-decoration: none;
                    letter-spacing: -0.5px;
                }
                .content {
                    font-size: 16px;
                    line-height: 1.6;
                    color: #d1d5db;
                }
                .button {
                    display: inline-block;
                    background-color: #f97316;
                    color: #ffffff !important;
                    text-decoration: none;
                    font-weight: 600;
                    padding: 12px 24px;
                    border-radius: 8px;
                    margin: 24px 0;
                    text-align: center;
                }
                .footer {
                    text-align: center;
                    margin-top: 30px;
                    font-size: 12px;
                    color: #6b7280;
                    line-height: 1.5;
                }
                a {
                    color: #f97316;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <a href="' . APP_URL . '" class="logo">Constrói Já</a>
                </div>
                <div class="card">
                    <div class="content">
                        ' . $content . '
                    </div>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Constrói Já. Todos os direitos reservados.</p>
                    <p>Este é um email automático, por favor não responda diretamente.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
}
