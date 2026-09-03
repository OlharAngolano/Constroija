<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

// Exige autenticação básica
middleware_require_auth();

$user = current_user();
$db = db();

// Buscar estado mais atualizado do utilizador
$profile = $db->fetch("SELECT * FROM profiles WHERE id = ?", [$user['id']]);
if ($profile) {
    // Sincroniza a sessão apenas com o DTO seguro (CJ-04)
    $_SESSION['user'] = user_session_dto($profile);
} else {
    $profile = $user;
}

$now = new DateTime();
$expiresAt = $profile['subscription_expires_at'] ? new DateTime($profile['subscription_expires_at']) : null;
$isExpired = $expiresAt && $expiresAt <= $now;
$isVIP = $profile['status'] === 'active' && $expiresAt && !$isExpired;
// 'suspended' fica reservado à moderação da conta (CJ-11): a expiração do
// premium nunca suspende; o membro volta ao plano gratuito.
$isSuspended = ($profile['status'] === 'suspended');
$premiumJustExpired = !$isSuspended && $isExpired;

// Razão exibida na página: 'admin' (moderação) ou 'expired' (contas legadas
// suspensas por faturação antes da correção CJ-11).
$suspendedReason = '';
if ($isSuspended) {
    $suspendedReason = $isExpired ? 'expired' : 'admin';
}

// Pedido de pagamento pendente (CJ-01): o pagamento só é ativado após
// confirmação da equipa — nunca pelo próprio utilizador.
$pendingOrder = null;
try {
    $pendingOrder = $db->fetch(
        "SELECT id, plan_name, amount, currency, created_at FROM payment_orders
         WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
        [$user['id']]
    );
} catch (PDOException $e) {
    // Tabela ainda não migrada — ignora
}

$isTrial = false;
if ($isVIP && !empty($profile['created_at'])) {
    $createdAt = new DateTime($profile['created_at']);
    $diff = $now->getTimestamp() - $createdAt->getTimestamp();
    if ($diff <= 3 * 24 * 60 * 60) {
        $isTrial = true;
    }
}

$title = 'Subscrição VIP — Constrói Já';
require_once __DIR__ . '/../templates/header.php';
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    /* Variables and Theme overrides for premium experience */
    :root {
        --vip-gold: #fbbf24;
        --vip-gold-glow: rgba(251, 191, 36, 0.15);
        --vip-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --vip-card-shadow: 0 10px 30px -10px rgba(245, 158, 11, 0.2);
    }

    .pricing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-top: 20px;
    }

    .pricing-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 32px;
        position: relative;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .pricing-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-lg);
        border-color: var(--text-muted);
    }

    .pricing-card.popular {
        border-color: var(--accent-primary);
        box-shadow: 0 10px 40px -15px rgba(249, 115, 22, 0.15);
    }

    .pricing-card.popular::before {
        content: 'Recomendado';
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--accent-primary);
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 4px 16px;
        border-radius: 50px;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
    }

    .pricing-card.vip {
        border-color: var(--vip-gold);
        box-shadow: var(--vip-card-shadow);
        background: linear-gradient(to bottom, var(--bg-card), rgba(251, 191, 36, 0.02));
    }

    .pricing-card.vip::before {
        content: 'Melhor Valor';
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--vip-gradient);
        color: #0b0f19;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 4px 16px;
        border-radius: 50px;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
    }

    .features-list {
        list-style: none;
        padding: 0;
        margin: 24px 0 32px 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .features-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: var(--text-secondary);
    }

    .features-list li i {
        flex-shrink: 0;
    }

    .reference-box {
        background: rgba(0, 0, 0, 0.2);
        border: 1px dashed var(--border-color);
        border-radius: var(--radius-md);
        padding: 20px;
        font-family: monospace;
        margin-top: 20px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        position: relative;
        overflow: hidden;
    }

    .reference-row {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
    }

    .reference-label {
        color: var(--text-secondary);
        font-family: 'Inter', sans-serif;
    }

    .reference-value {
        color: var(--text-primary);
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .atm-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        border-radius: 16px;
        padding: 24px;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .atm-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 80%);
        pointer-events: none;
    }

</style>

