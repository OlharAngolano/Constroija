<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

// Redirecionar se já estiver autenticado
if (is_logged_in()) {
    redirect('/feed');
}

$title = 'Constrói Já — Plataforma Inteligente de Gestão de Obras em Angola';
require_once __DIR__ . '/../templates/header.php';
?>

<!-- Estilos Completos Premium para a Landing Page (Tema Escuro Espacial) -->
<style nonce="<?php echo Security::getNonce(); ?>">
    /* Reset local e fontes */
    .landing-wrapper {
        font-family: 'Inter', sans-serif;
        background: #070913;
        color: #f1f5f9;
        overflow-x: hidden;
        margin-top: -20px; /* Alinhar com a margem do topo */
        position: relative;
    }
    
    .landing-wrapper h1, 
    .landing-wrapper h2, 
    .landing-wrapper h3, 
    .landing-wrapper h4, 
    .landing-wrapper h5, 
    .landing-wrapper h6 {
        font-family: 'Outfit', sans-serif;
        color: #ffffff;
    }

    /* Navegação de Visitante */
    .visitor-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 5%;
        background: rgba(7, 9, 19, 0.75);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    
    .visitor-nav-links {
        display: flex;
        gap: 30px;
        align-items: center;
    }
    
    .visitor-nav-links a {
        color: #94a3b8;
        text-decoration: none;
        font-weight: 500;
        font-size: 14.5px;
        transition: color 0.2s ease;
    }
    
    .visitor-nav-links a:hover {
        color: #ffffff;
    }
    
    .visitor-nav-actions {
        display: flex;
        gap: 16px;
        align-items: center;
    }

    /* Aura luminosa de fundo (Design WOW) */
    .background-aura-orange {
        position: absolute;
        top: -200px;
        right: -10%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(249, 115, 22, 0.08) 0%, transparent 70%);
        pointer-events: none;
        z-index: 0;
    }

    .background-aura-blue {
        position: absolute;
        top: 400px;
        left: -10%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.05) 0%, transparent 70%);
        pointer-events: none;
        z-index: 0;
    }

    /* Hero Section */
    .hero-sec {
        padding: 90px 5% 70px 5%;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
        z-index: 10;
    }
    
    .hero-badge-vip {
        background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(249, 115, 22, 0.1) 100%);
        color: #fbbf24;
        border: 1px solid rgba(251, 191, 36, 0.25);
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 13.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 28px;
        box-shadow: 0 4px 15px rgba(251, 191, 36, 0.05);
    }
    
    .hero-title-premium {
        font-size: 54px;
        font-weight: 800;
        line-height: 1.15;
        max-width: 900px;
        margin-bottom: 24px;
        letter-spacing: -1px;
    }
    
    .hero-title-premium span {
        background: linear-gradient(135deg, #fbbf24 0%, #f97316 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .hero-desc {
        font-size: 19px;
        color: #94a3b8;
        max-width: 720px;
        line-height: 1.6;
        margin-bottom: 40px;
    }

    /* Showcase da Calculadora / WhatsApp Push / Marketplace (Fator WOW) */
    .teaser-showcase {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 40px;
        max-width: 1100px;
        margin: 50px auto 90px auto;
        padding: 0 20px;
        align-items: center;
        z-index: 10;
        position: relative;
    }
    
    .teaser-left {
        background: rgba(26, 32, 50, 0.45);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: var(--radius-lg);
        padding: 40px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    }
    
    .whatsapp-mockup {
        background: #0b141a;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        position: relative;
        animation: shake 4s infinite ease-in-out;
    }
    
    @keyframes shake {
        0%, 90%, 100% { transform: rotate(0deg); }
        92% { transform: rotate(1deg) translateY(-2px); }
        94% { transform: rotate(-1deg) translateY(1px); }
        96% { transform: rotate(1deg) translateY(-1px); }
        98% { transform: rotate(-1deg) translateY(0); }
    }
    
    .whatsapp-header {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #25D366;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    
    .whatsapp-bubble {
        background: #202c33;
        border-radius: 8px;
        padding: 12px;
        font-size: 13.5px;
        line-height: 1.45;
        color: #e9edef;
        border-left: 4px solid #f97316;
    }

    /* Card de Cupão B2B com Efeito Blur (O Segredo Comercial) */
    .b2b-coupon-teaser {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.02) 0%, rgba(255, 255, 255, 0.01) 100%);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: var(--radius-md);
        padding: 24px;
        position: relative;
        overflow: hidden;
        margin-top: 24px;
    }
    
    .blur-overlay-vip {
        position: absolute;
        inset: 0;
        background: rgba(7, 9, 19, 0.4);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 5;
    }
    
    .lock-pill {
        background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
        color: #0b0f19;
        font-weight: 800;
        font-size: 11.5px;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 50px;
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
    }
    
    /* Comparação Standard vs VIP (Grid Estilo Ledger) */
    .comparison-section {
        max-width: 1000px;
        margin: 40px auto 90px auto;
        padding: 0 20px;
        z-index: 10;
        position: relative;
    }
    
    .comparison-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(20, 26, 42, 0.3);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .comparison-table th, 
    .comparison-table td {
        padding: 18px 24px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .comparison-table th {
        background: rgba(255, 255, 255, 0.02);
        font-weight: 700;
        color: #ffffff;
    }
    
    .comparison-table tr:hover td {
        background: rgba(255, 255, 255, 0.01);
    }
    
    .badge-vip-check {
        color: #fbbf24;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Pricing Section */
    .plans-section {
        background: radial-gradient(circle at center, rgba(251, 191, 36, 0.02) 0%, transparent 60%);
        padding: 80px 5%;
        z-index: 10;
        position: relative;
    }
    
    .plans-title {
        text-align: center;
        margin-bottom: 50px;
    }
    
    .plans-grid-landing {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
        gap: 30px;
        max-width: 1050px;
        margin: 0 auto;
    }
    
    .plan-card-landing {
        background: rgba(26, 32, 50, 0.4);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: var(--radius-lg);
        padding: 40px 32px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }
    
    .plan-card-landing:hover {
        transform: translateY(-8px);
        border-color: rgba(255, 255, 255, 0.15);
        box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    }
    
    .plan-card-landing.popular-plan {
        border-color: var(--accent-primary);
        box-shadow: 0 10px 35px -10px rgba(249, 115, 22, 0.2);
        background: linear-gradient(to bottom, rgba(249, 115, 22, 0.02), rgba(26, 32, 50, 0.4));
    }
    
    .plan-card-landing.popular-plan::before {
        content: 'RECOMENDADO';
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--accent-primary);
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        padding: 4px 16px;
        border-radius: 50px;
        box-shadow: 0 4px 10px rgba(249, 115, 22, 0.2);
    }
    
    .plan-card-landing.vip-plan {
        border-color: #fbbf24;
        box-shadow: 0 10px 35px -10px rgba(251, 191, 36, 0.15);
        background: linear-gradient(to bottom, rgba(251, 191, 36, 0.02), rgba(26, 32, 50, 0.4));
    }
    
    .plan-card-landing.vip-plan::before {
        content: 'MELHOR VALOR';
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
        color: #0b0f19;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1px;
        padding: 4px 16px;
        border-radius: 50px;
        box-shadow: 0 4px 10px rgba(251, 191, 36, 0.3);
    }

    /* Testimonials */
    .testimonial-sec {
        padding: 80px 5%;
        background: rgba(20, 26, 42, 0.15);
    }
    
    .testimonial-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        max-width: 1000px;
        margin: 0 auto;
    }
    
    .testimonial-card {
        background: rgba(26, 32, 50, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-md);
        padding: 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    /* Accordion FAQ */
    .faq-sec {
        max-width: 800px;
        margin: 80px auto;
        padding: 0 20px;
    }
    
    .faq-item {
        background: rgba(26, 32, 50, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-sm);
        margin-bottom: 12px;
        overflow: hidden;
    }
    
    .faq-question {
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s ease;
    }
    
    .faq-question:hover {
        background: rgba(255, 255, 255, 0.02);
    }
    
    .faq-answer {
        padding: 0 20px 20px 20px;
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.6;
        display: none;
    }

    /* Responsividade */
    @media (max-width: 992px) {
        .teaser-showcase {
            grid-template-columns: 1fr;
            gap: 50px;
        }
        .hero-title-premium {
            font-size: 40px;
        }
    }
    
    @media (max-width: 768px) {
        .visitor-nav-links {
            display: none;
        }
        .hero-title-premium {
            font-size: 32px;
        }
        .hero-desc {
            font-size: 16px;
        }
    }
</style>

<div class="landing-wrapper">
    <!-- Auroras estéticas de fundo -->
    <div class="background-aura-orange"></div>
    <div class="background-aura-blue"></div>

    <!-- Navegação de Topo de Visitante -->
    <header class="visitor-nav">
        <a href="/" style="display:flex; align-items:center; gap:10px; text-decoration:none; font-weight:800; font-size:20px; color:#ffffff;">
            <div style="background:rgba(249, 115, 22, 0.1); width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--accent-primary);">
                <i data-lucide="hard-hat" style="width:20px; height:20px;"></i>
            </div>
            <span style="font-family:'Outfit', sans-serif;">Constrói Já</span>
        </a>
        
        <nav class="visitor-nav-links">
            <a href="#funcionalidades">Funcionalidades</a>
            <a href="#comparacao">Porquê VIP?</a>
            <a href="#precos">Preços</a>
            <a href="#faq">Perguntas Frequentes</a>
        </nav>
        
        <div class="visitor-nav-actions">
            <a href="/login" class="btn btn-secondary" style="padding: 10px 20px; font-size:14px;">Entrar</a>
            <a href="/register" class="btn btn-primary" style="padding: 10px 20px; font-size:14px; box-shadow: 0 4px 15px rgba(249, 115, 22, 0.25);">Começar Grátis</a>
        </div>
    </header>

    <!-- HERO SECTION: Fator de Conversão de Alto Impacto -->
    <section class="hero-sec">
        <div class="hero-badge-vip">
            <i data-lucide="award" style="width:16px; height:16px; animation: spin 4s linear infinite;"></i>
            <span>3 DIAS DE TESTE TOTALMENTE GRÁTIS</span>
        </div>
        
        <h1 class="hero-title-premium">
            Gerencie a sua Obra com Controle Total e <span>Poupança Real de Dinheiro</span>
        </h1>
        
        <p class="hero-desc">
            Evite estouros de orçamento, compre materiais a preço de fornecedor com cupões B2B e partilhe relatórios de progresso em Angola. Experimente sem compromisso.
        </p>
        
        <div class="cta-group" style="margin-bottom: 40px;">
            <a href="/register" class="btn btn-primary" style="padding: 16px 36px; font-size: 16px; font-weight:700; border-radius:50px; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); box-shadow: 0 10px 25px -5px rgba(249, 115, 22, 0.45);">
                Iniciar Teste Gratuito de 3 Dias
                <i data-lucide="arrow-right" style="margin-left: 8px;"></i>
            </a>
            <a href="#precos" class="btn btn-secondary" style="padding: 16px 32px; font-size: 16px; border-radius:50px;">
                Ver Planos VIP
            </a>
        </div>

        <!-- Métrica de Prova Social -->
        <div style="display:flex; justify-content:center; gap:40px; flex-wrap:wrap; border-top: 1px solid rgba(255,255,255,0.05); padding-top:40px; width:100%; max-width:800px; margin-top:20px;">
            <div style="text-align:center;">
                <span style="display:block; font-size:28px; font-weight:800; color:#ffffff; font-family:'Outfit';">1.240+</span>
                <span style="color:#94a3b8; font-size:13px;">Obras Ativas em Angola</span>
            </div>
            <div style="text-align:center; border-left: 1px solid rgba(255,255,255,0.05); border-right: 1px solid rgba(255,255,255,0.05); padding: 0 40px;">
                <span style="display:block; font-size:28px; font-weight:800; color:#fbbf24; font-family:'Outfit';">35M+ Kz</span>
                <span style="color:#94a3b8; font-size:13px;">Economizados em Desvios</span>
            </div>
            <div style="text-align:center;">
                <span style="display:block; font-size:28px; font-weight:800; color:#25d366; font-family:'Outfit';">45+</span>
                <span style="color:#94a3b8; font-size:13px;">Fornecedores com Cupão B2B</span>
            </div>
        </div>
    </section>

    <!-- TEASER SHOWCASE: Destaque de Recursos e Cupões Blur VIP -->
    <section class="teaser-showcase" id="funcionalidades">
        <div class="teaser-left slideUp">
            <h3 style="font-size:24px; font-weight:800; margin-bottom:16px;">Controlo de Custos e Alertas WhatsApp</h3>
            <p style="color:#94a3b8; font-size:15px; line-height:1.6; margin-bottom:24px;">
                Esqueça surpresas no fim da semana. Configure os seus tetos financeiros por etapa (Alvenaria, Pintura, Cobertura) e receba avisos imediatos se houver risco de estouro.
            </p>
            
            <div class="whatsapp-mockup">
                <div class="whatsapp-header">
                    <i data-lucide="phone" style="width: 14px; height: 14px;"></i>
                    <span>ALERTA FINANCEIRO DE OBRA</span>
                </div>
                <div class="whatsapp-bubble">
                    <strong>⚠️ Estouro de Orçamento Detectado!</strong><br>
                    Na fase <strong>"Estrutura"</strong>, o consumo real excedeu o orçamentado em <strong>14%</strong>.<br>
                    Valor excedente: <strong>15.000,00 Kz</strong>.
                </div>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Cupão Secil com Lock VIP -->
            <div class="b2b-coupon-teaser">
                <div class="blur-overlay-vip">
                    <div class="lock-pill">
                        <i data-lucide="lock" style="width: 12px; height: 12px;"></i>
                        Exclusivo VIP
                    </div>
                    <small style="color: #cbd5e1; font-weight:600; margin-top:8px; font-size:11px;">Aderir VIP para desbloquear</small>
                </div>
                
                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                    <strong style="color:#ffffff;">Secil Cimentos</strong>
                    <span style="color:#25d366; font-weight:700; font-size:13px;">-12% de Desconto</span>
                </div>
                <div style="background:rgba(255,255,255,0.05); padding:8px; border-radius:4px; font-family:monospace; font-size:14px; text-align:center; color:#fbbf24;">
                    SECIL_CIMENTO_SECRET_VIP
                </div>
            </div>

            <!-- Cupão Sika com Lock VIP -->
            <div class="b2b-coupon-teaser">
                <div class="blur-overlay-vip">
                    <div class="lock-pill">
                        <i data-lucide="lock" style="width: 12px; height: 12px;"></i>
                        Exclusivo VIP
                    </div>
                    <small style="color: #cbd5e1; font-weight:600; margin-top:8px; font-size:11px;">Aderir VIP para desbloquear</small>
                </div>
                
                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                    <strong style="color:#ffffff;">Sika Angola</strong>
                    <span style="color:#25d366; font-weight:700; font-size:13px;">-15% Aditivos & Argamassas</span>
                </div>
                <div style="background:rgba(255,255,255,0.05); padding:8px; border-radius:4px; font-family:monospace; font-size:14px; text-align:center; color:#fbbf24;">
                    SIKA_VIP_BUILDER_ANGOLA
                </div>
            </div>
        </div>
    </section>

    <!-- TABELA COMPARATIVA VIP VS STANDARD -->
    <section class="comparison-section" id="comparacao">
        <div style="text-align:center; margin-bottom:40px;">
            <h2 style="font-size:32px; font-weight:800;">Porquê escolher o plano VIP?</h2>
            <p style="color:#94a3b8; font-size:15px; margin-top:10px;">Compare as funcionalidades e descubra o valor da nossa assinatura premium.</p>
        </div>
        
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Funcionalidade</th>
                    <th>Membro Standard (Grátis)</th>
                    <th style="color: #fbbf24;">Membro VIP Premium</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gestão de Despesas & Orçamentos</td>
                    <td>Básica (1 Projeto)</td>
                    <td class="badge-vip-check"><i data-lucide="zap" style="width:14px; height:14px;"></i> Ilimitada (Múltiplas Obras)</td>
                </tr>
                <tr>
                    <td>Gráficos Dinâmicos de Desvios</td>
                    <td>Não (Tabelas Apenas)</td>
                    <td class="badge-vip-check"><i data-lucide="zap" style="width:14px; height:14px;"></i> Sim (Visual Planeado vs Real)</td>
                </tr>
                <tr>
                    <td>Cupões de Desconto no Marketplace B2B</td>
                    <td>Bloqueados (Com Blur)</td>
                    <td class="badge-vip-check"><i data-lucide="zap" style="width:14px; height:14px;"></i> Desbloqueados (Todos os parceiros)</td>
                </tr>
                <tr>
                    <td>Alertas de Estouro via WhatsApp</td>
                    <td>Não disponível</td>
                    <td class="badge-vip-check"><i data-lucide="zap" style="width:14px; height:14px;"></i> Sim (Imediato no telemóvel)</td>
                </tr>
                <tr>
                    <td>Relatórios de Auditoria Exportáveis</td>
                    <td>HTML Simples</td>
                    <td class="badge-vip-check"><i data-lucide="zap" style="width:14px; height:14px;"></i> PDF Corporativo A4 + QR Code</td>
                </tr>
                <tr>
                    <td>Sinalização nos Portfólios Públicos</td>
                    <td>Crachá Básico</td>
                    <td class="badge-vip-check"><i data-lucide="zap" style="width:14px; height:14px;"></i> Selo "VIP Verificado" (Mais Confiança)</td>
                </tr>
            </tbody>
        </table>
    </section>

    <!-- PRICING GRIDS: O Fator de Decisão -->
    <section class="plans-section" id="precos">
        <div class="plans-title">
            <h2 style="font-size:36px; font-weight:800; margin-bottom:12px;">Planos de Subscrição VIP Simples</h2>
            <p style="color:#94a3b8; font-size:16px;">Sem taxas ocultas. Escolha a duração ideal para a sua estrutura de obras.</p>
        </div>
        
        <div class="plans-grid-landing">
            <!-- PLANO 1 -->
            <div class="plan-card-landing">
                <div>
                    <h3 style="font-size:22px; font-weight:800; margin-bottom:8px;">Mestre de Obra</h3>
                    <p style="color:#94a3b8; font-size:13.5px; line-height:1.4; margin-bottom:20px;">Ideal para profissionais e obras pontuais rápidas.</p>
                    <div style="display:flex; align-items:baseline; gap:4px; margin-bottom:24px;">
                        <span style="font-size:36px; font-weight:800;">5.000 Kz</span>
                        <span style="color:#64748b; font-size:14px;">/ 1 mês</span>
                    </div>
                    
                    <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:12px; margin-bottom:30px; font-size:14px; color:#cbd5e1;">
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> 1 Projeto Ativo</li>
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Acesso ao Marketplace B2B</li>
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Relatórios PDF Básicos</li>
                        <li style="color:#64748b;"><i data-lucide="x" style="color:#ef4444; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Sem alertas imediatos no WhatsApp</li>
                    </ul>
                </div>
                <a href="/register" class="btn btn-secondary" style="width:100%; text-align:center;">Começar Teste</a>
            </div>

            <!-- PLANO 2 -->
            <div class="plan-card-landing popular-plan">
                <div>
                    <h3 style="font-size:22px; font-weight:800; margin-bottom:8px; color:var(--accent-primary);">Empreiteiro Pro</h3>
                    <p style="color:#94a3b8; font-size:13.5px; line-height:1.4; margin-bottom:20px;">O mais popular. Excelente equilíbrio para empreiteiros ativos.</p>
                    <div style="display:flex; align-items:baseline; gap:4px; margin-bottom:6px;">
                        <span style="font-size:36px; font-weight:800; color:#ffffff;">12.000 Kz</span>
                        <span style="color:#64748b; font-size:14px;">/ 3 meses</span>
                    </div>
                    <small style="color:#25d366; font-weight:700; display:block; margin-bottom:24px;">Poupe 3.000 Kz (Preço Médio: 4.000 Kz/mês)</small>
                    
                    <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:12px; margin-bottom:30px; font-size:14px; color:#cbd5e1;">
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Projetos Ilimitados</li>
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Todos os cupões B2B liberados</li>
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Alertas imediatos WhatsApp</li>
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Relatórios PDF Estendidos com QR Code</li>
                    </ul>
                </div>
                <a href="/register" class="btn btn-primary" style="width:100%; text-align:center;">Começar Teste</a>
            </div>

            <!-- PLANO 3 -->
            <div class="plan-card-landing vip-plan">
                <div>
                    <h3 style="font-size:22px; font-weight:800; margin-bottom:8px; color:#fbbf24;">Construtor VIP</h3>
                    <p style="color:#94a3b8; font-size:13.5px; line-height:1.4; margin-bottom:20px;">Máxima autoridade empresarial e parcerias em Angola.</p>
                    <div style="display:flex; align-items:baseline; gap:4px; margin-bottom:6px;">
                        <span style="font-size:36px; font-weight:800; color:#ffffff;">20.000 Kz</span>
                        <span style="color:#64748b; font-size:14px;">/ 6 meses</span>
                    </div>
                    <small style="color:#fbbf24; font-weight:700; display:block; margin-bottom:24px;">Poupe 10.000 Kz (Preço Médio: 3.333 Kz/mês)</small>
                    
                    <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:12px; margin-bottom:30px; font-size:14px; color:#cbd5e1;">
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> <strong>Tudo do Plano Pro</strong></li>
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Selo VIP Dourado no Perfil e Portfólio</li>
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Destaque no topo de pesquisas do B2B</li>
                        <li><i data-lucide="check" style="color:#25d366; width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:8px;"></i> Canal VIP de suporte prioritário</li>
                    </ul>
                </div>
                <a href="/register" class="btn btn-secondary" style="width:100%; text-align:center; border-color:#fbbf24; color:#fbbf24;">Começar Teste</a>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="testimonial-sec">
        <div style="text-align:center; margin-bottom:48px;">
            <h2 style="font-size:32px; font-weight:800;">Quem usa, confia</h2>
            <p style="color:#94a3b8; font-size:15px; margin-top:8px;">Profissionais e proprietários angolanos que gerenciam obras de sucesso.</p>
        </div>
        
        <div class="testimonial-grid">
            <div class="testimonial-card">
                <p style="font-style:italic; color:#cbd5e1; font-size:14px; line-height:1.5; margin-bottom:16px;">
                    "Com o Alerta WhatsApp do Constrói Já VIP, soubemos que a fase de fundação ultrapassaria a verba planeada logo no primeiro dia. Conseguimos agir rápido e economizar 300.000 Kz!"
                </p>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="background:#fbbf24; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#070913; font-weight:800; font-size:13px;">EK</div>
                    <div>
                        <strong style="font-size:13.5px; display:block;">Eng. Kiala</strong>
                        <span style="color:#64748b; font-size:11.5px;">Luanda, Angola</span>
                    </div>
                </div>
            </div>

            <div class="testimonial-card">
                <p style="font-style:italic; color:#cbd5e1; font-size:14px; line-height:1.5; margin-bottom:16px;">
                    "Os cupões de desconto VIP na Sika e Secil Cimentos pagaram a subscrição semestral na nossa primeira compra de materiais. Uma ferramenta indispensável para qualquer construtora em Angola."
                </p>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="background:#3b82f6; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#ffffff; font-weight:800; font-size:13px;">MM</div>
                    <div>
                        <strong style="font-size:13.5px; display:block;">Maria Manuel</strong>
                        <span style="color:#64748b; font-size:11.5px;">Talatona, Angola</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ SECTION Accordion -->
    <section class="faq-sec" id="faq">
        <div style="text-align:center; margin-bottom:40px;">
            <h2 style="font-size:32px; font-weight:800;">Perguntas Frequentes</h2>
            <p style="color:#94a3b8; font-size:15px; margin-top:8px;">Tire as suas dúvidas técnicas sobre o plano VIP do Constrói Já.</p>
        </div>
        
        <div class="faq-item">
            <div class="faq-question" data-jsaction="toggleFaq" data-jselement="1">
                <span>Como funciona o período de testes de 3 dias?</span>
                <i data-lucide="chevron-down" style="width:16px; height:16px;"></i>
            </div>
            <div class="faq-answer">
                Ao criar a sua conta, todas as funcionalidades VIP são ativadas gratuitamente durante 3 dias. Não precisa de dados de cartão. Após esse período, o acesso é temporariamente restrito e pode selecionar um dos nossos planos para continuar.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" data-jsaction="toggleFaq" data-jselement="1">
                <span>Quais são os métodos de pagamento suportados em Angola?</span>
                <i data-lucide="chevron-down" style="width:16px; height:16px;"></i>
            </div>
            <div class="faq-answer">
                Suportamos transferências bancárias diretas, depósitos e pagamentos via Multicaixa Express. Os dados de IBAN e contacto de WhatsApp oficial do suporte estão disponíveis diretamente na sua área de Faturação.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" data-jsaction="toggleFaq" data-jselement="1">
                <span>Posso cancelar a assinatura quando quiser?</span>
                <i data-lucide="chevron-down" style="width:16px; height:16px;"></i>
            </div>
            <div class="faq-answer">
                Sim, a sua subscrição não é de renovação automática obrigatória. Paga apenas o período contratado (1, 3 ou 6 meses). Caso não queira renovar no final, a sua conta simplesmente reverte para o estado Standard gratuito.
            </div>
        </div>
    </section>

    <!-- Footer Geral -->
    <footer style="border-top: 1px solid rgba(255,255,255,0.05); padding: 50px 5%; text-align:center; color:#64748b; font-size:14px;">
        <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-bottom:16px;">
            <i data-lucide="hard-hat" style="color:#fbbf24; width:22px; height:22px;"></i>
            <span style="font-weight:800; color:#ffffff; font-size:16px; font-family:'Outfit';">Constrói Já</span>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Constrói Já. Todos os direitos reservados. Orgulhosamente desenvolvido para Angola 🇦🇴.</p>
    </footer>
</div>

<!-- Script Interativo Accordion FAQ -->
<script nonce="<?php echo Security::getNonce(); ?>">
    function toggleFaq(el) {
        const item = el.parentElement;
        const answer = item.querySelector('.faq-answer');
        const icon = item.querySelector('[data-lucide]');
        const isVisible = answer.style.display === 'block';
        
        // Fechar todos
        document.querySelectorAll('.faq-answer').forEach(ans => ans.style.display = 'none');
        document.querySelectorAll('.faq-item').forEach(it => {
            const ic = it.querySelector('[data-lucide]');
            if (ic) ic.setAttribute('data-lucide', 'chevron-down');
        });
        
        if (!isVisible) {
            answer.style.display = 'block';
            if (icon) icon.setAttribute('data-lucide', 'chevron-up');
        } else {
            answer.style.display = 'none';
            if (icon) icon.setAttribute('data-lucide', 'chevron-down');
        }
        
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
