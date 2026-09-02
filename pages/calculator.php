<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Security.php';

$isLoggedIn = is_logged_in();
$title = 'Calculadora Inteligente de Materiais — Constrói Já';
require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 960px; margin: <?php echo $isLoggedIn ? '10px auto 40px auto' : '40px auto'; ?>; padding: 0 20px; display: flex; flex-direction: column; gap: 24px;">

    <!-- Título Principal e Introdução (SEO optimized) -->
    <div style="text-align: center; margin-bottom: 10px;">
        <span class="badge badge-primary" style="padding: 6px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; display: inline-block;">Ferramenta Gratuita</span>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 32px; font-weight: 800; line-height: 1.2; margin-bottom: 10px;">
            Calculadora Inteligente de Materiais
        </h1>
        <p style="color: var(--text-secondary); max-width: 600px; margin: 0 auto; font-size: 15px; line-height: 1.6;">
            Estime instantaneamente tijolos, sacos de cimento, areia, brita e água para a sua obra com base nos rácios oficiais da construção civil em Angola.
        </p>
    </div>

    <!-- Layout de Colunas da Calculadora -->
    <div style="display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start;" class="calculator-grid">
        
        <!-- COLUNA ESQUERDA: PARÂMETROS E ESCOLHA DE TIPO DE OBRA -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            
            <!-- Seleção do Tipo de Obra (Tabs Horizontais) -->
            <div class="card" style="padding: 16px;">
                <label style="display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; color: var(--text-secondary);">
                    Selecione o Tipo de Serviço:
                </label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;" id="calc-tabs">
                    <button type="button" class="tab-btn active" data-type="muro" style="flex: 1; min-width: 140px; padding: 12px 10px; font-size: 13px; font-weight: 600; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: rgba(255,255,255,0.02); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: var(--transition-normal);">
                        <i data-lucide="layout-grid" style="width: 16px; height: 16px;"></i>
                        Construção de Muro
                    </button>
                    <button type="button" class="tab-btn" data-type="reboco" style="flex: 1; min-width: 140px; padding: 12px 10px; font-size: 13px; font-weight: 600; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: rgba(255,255,255,0.02); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: var(--transition-normal);">
                        <i data-lucide="brush" style="width: 16px; height: 16px;"></i>
                        Reboque de Parede
                    </button>
                    <button type="button" class="tab-btn" data-type="laje" style="flex: 1; min-width: 140px; padding: 12px 10px; font-size: 13px; font-weight: 600; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: rgba(255,255,255,0.02); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: var(--transition-normal);">
                        <i data-lucide="layers" style="width: 16px; height: 16px;"></i>
                        Betonagem de Laje
                    </button>
                </div>
            </div>

            <!-- Formulário Dinâmico de Dimensões -->
            <div class="card" style="padding: 24px;">
                <h3 style="margin-bottom: 20px; font-size: 16px; border-left: 3px solid var(--accent-primary); padding-left: 10px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="settings-2" style="color: var(--accent-primary); width: 18px; height: 18px;"></i>
                    Dimensões Físicas
                </h3>
                
                <form id="calculator-form" onsubmit="event.preventDefault();" style="display: flex; flex-direction: column; gap: 16px;">
                    
                    <!-- Inputs para Muro e Reboco (Comprimento e Altura) -->
                    <div id="dim-muro-reboco" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="input-length" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Comprimento (metros)</label>
                            <input type="number" step="0.01" id="input-length" class="form-control" placeholder="Ex: 25.00" value="10.00" style="width:100%;">
                        </div>
                        <div class="form-group">
                            <label for="input-height" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Altura (metros)</label>
                            <input type="number" step="0.01" id="input-height" class="form-control" placeholder="Ex: 2.20" value="2.00" style="width:100%;">
                        </div>
                    </div>

                    <!-- Input Extra para Reboco (Lados) -->
                    <div id="dim-reboco-extra" style="display: none;" class="form-group">
                        <label for="input-sides" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Lados a rebocar</label>
                        <select id="input-sides" class="form-control" style="width:100%; background: var(--bg-secondary); height: 38px;">
                            <option value="1">Apenas 1 Lado (Interior ou Exterior)</option>
                            <option value="2" selected>Ambos os Lados (Interior e Exterior)</option>
                        </select>
                    </div>

                    <!-- Inputs para Laje (Comprimento, Largura e Espessura) -->
                    <div id="dim-laje" style="display: none; flex-direction: column; gap: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label for="laje-length" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Comprimento (metros)</label>
                                <input type="number" step="0.01" id="laje-length" class="form-control" placeholder="Ex: 12.00" value="10.00" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label for="laje-width" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Largura (metros)</label>
                                <input type="number" step="0.01" id="laje-width" class="form-control" placeholder="Ex: 8.00" value="8.00" style="width:100%;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="laje-thickness" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Espessura da Laje (centímetros)</label>
                            <select id="laje-thickness" class="form-control" style="width:100%; background: var(--bg-secondary); height: 38px;">
                                <option value="8">8 cm (Laje leve / Enchimento)</option>
                                <option value="10" selected>10 cm (Padrão Residencial)</option>
                                <option value="12">12 cm (Laje Reforçada)</option>
                                <option value="15">15 cm (Tráfego / Comercial)</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Notas de Rácio Angolano -->
            <div class="card" style="padding: 16px; background: rgba(59, 130, 246, 0.02); border-color: rgba(59,130,246,0.15);">
                <h4 style="font-size: 13px; font-weight: 700; color: var(--accent-secondary); display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                    <i data-lucide="info" style="width: 16px; height: 16px;"></i>
                    Rácios de Referência em Angola:
                </h4>
                <ul style="font-size: 12px; color: var(--text-secondary); line-height: 1.6; padding-left: 20px; margin: 0; display: flex; flex-direction: column; gap: 4px;">
                    <li><strong>Muro:</strong> Consome 12.5 Tijolos por m², 0.25 sacos de cimento e 0.05m³ de areia por cada m² de muro.</li>
                    <li><strong>Reboque:</strong> Assume espessura média de 2cm, consumindo 0.12 sacos de cimento e 0.02m³ de areia por m² (por lado).</li>
                    <li><strong>Laje:</strong> Traço padrão residencial (1:2:3), estimando por m³ de betão cerca de 8 sacos de cimento, 0.6m³ areia e 0.8m³ brita.</li>
                </ul>
            </div>

        </div>

        <!-- COLUNA DIREITA: RESULTADOS EM REAL-TIME E CTA -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            
            <!-- CARD DE RESULTADOS -->
            <div class="card" style="padding: 24px; background: linear-gradient(135deg, rgba(249,115,22,0.03), rgba(10,15,30,0.95)); border-color: var(--accent-primary); position: relative; overflow: hidden;">
                <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: var(--accent-primary); filter: blur(50px); opacity: 0.2; pointer-events: none;"></div>
                
                <h3 style="margin-bottom: 20px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="calculator" style="color: var(--accent-primary); width: 18px; height: 18px;"></i>
                    Estimativa de Materiais
                </h3>

                <!-- Área Total / Volume Estimado -->
                <div style="background: rgba(255,255,255,0.03); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); text-align: center; margin-bottom: 20px;">
                    <span id="metric-label" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); display: block;">Área de Muro Estimada</span>
                    <strong id="metric-value" style="font-size: 24px; font-family: 'Outfit', sans-serif; color: #ffffff; display: block; margin-top: 4px;">20.00 m²</strong>
                </div>

                <!-- Lista de Materiais com Cards Individuais -->
                <div style="display: flex; flex-direction: column; gap: 12px;" id="materials-container">
                    
                    <!-- Cimento -->
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="background: rgba(249,115,22,0.05); padding: 8px; border-radius: 50%;">
                                <i data-lucide="package" style="width: 16px; height: 16px; color: var(--accent-primary);"></i>
                            </div>
                            <span style="font-size: 13px; font-weight: 500;">Cimento</span>
                        </div>
                        <span id="res-cement" style="font-size: 14px; font-weight: 700; color: #ffffff;">5.0 Sacos</span>
                    </div>

                    <!-- Areia -->
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="background: rgba(59,130,246,0.05); padding: 8px; border-radius: 50%;">
                                <i data-lucide="archive" style="width: 16px; height: 16px; color: var(--accent-secondary);"></i>
                            </div>
                            <span style="font-size: 13px; font-weight: 500;">Areia</span>
                        </div>
                        <span id="res-sand" style="font-size: 14px; font-weight: 700; color: #ffffff;">1.0 m³</span>
                    </div>

                    <!-- Tijolo (Opcional, Ocultado na laje/reboco) -->
                    <div id="box-brick" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="background: rgba(239,68,68,0.05); padding: 8px; border-radius: 50%;">
                                <i data-lucide="brick" style="width: 16px; height: 16px; color: var(--accent-danger);"></i>
                            </div>
                            <span style="font-size: 13px; font-weight: 500;">Tijolos</span>
                        </div>
                        <span id="res-brick" style="font-size: 14px; font-weight: 700; color: #ffffff;">250 Unid.</span>
                    </div>

                    <!-- Brita (Opcional, visível apenas na laje) -->
                    <div id="box-gravel" style="display: none; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="background: rgba(148,163,184,0.05); padding: 8px; border-radius: 50%;">
                                <i data-lucide="database" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                            </div>
                            <span style="font-size: 13px; font-weight: 500;">Brita</span>
                        </div>
                        <span id="res-gravel" style="font-size: 14px; font-weight: 700; color: #ffffff;">0.0 m³</span>
                    </div>

                    <!-- Água (Opcional, visível apenas na laje) -->
                    <div id="box-water" style="display: none; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="background: rgba(16,185,129,0.05); padding: 8px; border-radius: 50%;">
                                <i data-lucide="droplet" style="width: 16px; height: 16px; color: var(--accent-success);"></i>
                            </div>
                            <span style="font-size: 13px; font-weight: 500;">Água</span>
                        </div>
                        <span id="res-water" style="font-size: 14px; font-weight: 700; color: #ffffff;">0 Litros</span>
                    </div>

                </div>

                <!-- CTA Botão de Ação Guardar -->
                <div style="margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <?php if ($isLoggedIn): ?>
                        <button type="button" onclick="saveToNewProject();" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="folder-plus" style="width: 16px; height: 16px;"></i>
                            Iniciar Obra com esta Estimativa
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="redirectToRegister();" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="user-plus" style="width: 16px; height: 16px;"></i>
                            Criar Conta para Salvar Orçamento
                        </button>
                        <small style="color: var(--text-muted); font-size: 10px; display: block; text-align: center; margin-top: 8px; line-height: 1.4;">
                            Registo grátis em 1 minuto. Crie a sua conta e controle desvios financeiros!
                        </small>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>

