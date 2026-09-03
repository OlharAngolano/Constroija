<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

// Redirecionar para feed se o utilizador estiver ativo
$user = current_user();
if ($user && ($user['status'] ?? '') === 'active' && ((int)($user['is_admin'] ?? 0) === 1 || empty($user['subscription_expires_at']) || $user['subscription_expires_at'] >= date('Y-m-d H:i:s'))) {
    redirect('/feed');
}

$title = 'Conta Suspensa — Constrói Já';
require_once __DIR__ . '/../templates/header.php';
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    .suspended-container {
        min-height: 85vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        background: radial-gradient(circle at top right, rgba(249, 115, 22, 0.05), transparent 40%), var(--bg-primary);
        position: relative;
    }
    
    .suspended-card {
        width: 100%;
        max-width: 520px;
        border-radius: var(--radius-lg);
        padding: 40px;
        background: rgba(26, 32, 50, 0.45);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        position: relative;
        overflow: hidden;
    }
    
    .suspended-card::before {
        content: '';
        position: absolute;
        top: -150px;
        left: -150px;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(239, 68, 68, 0.05) 0%, transparent 70%);
        pointer-events: none;
    }

    .suspended-card::after {
        content: '';
        position: absolute;
        bottom: -150px;
        right: -150px;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(245, 158, 11, 0.05) 0%, transparent 70%);
        pointer-events: none;
    }

    .lock-glow {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(239, 68, 68, 0.2) 0%, rgba(239, 68, 68, 0.03) 70%);
        color: var(--accent-danger);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 24px;
        border: 1px solid rgba(239, 68, 68, 0.3);
        box-shadow: 0 0 25px rgba(239, 68, 68, 0.15);
        animation: pulseLock 2.5s infinite ease-in-out;
    }

    @keyframes pulseLock {
        0%, 100% { transform: scale(1); box-shadow: 0 0 25px rgba(239, 68, 68, 0.15); }
        50% { transform: scale(1.05); box-shadow: 0 0 35px rgba(239, 68, 68, 0.3); }
    }

    .btn-pay-vip {
        background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
        color: #0b0f19 !important;
        border-radius: var(--radius-md);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 16px;
        font-weight: 800;
        font-size: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 16px;
        box-shadow: 0 8px 20px -6px rgba(245, 158, 11, 0.4);
        border: none;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .btn-pay-vip:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px -5px rgba(245, 158, 11, 0.6);
        filter: brightness(1.05);
    }

    .btn-pay-vip:active {
        transform: translateY(0);
    }

    .divider-line {
        display: flex;
        align-items: center;
        text-align: center;
        color: var(--text-muted);
        font-size: 12px;
        margin: 20px 0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .divider-line::before, .divider-line::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .divider-line:not(:empty)::before {
        margin-right: .75em;
    }

    .divider-line:not(:empty)::after {
        margin-left: .75em;
    }
    .susp-logout-link:hover { color: var(--text-primary) !important; }
</style>

<div class="suspended-container">
    <div class="suspended-card slideUp">
        
        <div class="lock-glow">
            <i data-lucide="lock" style="width: 32px; height: 32px;"></i>
        </div>
        
        <h2 style="color: var(--text-primary); margin-bottom: 12px; font-weight: 800; font-size: 28px; letter-spacing: -0.5px;">Acesso Suspenso</h2>
        
        <p style="color: var(--text-secondary); font-size: 15px; line-height: 1.6; margin-bottom: 32px; max-width: 420px; margin-left: auto; margin-right: auto;">
            O seu período de teste expirou ou a sua mensalidade encontra-se pendente. 
            Para continuar a usar o <strong>Constrói Já</strong> e gerir as suas obras com toda a eficiência, regularize a sua mensalidade de apenas <strong>5.000 Kz</strong>.
        </p>

        <!-- OPÇÃO 1: PAGAMENTO AUTOMÁTICO VIA MULTICAIXA (RECOMENDADO) -->
        <a href="/subscription" class="btn-pay-vip">
            <i data-lucide="credit-card" style="margin-right: 10px; width: 18px; height: 18px;"></i>
            Pagar via Multicaixa Express
        </a>
        
        <div class="divider-line">ou</div>
        
        <!-- OPÇÃO 2: BACKUP VIA WHATSAPP -->
        <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: var(--radius-md); padding: 24px; text-align: left; margin-bottom: 32px; backdrop-filter: blur(10px);">
            <h4 style="margin-bottom: 8px; color: var(--text-primary); font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="help-circle" style="color: var(--accent-primary); width: 16px; height: 16px;"></i>
                Envio Manual de Comprovativo
            </h4>
            <p style="color: var(--text-muted); font-size: 13.5px; line-height: 1.5; margin-bottom: 16px;">
                Se já efetuou transferência bancária direta, envie o comprovativo pelo WhatsApp para ativação célere pela equipa.
            </p>
            <a href="https://wa.me/244923972131" target="_blank" class="btn" style="background: #25D366; color: white !important; border-radius: var(--radius-sm); display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 12px; font-weight: 600; font-size: 14.5px; text-decoration: none; border: none; transition: transform 0.2s;">
                <i data-lucide="message-circle" style="margin-right: 8px; width: 18px; height: 18px;"></i>
                Enviar via WhatsApp
            </a>
        </div>

        <a href="#" data-jsaction="App.logout" data-jsprevent="1" style="color: var(--text-muted); font-size: 14px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: color 0.2s;" class="susp-logout-link"">
            <i data-lucide="log-out" style="width: 14px; height: 14px;"></i>
            Terminar Sessão
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
