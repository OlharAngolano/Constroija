<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

// Exige autenticação básica
middleware_require_auth();

$user = current_user();
$db = db();

// Buscar estado mais atualizado do utilizador (DTO seguro — sem tokens)
$profile = $db->fetch("SELECT * FROM profiles WHERE id = ?", [$user['id']]);
$_SESSION['user'] = user_session_dto($profile); // Sincroniza a sessão (CJ-04)

$now = new DateTime();
$expiresAt = $profile['subscription_expires_at'] ? new DateTime($profile['subscription_expires_at']) : null;
$isVIP = $profile['status'] === 'active' && $expiresAt && $expiresAt > $now;

$title = 'Parceiros B2B & Cupões — Constrói Já';
require_once __DIR__ . '/../templates/header.php';

try {
    // (CJ-09) Sem DDL em runtime: a tabela partners vem das migrações.
    $partners = $db->fetchAll("SELECT * FROM partners ORDER BY id ASC");
} catch (PDOException $e) {
    error_log('Marketplace: tabela partners indisponível — execute bin/migrate.php');
    $partners = [];
    $partnersTableMissing = true;
}
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    .category-filter {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 30px;
    }

    .category-btn {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 8px 18px;
        border-radius: 50px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .category-btn.active, .category-btn:hover {
        background: linear-gradient(135deg, var(--accent-primary) 0%, #ea580c 100%);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
    }

    .partners-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
        gap: 24px;
    }

    .partner-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: var(--shadow-md);
    }

    .partner-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg), var(--shadow-glow);
        border-color: var(--accent-primary);
    }

    .partner-body {
        padding: 24px;
        display: flex;
        gap: 16px;
        transition: padding 0.3s;
    }

    .partner-logo {
        width: 72px;
        height: 72px;
        border-radius: 14px;
        object-fit: cover;
        flex-shrink: 0;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
    }

    .partner-info {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .partner-tag {
        align-self: flex-start;
        font-size: 10px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 2px 10px;
        border-radius: 50px;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .partner-footer {
        border-top: 1px solid var(--border-color);
        padding: 16px 24px;
        background: var(--bg-secondary);
        display: flex;
        flex-direction: column;
        gap: 10px;
        transition: padding 0.3s;
    }

    .coupon-container {
        position: relative;
        border-radius: var(--radius-md);
        overflow: hidden;
        border: 1.5px dashed var(--border-color);
        background: var(--bg-primary);
        padding: 12px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 52px;
        transition: all 0.3s;
    }

    .coupon-blur-mask {
        position: absolute;
        inset: 0;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        background: rgba(255, 255, 255, 0.85);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: var(--text-primary);
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        z-index: 10;
        transition: all 0.3s;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .coupon-blur-mask:hover {
        background: rgba(255, 255, 255, 0.95);
        color: var(--accent-primary);
    }

    .vip-gold-glow {
        border-color: rgba(251, 191, 36, 0.35);
        background: linear-gradient(135deg, rgba(251, 191, 36, 0.06) 0%, rgba(217, 119, 6, 0.06) 100%);
        box-shadow: 0 0 15px rgba(251, 191, 36, 0.08);
    }

    .vip-badge-text {
        font-size: 10px;
        color: #fbbf24;
        font-weight: 800;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 4px;
        letter-spacing: 0.5px;
    }

    @media (max-width: 576px) {
        .partner-body {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 14px !important;
            padding: 18px !important;
        }
        .partner-logo {
            width: 56px !important;
            height: 56px !important;
            border-radius: 10px;
        }
        .partner-footer {
            padding: 14px 18px !important;
        }
    }
</style>

<div style="max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 30px;" class="slideUp">
    
    <!-- CABEÇALHO DA VITRINE -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 20px;">
        <div>
            <h2 style="font-family:'Outfit', sans-serif; font-weight:800; font-size:28px; display:flex; align-items:center; gap:10px;">
                <i data-lucide="shopping-bag" style="color:var(--accent-success);"></i>
                Parceiros B2B & Cupões de Desconto
            </h2>
            <p style="color:var(--text-secondary); font-size:14px;">Economize milhares de Kwanzas nos seus materiais com descontos exclusivos negociados para o canteiro de obras.</p>
        </div>
        
        <?php if (!$isVIP): ?>
            <a href="/subscription" class="btn btn-primary" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-color: #f59e0b; color: #0b0f19; font-weight:800; display:flex; align-items:center; gap:8px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">
                <i data-lucide="award"></i>
                Aderir ao Plano VIP
            </a>
        <?php else: ?>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); color: #fbbf24; padding: 8px 16px; border-radius: 50px; font-weight: 600; font-size: 14px;">
                <i data-lucide="award" style="width: 18px; height: 18px;"></i>
                Acesso Premium VIP Desbloqueado!
            </div>
        <?php endif; ?>
    </div>

    <!-- WIDGET DE RESUMO/CTA VIP -->
    <?php if (!$isVIP): ?>
        <div class="card" style="padding: 24px; background: linear-gradient(135deg, rgba(255, 107, 0, 0.06) 0%, var(--bg-card) 100%); border-color: rgba(255, 107, 0, 0.2); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div style="display:flex; gap:16px; align-items:center;">
                <div style="width:50px; height:50px; border-radius:50%; background:rgba(255, 107, 0, 0.1); color:var(--accent-primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i data-lucide="shield-alert" style="width:24px; height:24px;"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size:16px; font-family:'Outfit', sans-serif; color:var(--text-primary);">Poupe até 20% com Cupões de Desconto VIP</h4>
                    <p style="margin:4px 0 0 0; color:var(--text-secondary); font-size:13px; max-width: 600px;">Assinantes premium do Constrói Já têm acesso instantâneo a códigos oficiais de grandes marcas nacionais e poupam em cimento, acabamentos e eletricidade.</p>
                </div>
            </div>
            <a href="/subscription" class="btn btn-secondary" style="border-color:var(--accent-primary); color:var(--accent-primary); font-weight: 600; font-size: 13px;">Explorar Planos VIP</a>
        </div>
    <?php endif; ?>

    <!-- FILTROS DE CATEGORIA -->
    <div class="category-filter">
        <button class="category-btn active" onclick="filterCategory('all', this)">Todos os Parceiros</button>
        <button class="category-btn" onclick="filterCategory('construcao', this)">Construção Geral</button>
        <button class="category-btn" onclick="filterCategory('acabamentos', this)">Acabamentos</button>
        <button class="category-btn" onclick="filterCategory('pintura', this)">Pintura</button>
        <button class="category-btn" onclick="filterCategory('outros', this)">Ferragens & Outros</button>
    </div>

    <!-- GRID DE LOJAS PARCEIRAS -->
    <div class="partners-grid">
        <?php foreach ($partners as $partner): ?>
            <div class="partner-card" data-category="<?php echo $partner['category']; ?>">
                <div class="partner-body">
                    <img src="<?php echo $partner['logo']; ?>" alt="<?php echo $partner['name']; ?>" class="partner-logo">
                    
                    <div class="partner-info">
                        <span class="partner-tag"><?php 
                            if ($partner['category'] === 'construcao') echo 'Construção';
                            elseif ($partner['category'] === 'acabamentos') echo 'Acabamento';
                            elseif ($partner['category'] === 'pintura') echo 'Pintura';
                            else echo 'Ferragens & Equipamentos';
                        ?></span>
                        <h4 style="margin: 4px 0 0 0; font-family:'Outfit', sans-serif; font-weight: 800; font-size: 18px; color: var(--text-primary);"><?php echo $partner['name']; ?></h4>
                        <p style="margin: 6px 0 0 0; font-size: 13px; color: var(--text-secondary); line-height: 1.5;"><?php echo $partner['desc']; ?></p>
                    </div>
                </div>

                <div class="partner-footer">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size: 14px; font-weight:700; color:var(--accent-success);"><?php echo $partner['discount']; ?></span>
                        
                        <?php if ($isVIP): ?>
                            <span class="vip-badge-text">
                                <i data-lucide="gem" style="width:12px; height:12px;"></i>
                                VIP Exclusivo
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- CAIXA DE CUPÃO COM BLUR SE NÃO FOR VIP -->
                    <div class="coupon-container <?php echo $isVIP ? 'vip-gold-glow' : ''; ?>">
                        <?php if (!$isVIP): ?>
                            <!-- MÁSCARA COM BLUR DE CADEADO -->
                            <div class="coupon-blur-mask" onclick="window.location.href='/subscription'">
                                <i data-lucide="lock" style="width: 14px; height: 14px;"></i>
                                Desbloquear Cupão VIP
                            </div>
                            
                            <!-- CUPÃO FALSO BLURRED POR TRÁS (CSS BACKDROP FILTER APLICA BLUR) -->
                            <div style="font-family: monospace; font-size: 15px; font-weight: 800; color: var(--text-muted);">
                                XXXXXXXX
                            </div>
                        <?php else: ?>
                            <!-- CUPÃO VISÍVEL DE ALTA QUALIDADE -->
                            <div style="font-family: monospace; font-size: 15px; font-weight: 800; color: #fbbf24; letter-spacing: 0.5px;">
                                <?php echo $partner['coupon']; ?>
                            </div>
                            <button class="btn" onclick="copyToClipboard('<?php echo $partner['coupon']; ?>', this)" style="background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.2); color: #fbbf24; padding: 4px 10px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                <i data-lucide="copy" style="width:12px; height:12px;"></i>
                                Copiar
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- BOTAO WHATSAPP PERSONALIZADO -->
                    <?php if (!empty($partner['whatsapp'])): ?>
                        <?php
                        $cleanPhone = preg_replace('/[^0-9]/', '', $partner['whatsapp']);
                        $waMsg = "Olá, equipa da " . $partner['name'] . "! Vi o vosso perfil no Marketplace B2B do Constrói Já e gostaria de solicitar um orçamento para serviços/materiais de construção.";
                        $waUrl = "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode($waMsg);
                        ?>
                        <a href="<?php echo $waUrl; ?>" target="_blank" class="btn btn-secondary" style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: #22c55e; font-size: 12px; padding: 10px; width: 100%; justify-content: center; display: inline-flex; align-items: center; gap: 6px; margin-top: 8px;">
                            <i data-lucide="message-circle" style="width:16px; height:16px;"></i>
                            Contactar no WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
    // Filtro instantâneo JS
    function filterCategory(category, btnElement) {
        // Reset botões
        const buttons = document.querySelectorAll('.category-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
        
        // Ativar atual
        btnElement.classList.add('active');

        // Filtrar cards
        const cards = document.querySelectorAll('.partner-card');
        cards.forEach(card => {
            if (category === 'all' || card.getAttribute('data-category') === category) {
                card.style.display = 'flex';
                card.classList.add('slideUp');
            } else {
                card.style.display = 'none';
                card.classList.remove('slideUp');
            }
        });
    }

    // Copiar cupom para área de transferência
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="check" style="width:12px; height:12px;"></i> Copiado!';
            btn.style.background = 'rgba(16, 185, 129, 0.1)';
            btn.style.borderColor = 'rgba(16, 185, 129, 0.3)';
            btn.style.color = 'var(--accent-success)';
            
            // Re-render do ícone da Lucide para o 'check'
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            App.showToast('Cupão copiado com sucesso!', 'success');

            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = 'rgba(251, 191, 36, 0.05)';
                btn.style.borderColor = 'rgba(251, 191, 36, 0.2)';
                btn.style.color = '#fbbf24';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy text: ', err);
            App.showToast('Erro ao copiar cupão.', 'danger');
        });
    }
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
