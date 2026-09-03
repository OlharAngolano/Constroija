<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if (is_logged_in()) {
    redirect('/feed');
}

$title = 'Criar Conta — Constrói Já';
require_once __DIR__ . '/../templates/header.php';
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    @keyframes pulse {
        0%, 100% { transform: scale(1); box-shadow: 0 0 15px rgba(251, 191, 36, 0.1); }
        50% { transform: scale(1.03); box-shadow: 0 0 25px rgba(251, 191, 36, 0.25); }
    }
</style>

<div style="min-height: 85vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="card slideUp" style="width: 100%; max-width: 460px; border-radius: var(--radius-lg); padding: 40px; background: rgba(255,255,255,0.02);">
        
        <div style="text-align:center; margin-bottom:24px;">
            <div style="width:56px; height:56px; border-radius:var(--radius-md); background:rgba(249, 115, 22, 0.1); color:var(--accent-primary); display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                <i data-lucide="user-plus" style="width:30px; height:30px;"></i>
            </div>
            <h2>Criar Conta</h2>
            <p style="color:var(--text-secondary); font-size:14px; margin-top:4px;">Registe-se e comece a gerir as suas obras hoje</p>
            <div style="display:inline-flex; align-items:center; gap:6px; background:rgba(251, 191, 36, 0.08); border:1px solid rgba(251, 191, 36, 0.3); color:#fbbf24; padding:6px 16px; border-radius:50px; font-weight:700; font-size:12.5px; margin-top:12px; animation: pulse 2s infinite ease-in-out;">
                <i data-lucide="gift" style="width:14px; height:14px;"></i>
                <span>🎉 Inclui 3 Dias VIP de Teste Grátis!</span>
            </div>
        </div>

        <form id="register-form" data-jsaction="handleRegister" data-jsprevent="1" novalidate>
            <div class="form-group">
                <label for="name" class="form-label">Nome Completo</label>
                <input type="text" id="name" class="form-control" placeholder="ex: Eng. António Manuel" required>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">Username Único</label>
                <input type="text" id="username" class="form-control" placeholder="ex: antonio.manuel" required>
                <small style="margin-top:4px; display:block; color:var(--text-muted);">Apenas letras, números, pontos (.) ou underscores (_).</small>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Corporativo ou Pessoal</label>
                <input type="email" id="email" class="form-control" placeholder="exemplo@dominio.ao" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div style="position:relative; display:flex; align-items:center;">
                    <input type="password" id="password" class="form-control" placeholder="Mínimo 8 caracteres" required autocomplete="new-password" style="padding-right: 44px; width: 100%;">
                    <button type="button" data-jsaction="togglePasswordVisibility" data-jsarg="password" data-jselement="1" style="position:absolute; right:6px; background:none; border:none; padding:8px; cursor:pointer; color:var(--text-muted); display:inline-flex; align-items:center; justify-content:center; outline:none; transition: color var(--transition-fast);" aria-label="Mostrar/Ocultar password">
                        <i data-lucide="eye" style="width:18px; height:18px;"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirm" class="form-label">Confirmar Password</label>
                <div style="position:relative; display:flex; align-items:center;">
                    <input type="password" id="password_confirm" class="form-control" placeholder="Repita a password anterior" required autocomplete="new-password" style="padding-right: 44px; width: 100%;">
                    <button type="button" data-jsaction="togglePasswordVisibility" data-jsarg="password_confirm" data-jselement="1" style="position:absolute; right:6px; background:none; border:none; padding:8px; cursor:pointer; color:var(--text-muted); display:inline-flex; align-items:center; justify-content:center; outline:none; transition: color var(--transition-fast);" aria-label="Mostrar/Ocultar password">
                        <i data-lucide="eye" style="width:18px; height:18px;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" id="register-btn" class="btn btn-primary" style="width:100%; padding:12px; font-size:15px; margin-top:10px;">
                Registar Conta
                <i data-lucide="user-check"></i>
            </button>
        </form>

        <div style="text-align:center; margin-top:24px; font-size:14px; color:var(--text-secondary);">
            Já tem conta? <a href="/login" style="font-weight:600; color:var(--accent-primary);">Inicie Sessão</a>
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

async function handleRegister() {
    const btn = document.getElementById('register-btn');
    const name = document.getElementById('name').value.trim();
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('password_confirm').value;

    if (!name || !username || !email || !password || !passwordConfirm) {
        App.showToast('Preencha todos os campos obrigatórios.', 'warning');
        return;
    }

    if (password !== passwordConfirm) {
        App.showToast('As passwords introduzidas não coincidem.', 'warning');
        return;
    }

    if (password.length < 8) {
        App.showToast('A password deve ter pelo menos 8 caracteres.', 'warning');
        return;
    }

    App.setLoading(btn, true);

    try {
        const response = await App.post('/api/auth/register', {
            name: name,
            username: username,
            email: email,
            password: password,
            password_confirm: passwordConfirm
        });

        App.showToast(response.message || 'Registo concluído! A iniciar sessão...', 'success');
        
        setTimeout(() => {
            window.location.href = '/feed';
        }, 1200);

    } catch (error) {
        App.showToast(error.message || 'Erro ao efetuar registo.', 'danger');
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
