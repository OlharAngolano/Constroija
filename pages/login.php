<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if (is_logged_in()) {
    redirect('/feed');
}

$title = 'Iniciar Sessão — Constrói Já';
require_once __DIR__ . '/../templates/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="card slideUp" style="width: 100%; max-width: 420px; border-radius: var(--radius-lg); padding: 40px; background: rgba(255,255,255,0.02);">
        
        <div style="text-align:center; margin-bottom:30px;">
            <div style="width:56px; height:56px; border-radius:var(--radius-md); background:rgba(249, 115, 22, 0.1); color:var(--accent-primary); display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                <i data-lucide="hard-hat" style="width:32px; height:32px;"></i>
            </div>
            <h2>Iniciar Sessão</h2>
            <p style="color:var(--text-secondary); font-size:14px; margin-top:6px;">Aceda à gestão financeira das suas obras</p>
        </div>

        <form id="login-form" data-jsaction="handleLogin" data-jsprevent="1" novalidate>
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" class="form-control" placeholder="exemplo@dominio.ao" required autocomplete="email">
            </div>

            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label for="password" class="form-label" style="margin-bottom:0;">Password</label>
                    <a href="/forgot-password" style="font-size:12px; color:var(--text-secondary);">Esqueceu a password?</a>
                </div>
                <div style="position:relative; display:flex; align-items:center;">
                    <input type="password" id="password" class="form-control" placeholder="••••••••" required autocomplete="current-password" style="padding-right: 44px; width: 100%;">
                    <button type="button" data-jsaction="togglePasswordVisibility" data-jsarg="password" data-jselement="1" style="position:absolute; right:6px; background:none; border:none; padding:8px; cursor:pointer; color:var(--text-muted); display:inline-flex; align-items:center; justify-content:center; outline:none; transition: color var(--transition-fast);" aria-label="Mostrar/Ocultar password">
                        <i data-lucide="eye" style="width:18px; height:18px;"></i>
                    </button>
                </div>
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" id="remember_me" style="width:16px; height:16px; accent-color:var(--accent-primary); cursor:pointer;">
                <label for="remember_me" style="font-size:13px; color:var(--text-secondary); cursor:pointer; user-select:none;">Lembrar-me neste dispositivo</label>
            </div>

            <button type="submit" id="login-btn" class="btn btn-primary" style="width:100%; padding:12px; font-size:15px; margin-top:10px;">
                Entrar
                <i data-lucide="log-in"></i>
            </button>
        </form>

        <div style="text-align:center; margin-top:24px; font-size:14px; color:var(--text-secondary);">
            Ainda não tem conta? <a href="/register" style="font-weight:600; color:var(--accent-primary);">Registe-se já</a>
        </div>

    </div>
</div>

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

async function handleLogin() {
    const btn = document.getElementById('login-btn');
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const remember = document.getElementById('remember_me').checked;

    if (!email || !password) {
        App.showToast('Introduza o email e a password.', 'warning');
        return;
    }

    App.setLoading(btn, true);

    try {
        const response = await App.post('/api/auth/login', {
            email: email,
            password: password,
            remember_me: remember
        });

        // Os dados da API ficam dentro de `data` (incluindo o redireccionamento
        // especial de contas suspensas).
        const result = response.data || {};
        const redirectUrl = result.redirect || '/feed';
        const toastType = result.redirect ? 'warning' : 'success';
        
        App.showToast(response.message || 'Sessão iniciada com sucesso!', toastType);
        
        setTimeout(() => {
            window.location.href = redirectUrl;
        }, 1000);

    } catch (error) {
        App.showToast(error.message || 'Erro ao iniciar sessão.', 'danger');
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