</div>

<script nonce="<?php echo Security::getNonce(); ?>">
let activeService = 'muro';

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar listeners de tabs
    const tabs = document.querySelectorAll('#calc-tabs .tab-btn');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeService = tab.getAttribute('data-type');
            toggleFormFields();
            calculate();
        });
    });

    // Inputs dinâmicos para cálculo real-time
    const inputs = document.querySelectorAll('#calculator-form input, #calculator-form select');
    inputs.forEach(input => {
        input.addEventListener('input', calculate);
        input.addEventListener('change', calculate);
    });

    // Primeiro cálculo inicial
    calculate();
});

function toggleFormFields() {
    const dimMuroReboco = document.getElementById('dim-muro-reboco');
    const dimRebocoExtra = document.getElementById('dim-reboco-extra');
    const dimLaje = document.getElementById('dim-laje');
    
    const boxBrick = document.getElementById('box-brick');
    const boxGravel = document.getElementById('box-gravel');
    const boxWater = document.getElementById('box-water');
    
    const metricLabel = document.getElementById('metric-label');

    if (activeService === 'muro') {
        dimMuroReboco.style.display = 'grid';
        dimRebocoExtra.style.display = 'none';
        dimLaje.style.display = 'none';
        
        boxBrick.style.display = 'flex';
        boxGravel.style.display = 'none';
        boxWater.style.display = 'none';
        
        metricLabel.textContent = 'Área de Muro Estimada';
    } else if (activeService === 'reboco') {
        dimMuroReboco.style.display = 'grid';
        dimRebocoExtra.style.display = 'block';
        dimLaje.style.display = 'none';
        
        boxBrick.style.display = 'none';
        boxGravel.style.display = 'none';
        boxWater.style.display = 'none';
        
        metricLabel.textContent = 'Área de Reboque Total';
    } else if (activeService === 'laje') {
        dimMuroReboco.style.display = 'none';
        dimRebocoExtra.style.display = 'none';
        dimLaje.style.display = 'flex';
        
        boxBrick.style.display = 'none';
        boxGravel.style.display = 'flex';
        boxWater.style.display = 'flex';
        
        metricLabel.textContent = 'Volume de Betão Total';
    }
}

