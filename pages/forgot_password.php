<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if (is_logged_in()) {
    redirect('/feed');
}

$title = 'Recuperar Password — Constrói Já';
require_once __DIR__ . '/../templates/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="card slideUp" style="width: 100%; max-width: 420px; border-radius: var(--radius-lg); padding: 40px; background: rgba(255,255,255,0.02);">
        
        <div style="text-align:center; margin-bottom:30px;">
            <div style="width:56px; height:56px; border-radius:var(--radius-md); background:rgba(249, 115, 22, 0.1); color:var(--accent-primary); display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                <i data-lucide="key-round" style="width:32px; height:32px;"></i>
            </div>
            <h2>Recuperar Password</h2>
            <p style="color:var(--text-secondary); font-size:14px; margin-top:6px;">Insira o seu email para receber instruções de recuperação</p>
        </div>

        <form id="forgot-form" onsubmit="event.preventDefault(); handleForgot();">
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" class="form-control" placeholder="exemplo@dominio.ao" required autocomplete="email">
            </div>

            <button type="submit" id="forgot-btn" class="btn btn-primary" style="width:100%; padding:12px; font-size:15px; margin-top:10px;">
                Enviar Instruções
                <i data-lucide="send"></i>
            </button>
        </form>

        <div style="text-align:center; margin-top:24px; font-size:14px; color:var(--text-secondary);">
            Lembrou-se da password? <a href="/login" style="font-weight:600; color:var(--accent-primary);">Inicie Sessão</a>
        </div>

    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function handleForgot() {
    const btn = document.getElementById('forgot-btn');
    const email = document.getElementById('email').value.trim();

    if (!email) {
        App.showToast('Introduza o seu endereço de email.', 'warning');
        return;
    }

    App.setLoading(btn, true);

    try {
        const response = await App.post('/api/auth/forgot', {
            email: email,
            _token: App.csrfToken
        });
        
        App.showToast(response.message || 'Instruções enviadas com sucesso!', 'success', 6000);
        document.getElementById('email').value = '';
    } catch (error) {
        App.showToast(error.message || 'Erro ao processar o pedido de recuperação.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
