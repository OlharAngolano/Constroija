<?php
declare(strict_types=1);

require_once ROOT_DIR . '/includes/Mail.php';

// 1. Validar se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

// 2. Validar Token CSRF
if (!validate_csrf()) {
    json_error('Token CSRF inválido ou em falta.', 403);
}

// 3. Aplicar Rate Limiting (máximo 5 pedidos por hora)
if (!Security::rateLimit('auth_forgot', 5, 3600)) {
    json_error('Demasiados pedidos de recuperação. Por favor, tente novamente dentro de uma hora.', 429);
}

// 4. Capturar e validar o email
$email = trim((string)input('email', ''));
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Por favor, introduza um endereço de email válido.', 400);
}

// 5. Procurar o utilizador na base de dados
$db = db();
$user = $db->fetch("SELECT id, name, email FROM profiles WHERE email = ? LIMIT 1", [$email]);

if ($user !== null) {
    $userId = (int)$user['id'];
    $name = $user['name'];

    // 6. Gerar Token seguro com alta entropia
    $token = bin2hex(random_bytes(32));

    // 7. Calcular a expiração baseada no fuso horário do PHP (Luanda) para evitar divergências com o MySQL
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // 8. Atualizar a base de dados com o token e a data de expiração (1 hora)
    // Usamos Prepared Statement estrito para segurança contra SQLi
    $db->execute(
        "UPDATE profiles SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?",
        [$token, $expires, $userId]
    );

    error_log("Password reset requested for email '$email'. Token: '$token', Expires: '$expires'");

    // 8. Construir o link e o corpo do email HTML premium
    $resetLink = APP_URL . '/reset-password?token=' . $token;

    $htmlBody = '
    <h2 style="color: #f97316; margin-top: 0;">Recuperação de Palavra-passe</h2>
    <p>Olá <strong>' . sanitize($name) . '</strong>,</p>
    <p>Recebemos uma solicitação para redefinir a palavra-passe da sua conta na plataforma <strong>Constrói Já</strong>.</p>
    <p>Para prosseguir com a redefinição, clique no botão abaixo:</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="' . $resetLink . '" class="button" style="color: #ffffff;">Redefinir Palavra-passe</a>
    </div>
    
    <p>Este link de recuperação é estritamente pessoal e <strong>expira em 1 hora</strong>.</p>
    <p style="color: #9ca3af; font-size: 14px;">Se não solicitou esta redefinição, pode ignorar este email com segurança; a sua palavra-passe atual permanecerá inalterada.</p>
    <hr style="border: 0; border-top: 1px solid #1f2937; margin: 20px 0;">
    <p style="font-size: 12px; color: #9ca3af;">Se estiver a ter problemas ao clicar no botão, copie e cole o URL abaixo no seu navegador:</p>
    <p style="font-size: 12px; word-break: break-all;"><a href="' . $resetLink . '">' . $resetLink . '</a></p>
    ';

    // 9. Enviar o email de forma assíncrona/segura
    Mail::send($email, 'Recuperar Palavra-passe — Constrói Já', $htmlBody);
}

// 10. Retornar sempre resposta genérica de sucesso para evitar enumeração de utilizadores
json_ok([], 'Se o email introduzido estiver registado, receberá um link de recuperação brevemente.');
