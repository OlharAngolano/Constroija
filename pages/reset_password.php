<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if (is_logged_in()) {
    redirect('/feed');
}

$token = trim((string)($_GET['token'] ?? ''));

$isValid = false;
$userEmail = '';

if (!empty($token)) {
    // Validar o token na base de dados antes de renderizar a página
    $db = db();
    $user = $db->fetch(
        "SELECT email, password_reset_expires FROM profiles WHERE password_reset_token = ? LIMIT 1",
        [$token]
    );
    
    if ($user !== null) {
        $expires = $user['password_reset_expires'];
        $now = date('Y-m-d H:i:s');
        if ($expires !== null && $expires >= $now) {
            $isValid = true;
            $userEmail = $user['email'];
        } else {
            error_log("Password reset: Token expired. Token: $token, Expires: " . ($expires ?? 'NULL') . ", Now: $now");
        }
    } else {
        error_log("Password reset: Token not found in database. Token: $token");
    }
} else {
    error_log("Password reset: Token parameter is empty.");
}

$title = 'Redefinir Palavra-passe — Constrói Já';
require_once __DIR__ . '/../templates/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="card slideUp" style="width: 100%; max-width: 420px; border-radius: var(--radius-lg); padding: 40px; background: rgba(255,255,255,0.02);">
        
        <?php if (!$isValid): ?>
            <!-- Caso o Token seja Inválido ou Tenha Expirado -->
            <div style="text-align:center; margin-bottom:20px;">
                <div style="width:56px; height:56px; border-radius:var(--radius-md); background:rgba(239, 68, 68, 0.1); color:var(--accent-danger); display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <i data-lucide="alert-triangle" style="width:32px; height:32px;"></i>
                </div>
                <h2>Link Expirado</h2>
                <p style="color:var(--text-secondary); font-size:14px; margin-top:10px; line-height: 1.5;">
                    O link de recuperação de palavra-passe é inválido, já foi utilizado ou expirou (validade de 1 hora).
                </p>
            </div>

            <div style="margin-top: 30px;">
                <a href="/forgot-password" class="btn btn-primary" style="width:100%; padding:12px; font-size:15px; text-align:center; display:block; text-decoration:none;">
                    Solicitar Novo Link
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
        <?php else: ?>
            <!-- Caso o Token seja Válido -->
            <div style="text-align:center; margin-bottom:30px;">
                <div style="width:56px; height:56px; border-radius:var(--radius-md); background:rgba(249, 115, 22, 0.1); color:var(--accent-primary); display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <i data-lucide="shield-check" style="width:32px; height:32px;"></i>
                </div>
                <h2>Nova Palavra-passe</h2>
                <p style="color:var(--text-secondary); font-size:14px; margin-top:6px;">
                    A redefinir palavra-passe para <strong style="color:var(--text-primary);"><?php echo sanitize($userEmail); ?></strong>
                </p>
            </div>

            <form id="reset-form" onsubmit="event.preventDefault(); handleReset();">
                <input type="hidden" id="token" value="<?php echo sanitize($token); ?>">

                <div class="form-group">
                    <label for="password" class="form-label">Nova Palavra-passe</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input type="password" id="password" class="form-control" placeholder="Mínimo 8 caracteres" required autocomplete="new-password" style="padding-right: 44px; width: 100%;">
                        <button type="button" onclick="togglePasswordVisibility('password', this)" style="position:absolute; right:6px; background:none; border:none; padding:8px; cursor:pointer; color:var(--text-muted); display:inline-flex; align-items:center; justify-content:center; outline:none; transition: color var(--transition-fast);" aria-label="Mostrar/Ocultar password">
                            <i data-lucide="eye" style="width:18px; height:18px;"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirmar Palavra-passe</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input type="password" id="password_confirm" class="form-control" placeholder="Repita a palavra-passe" required autocomplete="new-password" style="padding-right: 44px; width: 100%;">
                        <button type="button" onclick="togglePasswordVisibility('password_confirm', this)" style="position:absolute; right:6px; background:none; border:none; padding:8px; cursor:pointer; color:var(--text-muted); display:inline-flex; align-items:center; justify-content:center; outline:none; transition: color var(--transition-fast);" aria-label="Mostrar/Ocultar password">
                            <i data-lucide="eye" style="width:18px; height:18px;"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="reset-btn" class="btn btn-primary" style="width:100%; padding:12px; font-size:15px; margin-top:10px;">
                    Atualizar Palavra-passe
                    <i data-lucide="lock"></i>
                </button>
            </form>
        <?php endif; ?>

        <div style="text-align:center; margin-top:24px; font-size:14px; color:var(--text-secondary);">
            Lembrou-se? <a href="/login" style="font-weight:600; color:var(--accent-primary);">Iniciar Sessão</a>
        </div>

    </div>
</div>

<?php if ($isValid): ?>
<script nonce="<?php echo Security::getNonce(); ?>">
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('[data-lucide]');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.setAttribute('data-lucide', 'eye-off');
    } else {
        input.type = 'password';
        if (icon) icon.setAttribute('data-lucide', 'eye');
    }
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

async function handleReset() {
    const btn = document.getElementById('reset-btn');
    const token = document.getElementById('token').value;
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('password_confirm').value;

    if (!password || !passwordConfirm) {
        App.showToast('Por favor, preencha todos os campos.', 'warning');
        return;
    }

    if (password.length < 8) {
        App.showToast('A palavra-passe deve conter pelo menos 8 caracteres.', 'warning');
        return;
    }

    if (password !== passwordConfirm) {
        App.showToast('As palavras-passe introduzidas não coincidem.', 'danger');
        return;
    }

    App.setLoading(btn, true);

    try {
        const response = await App.post('/api/auth/reset', {
            token: token,
            password: password,
            password_confirm: passwordConfirm,
            _token: App.csrfToken
        });
        
        App.showToast(response.message || 'Palavra-passe alterada com sucesso!', 'success');
        
        setTimeout(() => {
            window.location.href = '/login';
        }, 2000);
    } catch (error) {
        App.showToast(error.message || 'Erro ao redefinir a palavra-passe.', 'danger');
        App.setLoading(btn, false);
    }
}
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