let lastCalculatedData = {
    title: '',
    description: ''
};

function calculate() {
    const length = parseFloat(document.getElementById('input-length').value) || 0;
    const height = parseFloat(document.getElementById('input-height').value) || 0;
    
    const sides = parseInt(document.getElementById('input-sides').value) || 1;
    
    const lajeLength = parseFloat(document.getElementById('laje-length').value) || 0;
    const lajeWidth = parseFloat(document.getElementById('laje-width').value) || 0;
    const lajeThicknessCm = parseInt(document.getElementById('laje-thickness').value) || 10;
    
    const metricValue = document.getElementById('metric-value');
    
    let cement = 0;
    let sand = 0;
    let bricks = 0;
    let gravel = 0;
    let water = 0;

    if (activeService === 'muro') {
        const area = length * height;
        metricValue.textContent = area.toFixed(2) + ' m²';
        
        // Rácios angolanos para Muro de Alvenaria (1m²):
        // 12.5 Tijolos, 0.25 sacos de cimento, 0.05m³ areia
        bricks = Math.ceil(area * 12.5);
        cement = Math.ceil(area * 0.25 * 10) / 10; // Arredondado para 1 casa decimal
        sand = Math.ceil(area * 0.05 * 100) / 100; // Arredondado para 2 casas decimais
        
        document.getElementById('res-brick').textContent = bricks + ' Unid.';
        document.getElementById('res-cement').textContent = cement.toFixed(1) + ' Sacos';
        document.getElementById('res-sand').textContent = sand.toFixed(2) + ' m³';
        
        lastCalculatedData.title = `Construção de Muro (${length}m x ${height}m)`;
        lastCalculatedData.description = `Estimativa da Calculadora: Muro com ${area.toFixed(1)}m² de área. Materiais sugeridos: ${bricks} tijolos, ${cement.toFixed(1)} sacos de cimento, ${sand.toFixed(2)}m³ areia.`;

    } else if (activeService === 'reboco') {
        const area = length * height * sides;
        metricValue.textContent = area.toFixed(2) + ' m²';
        
        // Rácios angolanos para reboco por m² (média 2cm):
        // 0.12 sacos de cimento, 0.02m³ areia
        cement = Math.ceil(area * 0.12 * 10) / 10;
        sand = Math.ceil(area * 0.02 * 100) / 100;
        
        document.getElementById('res-cement').textContent = cement.toFixed(1) + ' Sacos';
        document.getElementById('res-sand').textContent = sand.toFixed(2) + ' m³';
        
        lastCalculatedData.title = `Reboque de Parede (${length}m x ${height}m x ${sides} lados)`;
        lastCalculatedData.description = `Estimativa da Calculadora: Reboco de ${area.toFixed(1)}m² de área total de parede. Materiais sugeridos: ${cement.toFixed(1)} sacos de cimento, ${sand.toFixed(2)}m³ areia.`;

    } else if (activeService === 'laje') {
        const volume = lajeLength * lajeWidth * (lajeThicknessCm / 100);
        metricValue.textContent = volume.toFixed(2) + ' m³';
        
        // Rácios angolanos para laje/betonagem (traço 1:2:3 por m³):
        // 8 sacos cimento, 0.6m³ areia, 0.8m³ brita, 150L água
        cement = Math.ceil(volume * 8);
        sand = Math.ceil(volume * 0.6 * 100) / 100;
        gravel = Math.ceil(volume * 0.8 * 100) / 100;
        water = Math.ceil(volume * 150);
        
        document.getElementById('res-cement').textContent = cement.toFixed(1) + ' Sacos';
        document.getElementById('res-sand').textContent = sand.toFixed(2) + ' m³';
        document.getElementById('res-gravel').textContent = gravel.toFixed(2) + ' m³';
        document.getElementById('res-water').textContent = water + ' Litros';
        
        lastCalculatedData.title = `Betonagem de Laje (${lajeLength}m x ${lajeWidth}m x ${lajeThicknessCm}cm)`;
        lastCalculatedData.description = `Estimativa da Calculadora: Betonagem com ${volume.toFixed(2)}m³ de betão estrutural. Materiais sugeridos: ${cement} sacos de cimento, ${sand.toFixed(2)}m³ areia, ${gravel.toFixed(2)}m³ brita, ${water}L água.`;
    }
}

function saveToNewProject() {
    // Redireciona para /projects/create preenchendo os parâmetros calculados
    const url = `/projects/create?title=${encodeURIComponent(lastCalculatedData.title)}&description=${encodeURIComponent(lastCalculatedData.description)}`;
    window.location.href = url;
}

function redirectToRegister() {
    // Redireciona para o registo passando parâmetros para voltar
    window.location.href = '/register?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
}
</script>

<!-- Estilo Premium Especial das Abas -->
<style nonce="<?php echo Security::getNonce(); ?>">
.tab-btn.active {
    background: linear-gradient(135deg, rgba(249,115,22,0.1), rgba(249,115,22,0.2)) !important;
    border-color: var(--accent-primary) !important;
    color: var(--accent-primary) !important;
    box-shadow: 0 0 10px rgba(249,115,22,0.15);
}
.tab-btn:hover {
    background: rgba(255,255,255,0.05);
    color: var(--text-primary);
}
.calculator-grid {
    transition: var(--transition-normal);
}
@media (max-width: 768px) {
    .calculator-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