<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 30px;" class="slideUp">
    
    <?php if ($isSuspended): ?>
        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-md); padding: 20px; display: flex; align-items: center; gap: 16px; margin-bottom: -10px; animation: fadeIn var(--transition-normal) ease;">
            <div style="background: rgba(239, 68, 68, 0.15); color: var(--accent-danger); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i data-lucide="<?php echo $suspendedReason === 'admin' ? 'shield-off' : 'alert-triangle'; ?>" style="width: 22px; height: 22px;"></i>
            </div>
            <div style="flex:1;">
                <?php if ($suspendedReason === 'admin'): ?>
                    <h4 style="margin: 0; color: var(--accent-danger); font-size: 15px; font-weight: 700;">Conta Suspensa pela Administração</h4>
                    <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13.5px; line-height: 1.5;">
                        A sua conta foi suspensa por motivos de moderação. Contacte a equipa pelo WhatsApp 
                        <a href="https://wa.me/244923972131" target="_blank" style="color:#25d366; font-weight:700; text-decoration:none;">+244 923 972 131</a>
                        para regularizar a situação.
                    </p>
                <?php else: ?>
                    <h4 style="margin: 0; color: var(--accent-danger); font-size: 15px; font-weight: 700;">Subscrição Expirada — Renove o Acesso</h4>
                    <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13.5px; line-height: 1.5;">
                        O seu período de teste expirou ou a sua subscrição VIP terminou. Selecione um dos planos abaixo e regularize a transferência para reativar o acesso total imediato a todas as ferramentas e relatórios!
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($premiumJustExpired): ?>
        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-md); padding: 20px; display: flex; align-items: center; gap: 16px; margin-bottom: -10px; animation: fadeIn var(--transition-normal) ease;">
            <div style="background: rgba(239, 68, 68, 0.15); color: var(--accent-danger); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i data-lucide="alert-triangle" style="width: 22px; height: 22px;"></i>
            </div>
            <div style="flex:1;">
                <h4 style="margin: 0; color: var(--accent-danger); font-size: 15px; font-weight: 700;">Subscrição Expirada — Plano Gratuito</h4>
                <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13.5px; line-height: 1.5;">
                    A sua subscrição VIP terminou e a sua conta passou ao plano gratuito (não foi suspensa).
                    Renove quando quiser para recuperar o acesso às ferramentas premium.
                </p>
            </div>
        </div>
    <?php elseif (!$isVIP): ?>
        <div style="background: rgba(249, 115, 22, 0.08); border: 1px solid rgba(249, 115, 22, 0.25); border-radius: var(--radius-md); padding: 20px; display: flex; align-items: center; gap: 16px; margin-bottom: -10px; animation: fadeIn var(--transition-normal) ease;">
            <div style="background: rgba(249, 115, 22, 0.15); color: var(--accent-primary); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i data-lucide="info" style="width: 22px; height: 22px;"></i>
            </div>
            <div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 15px; font-weight: 700;">Sem Plano Ativo</h4>
                <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13.5px; line-height: 1.5;">
                    Selecione um dos planos abaixo para ativar a sua subscrição VIP e desbloquear todas as funcionalidades premium!
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- CABEÇALHO -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="font-family:'Outfit', sans-serif; font-weight:800; font-size:28px;">Plano Premium Constrói Já VIP</h2>
            <p style="color:var(--text-secondary); font-size:14px;">Controle os seus custos, libere cupões B2B exclusivos e posicione a sua marca como líder do setor.</p>
        </div>
        
        <div>
            <?php if ($isVIP): ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); color: var(--vip-gold); padding: 8px 16px; border-radius: 50px; font-weight: 600; font-size: 14px;">
                    <i data-lucide="award" style="width: 18px; height: 18px;"></i>
                    <?php if ($isTrial): ?>
                        Membro VIP Ativo (Teste Grátis — Expira em: <?php echo $expiresAt->format('d/m/Y'); ?>)
                    <?php else: ?>
                        Membro VIP Ativo (Expira em: <?php echo $expiresAt->format('d/m/Y'); ?>)
                    <?php endif; ?>
                </div>
            <?php elseif ($isSuspended): ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--accent-danger); padding: 8px 16px; border-radius: 50px; font-weight: 600; font-size: 14px;">
                    <i data-lucide="shield-off" style="width: 18px; height: 18px;"></i>
                    <?php echo $suspendedReason === 'admin' ? 'Conta Suspensa pela Administração' : 'Subscrição Expirada — Renovar'; ?>
                </div>
            <?php else: ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(249, 115, 22, 0.1); border: 1px solid rgba(249, 115, 22, 0.3); color: var(--accent-primary); padding: 8px 16px; border-radius: 50px; font-weight: 600; font-size: 14px;">
                    <i data-lucide="info" style="width: 18px; height: 18px;"></i>
                    Sem Plano Ativo
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BENEFÍCIOS DO PLANO VIP -->
    <div class="card" style="padding: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; background: linear-gradient(135deg, rgba(25, 30, 50, 0.3) 0%, rgba(15, 20, 35, 0.3) 100%);">
        <div style="display: flex; gap: 12px; align-items: flex-start;">
            <div style="background: rgba(249, 115, 22, 0.1); padding: 8px; border-radius: 8px; color: var(--accent-primary);"><i data-lucide="bar-chart-3"></i></div>
            <div>
                <h5 style="margin: 0; font-size: 14px;">Desvio Orçamental Completo</h5>
                <small style="color: var(--text-secondary); display: block; margin-top: 4px;">Compare o planeado vs. real em gráficos detalhados reativos por fase de obra.</small>
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: flex-start;">
            <div style="background: rgba(251, 191, 36, 0.1); padding: 8px; border-radius: 8px; color: var(--vip-gold);"><i data-lucide="gift"></i></div>
            <div>
                <h5 style="margin: 0; font-size: 14px;">Parcerias B2B Desbloqueadas</h5>
                <small style="color: var(--text-secondary); display: block; margin-top: 4px;">Aceda a cupões oficiais de desconto com parceiros como Sika, Secil e Cimentos.</small>
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: flex-start;">
            <div style="background: rgba(59, 130, 246, 0.1); padding: 8px; border-radius: 8px; color: var(--accent-secondary);"><i data-lucide="phone"></i></div>
            <div>
                <h5 style="margin: 0; font-size: 14px;">Alertas Críticos Imediatos</h5>
                <small style="color: var(--text-secondary); display: block; margin-top: 4px;">Seja notificado no telemóvel quando os limites de orçamento por fase estourarem.</small>
            </div>
        </div>
    </div>

    <?php if ($pendingOrder): ?>
        <div style="background: rgba(251, 191, 36, 0.08); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: var(--radius-md); padding: 18px 22px; display: flex; align-items: center; gap: 14px; margin-top: 20px;">
            <i data-lucide="clock" style="color: var(--vip-gold); width: 22px; height: 22px; flex-shrink: 0;"></i>
            <div style="font-size: 13.5px; line-height: 1.5; color: var(--text-secondary);">
                <strong style="color: var(--text-primary);">Pedido de ativação pendente (#<?php echo (int)$pendingOrder['id']; ?>)</strong> —
                plano <strong><?php echo sanitize($pendingOrder['plan_name']); ?></strong> de
                <?php echo format_currency((float)$pendingOrder['amount'], $pendingOrder['currency'] ?? 'AOA'); ?>.
                Assim que a equipa confirmar a transferência, o seu acesso é ativado automaticamente.
            </div>
        </div>
    <?php endif; ?>

    <!-- PRICING CARDS -->
    <div class="pricing-grid">
        <!-- PLANO 1: MESTRE DE OBRA -->
        <div class="pricing-card">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h3 style="font-family:'Outfit', sans-serif; font-weight:800; font-size:20px; margin:0;">Mestre de Obra</h3>
                    <span style="font-size:11px; background:rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; font-weight: 600;">1 MÊS</span>
                </div>
                <p style="color:var(--text-secondary); font-size:13px; margin: 8px 0 20px 0;">Ideal para profissionais individuais e obras pequenas de rápida execução.</p>
                <div style="display:flex; align-items:baseline; gap:4px;">
                    <span style="font-size:32px; font-weight:800; font-family:'Outfit', sans-serif;">5.000 Kz</span>
                    <span style="color:var(--text-muted); font-size:14px;">/mês</span>
                </div>
                
                <ul class="features-list">
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> 1 Projeto Ativo Simultâneo</li>
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Controlo de Despesas Básicas</li>
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Descontos B2B Limitados</li>
                    <li><i data-lucide="x" style="color: var(--accent-danger); width:16px; height:16px;"></i> Sem Notificações Push</li>
                </ul>
            </div>
            <button class="btn btn-secondary" style="width:100%;" data-jsaction="selectPlan" data-jsarg="pro1">Escolher Plano</button>
        </div>

        <!-- PLANO 2: EMPREITEIRO PRO -->
        <div class="pricing-card popular">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h3 style="font-family:'Outfit', sans-serif; font-weight:800; font-size:20px; margin:0; color:var(--accent-primary);">Empreiteiro Pro</h3>
                    <span style="font-size:11px; background:rgba(249,115,22,0.1); color:var(--accent-primary); padding: 4px 8px; border-radius: 4px; font-weight: 700;">3 MESES</span>
                </div>
                <p style="color:var(--text-secondary); font-size:13px; margin: 8px 0 20px 0;">O mais popular. Perfeito para construtores com múltiplos canteiros ativos.</p>
                <div style="display:flex; align-items:baseline; gap:4px;">
                    <span style="font-size:32px; font-weight:800; font-family:'Outfit', sans-serif;">12.000 Kz</span>
                    <span style="color:var(--text-muted); font-size:14px;">/trimestre</span>
                </div>
                <small style="color:var(--accent-success); font-weight:600; display:block; margin-top:2px;">Poupa 3.000 Kz (Média 4.000 Kz/mês)</small>
                
                <ul class="features-list">
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Projetos Ilimitados</li>
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Relatórios PDF de Auditoria</li>
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Todos os Cupões B2B</li>
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Alertas WhatsApp Ativos</li>
                </ul>
            </div>
            <button class="btn btn-primary" style="width:100%;" data-jsaction="selectPlan" data-jsarg="pro3">Escolher Plano</button>
        </div>

        <!-- PLANO 3: CONSTRUTOR VIP -->
        <div class="pricing-card vip">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h3 style="font-family:'Outfit', sans-serif; font-weight:800; font-size:20px; margin:0; color:var(--vip-gold);">Construtor VIP</h3>
                    <span style="font-size:11px; background:rgba(251,191,36,0.1); color:var(--vip-gold); padding: 4px 8px; border-radius: 4px; font-weight: 800;">6 MESES</span>
                </div>
                <p style="color:var(--text-secondary); font-size:13px; margin: 8px 0 20px 0;">Plano de máxima autoridade empresarial. Prioridade em suporte e leads.</p>
                <div style="display:flex; align-items:baseline; gap:4px;">
                    <span style="font-size:32px; font-weight:800; font-family:'Outfit', sans-serif;">20.000 Kz</span>
                    <span style="color:var(--text-muted); font-size:14px;">/semestre</span>
                </div>
                <small style="color:var(--vip-gold); font-weight:700; display:block; margin-top:2px;">Poupa 10.000 Kz (Média 3.333 Kz/mês)</small>
                
                <ul class="features-list">
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Tudo do Plano Pro</li>
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Crachá VIP no Perfil Social</li>
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Posição Top na Pesquisa B2B</li>
                    <li><i data-lucide="check" style="color: var(--accent-success); width:16px; height:16px;"></i> Acesso a Leads Diretos de Clientes</li>
                </ul>
            </div>
            <button class="btn btn-secondary" style="width:100%; border-color:var(--vip-gold); color:var(--vip-gold);" data-jsaction="selectPlan" data-jsarg="pro6">Escolher Plano</button>
        </div>
    </div>

    <!-- AREA DE PAGAMENTO (DADOS DE TRANSFERÊNCIA VIP) -->
    <div id="payment-section" class="card" style="display: none; padding: 32px; border-color: var(--vip-gold); animation: fadeIn 0.4s ease;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
            <h4 style="font-family:'Outfit', sans-serif; font-weight:800; font-size:20px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="credit-card" style="color: var(--vip-gold);"></i>
                Dados de Pagamento de Subscrição
            </h4>
            <span style="font-size: 13px; font-weight: 600; background: rgba(251, 191, 36, 0.1); color: var(--vip-gold); padding: 4px 12px; border-radius: 50px;" id="selected-plan-badge">Mestre de Obra</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: center; flex-wrap: wrap;">
            
            <!-- Premium Transfer Visual Card -->
            <div class="atm-card" style="background: linear-gradient(135deg, #1e1b4b 0%, #0f0b29 100%); border-color: rgba(251, 191, 36, 0.2); box-shadow: 0 15px 35px rgba(251, 191, 36, 0.05);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
                    <span style="font-family:'Outfit', sans-serif; font-size:12px; font-weight:800; letter-spacing:1px; color:#a5b4fc;">DADOS DE TRANSFERÊNCIA</span>
                    <i data-lucide="award" style="width:22px; height:22px; color:var(--vip-gold);"></i>
                </div>
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="background: rgba(0,0,0,0.25); padding: 12px; border-radius: 8px; display:flex; flex-direction:column; gap:4px; border: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size:9px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">IBAN de Pagamento</span>
                        <span style="font-size:13px; font-weight:800; font-family:monospace; color:#38bdf8; word-break:break-all; user-select:all;" id="iban-val">AO06 0006 0000 91657733302 87</span>
                    </div>
                    <div style="background: rgba(0,0,0,0.25); padding: 12px; border-radius: 8px; display:flex; flex-direction:column; gap:4px; border: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size:9px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Titular da Conta</span>
                        <span style="font-size:13px; font-weight:800; color:#ffffff;">Francisco Filipe Venancio</span>
                    </div>
                    <div style="background: rgba(0,0,0,0.25); padding: 12px; border-radius: 8px; display:flex; flex-direction:column; gap:4px; border: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size:9px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Express / Telefone</span>
                        <span style="font-size:14px; font-weight:800; font-family:monospace; color:#fbbf24;">923972131</span>
                    </div>
                    <div style="background: rgba(0,0,0,0.25); padding: 12px; border-radius: 8px; display:flex; flex-direction:column; gap:4px; border: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size:9px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Montante do Plano</span>
                        <span style="font-size:16px; font-weight:800; font-family:monospace; color:#4ade80;" id="atm-amount-val">5.000,00 Kz</span>
                    </div>
                </div>
            </div>

            <!-- Como Pagar e Botão Simulador -->
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <h5 style="margin: 0; font-size:15px; font-family:'Outfit', sans-serif;">Como efetuar o pagamento:</h5>
                <ul style="margin:0; padding-left: 0; font-size: 13.5px; color: var(--text-secondary); display:flex; flex-direction:column; gap:12px; list-style-type:none;">
                    <li style="display:flex; align-items:flex-start; gap:8px;">
                        <i data-lucide="smartphone" style="color:var(--accent-primary); width:18px; height:18px; flex-shrink:0; margin-top:2px;"></i>
                        <span>Efetue a transferência via <strong>Multicaixa Express</strong> ou Internet Banking para o IBAN indicado à esquerda.</span>
                    </li>
                    <li style="display:flex; align-items:flex-start; gap:8px;">
                        <i data-lucide="send" style="color:var(--accent-secondary); width:18px; height:18px; flex-shrink:0; margin-top:2px;"></i>
                        <span>Envie o comprovativo de pagamento diretamente via WhatsApp para o número: <a href="https://wa.me/244923972131" target="_blank" style="color:#25d366; font-weight:700; text-decoration:none;"><strong>+244 923 972 131</strong></a>.</span>
                    </li>
                    <li style="display:flex; align-items:flex-start; gap:8px;">
                        <i data-lucide="globe" style="color:var(--vip-gold); width:18px; height:18px; flex-shrink:0; margin-top:2px;"></i>
                        <span><strong>Europa / Internacional:</strong> Por favor, chame-nos no WhatsApp <a href="https://wa.me/244923972131" target="_blank" style="color:#25d366; font-weight:700; text-decoration:none;"><strong>+244 923 972 131</strong></a> para receber as instruções e o IBAN internacional.</span>
                    </li>
                </ul>

                <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
                    <a id="whatsapp-submit-btn" href="https://wa.me/244923972131?text=Ol%C3%A1%2C%20gostaria%20de%20confirmar%20o%20pagamento%20da%20minha%20assinatura%20VIP%20no%20Constr%C3%B3i%20J%C3%A1.%20Seguem%20os%20meus%20detalhes%20e%20o%20comprovativo%20em%20anexo." target="_blank" class="btn btn-primary" style="background:#25D366; border-color:#25D366; box-shadow: 0 4px 14px rgba(37,211,102,0.3); font-weight: 700; display: flex; justify-content: center; align-items: center; gap: 8px; text-decoration:none;">
                        <i data-lucide="message-square"></i>
                        Enviar Comprovativo pelo WhatsApp
                    </a>

                    <!-- (CJ-01) Sem simulador: registar um pedido de ativação para
                         confirmação manual da equipa; a ativação nunca é automática. -->
                    <button type="button" id="btn-register-order" data-jsaction="registerPaymentOrder" class="btn btn-secondary" style="border-color:var(--vip-gold); color:var(--vip-gold); font-weight:700; display:flex; justify-content:center; align-items:center; gap:8px;">
                        <i data-lucide="shield-check"></i>
                        Já transferi — Registar pedido de ativação
                    </button>
                </div>
            </div>

        </div>
    </div>

</div>

<script nonce="<?php echo Security::getNonce(); ?>">
    // Planos definidos no servidor (valores de referência; o servidor valida sempre)
    const PLANS = {
        pro1: { name: 'Mestre de Obra', months: 1, amount: 5000 },
        pro3: { name: 'Empreiteiro Pro', months: 3, amount: 12000 },
        pro6: { name: 'Construtor VIP', months: 6, amount: 20000 }
    };
    let currentSelectedPlan = null;

    function selectPlan(planKey) {
        const plan = PLANS[planKey];
        if (!plan) return;
        currentSelectedPlan = { key: planKey, ...plan };

        // Formatar quantia
        const formattedAmount = new Intl.NumberFormat('pt-AO', { minimumFractionDigits: 2 }).format(plan.amount) + ' Kz';

        // Atualizar interface
        document.getElementById('selected-plan-badge').innerText = plan.name;
        document.getElementById('atm-amount-val').innerText = formattedAmount;

        // Atualizar link do WhatsApp com o texto pré-preenchido do plano
        const whatsappBtn = document.getElementById('whatsapp-submit-btn');
        if (whatsappBtn) {
            const message = encodeURIComponent(`Olá, gostava de confirmar o pagamento da minha assinatura VIP no Constrói Já.\n\nPlano Selecionado: ${plan.name}\nValor: ${formattedAmount}\n\nSeguem os meus detalhes e o comprovativo de transferência em anexo.`);
            whatsappBtn.href = `https://wa.me/244923972131?text=${message}`;
        }

        const registerBtn = document.getElementById('btn-register-order');
        if (registerBtn) {
            registerBtn.disabled = false;
            registerBtn.innerHTML = '<i data-lucide="shield-check" style="width:18px; height:18px;"></i> Já transferi — Registar pedido de ativação';
            if (window.lucide) window.lucide.createIcons();
        }

        const paymentSection = document.getElementById('payment-section');
        paymentSection.style.display = 'block';
        paymentSection.scrollIntoView({ behavior: 'smooth' });

        App.showToast(`Dados de pagamento gerados para o plano ${plan.name}!`, 'info');
    }

    // (CJ-01) Registo de pedido de pagamento — a ativação é feita pela equipa
    // após confirmação do comprovativo (ou por webhook do gateway com assinatura).
    async function registerPaymentOrder() {
        const btn = document.getElementById('btn-register-order');
        if (!currentSelectedPlan || !currentSelectedPlan.key) {
            App.showToast('Selecione primeiro um plano.', 'warning');
            return;
        }
        App.setLoading(btn, true);
        try {
            const response = await App.post('/api/payments/create-order', { plan: currentSelectedPlan.key });
            App.showToast('Pedido registado! Assim que a equipa confirmar a transferência, o seu acesso é ativado.', 'success');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="check-circle" style="width:18px; height:18px;"></i> Pedido enviado — aguarde confirmação';
            if (window.lucide) window.lucide.createIcons();
        } catch (e) {
            App.showToast(e.message || 'Erro ao registar o pedido. Tente novamente.', 'danger');
            App.setLoading(btn, false);
        }
    }
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
