<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Rota estritamente autenticada
middleware_require_auth();

$user = current_user();
$projectId = (int)input('id', 0);
$db = db();

// 1. Obter projeto e validação de equipa
try {
    $project = $db->fetch(
        "SELECT p.*, COALESCE(pm.role, 'owner') AS role 
         FROM projects p 
         LEFT JOIN project_managers pm ON p.id = pm.project_id AND pm.user_id = ?
         WHERE p.id = ? AND (p.user_id = ? OR pm.user_id = ?)",
        [$user['id'], $projectId, $user['id'], $user['id']]
    );
} catch (PDOException $e) {
    $project = null;
}

if (!$project) {
    set_flash_message('danger', 'Projeto não encontrado ou permissão negada.');
    redirect('/projects');
}

$title = 'Financeiro: ' . sanitize($project['title']) . ' — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';
?>
<style nonce="<?php echo Security::getNonce(); ?>">
    .whatsapp-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 360px;
        background: #1f2c34;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: whatsappSlideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .whatsapp-toast.shake {
        animation: whatsappSlideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards, whatsappShake 0.4s ease-in-out;
    }

    .whatsapp-header {
        background: #202c33;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .whatsapp-brand {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #e9edef;
        font-size: 12px;
        font-weight: 600;
    }

    .whatsapp-brand i {
        color: #25D366;
    }

    .whatsapp-time {
        color: #8696a0;
        font-size: 10px;
    }

    .whatsapp-body {
        padding: 14px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .whatsapp-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #25D366;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
    }

    .whatsapp-message {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .whatsapp-title {
        color: #e9edef;
        font-weight: 700;
        font-size: 13px;
        margin: 0;
    }

    .whatsapp-text {
        color: #d1d7db;
        font-size: 12px;
        line-height: 1.4;
        margin: 0;
    }

    @keyframes whatsappSlideIn {
        0% { transform: translateX(120%); opacity: 0; }
        100% { transform: translateX(0); opacity: 1; }
    }

    @keyframes whatsappSlideOut {
        0% { transform: translateX(0); opacity: 1; }
        100% { transform: translateX(120%); opacity: 0; }
    }

    @keyframes whatsappShake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-6px); }
        40%, 80% { transform: translateX(6px); }
    }

    /* Responsividade Inteligente do Painel Financeiro */
    .financials-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        width: 100%;
    }
    
    @media (max-width: 768px) {
        .financials-header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 12px !important;
        }
        .financials-header .financials-actions {
            width: 100% !important;
        }
    }
    
    @media (max-width: 576px) {
        .financials-actions {
            flex-direction: column !important;
            width: 100% !important;
            gap: 8px !important;
        }
        .financials-actions button,
        .financials-actions .btn {
            width: 100% !important;
            justify-content: center !important;
            padding: 10px !important;
        }
    }

    .charts-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 20px;
        align-items: stretch;
    }
    
    @media (max-width: 992px) {
        .charts-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    
    @media (max-width: 768px) {
        .charts-grid {
            grid-template-columns: 1fr !important;
        }
    }
    
    .financials-tabs {
        display: flex;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border-color);
        background: rgba(255, 255, 255, 0.01);
    }
    
    .financials-tabs .tab-btn {
        flex: 1;
        min-width: 160px;
        text-align: center;
        justify-content: center;
        border-radius: 0 !important;
        border-bottom: 2px solid transparent;
        padding: 16px 24px;
        font-size: 13px;
        transition: all 0.2s;
    }
    
    @media (max-width: 768px) {
        .financials-tabs {
            flex-direction: column !important;
            border-bottom: none !important;
        }
        .financials-tabs .tab-btn {
            width: 100% !important;
            border-bottom: 1px solid var(--border-color) !important;
            padding: 12px 16px !important;
            border-radius: 0 !important;
        }
        .financials-tabs .tab-btn.active {
            border-bottom: 2px solid var(--accent-primary) !important;
            background: rgba(255, 107, 0, 0.04) !important;
        }
    }
</style>
<?php

// 2. Procurar despesas ativas (não eliminadas)
// (CJ-07) anexos privados referenciados por id de documents (servidos via /api/files)
$expenses = $db->fetchAll(
    "SELECT e.*, pr.name AS registrant_name,
            (SELECT d.id FROM documents d WHERE d.expense_id = e.id AND d.kind = 'receipt' ORDER BY d.id DESC LIMIT 1) AS receipt_file_id,
            (SELECT d.id FROM documents d WHERE d.expense_id = e.id AND d.kind = 'expense_photo' ORDER BY d.id DESC LIMIT 1) AS photo_file_id
     FROM expenses e 
     JOIN profiles pr ON e.user_id = pr.id 
     WHERE e.project_id = ? AND e.deleted_at IS NULL 
     ORDER BY e.purchase_date DESC",
    [$projectId]
);

// 3. Procurar aportes de fundos
$funds = $db->fetchAll(
    "SELECT pf.*, pr.name AS registrant_name 
     FROM project_funds pf 
     JOIN profiles pr ON pf.user_id = pr.id 
     WHERE pf.project_id = ? 
     ORDER BY pf.created_at DESC",
    [$projectId]
);

// 4. Procurar itens de pré-orçamento (planeamento)
try {
    $preBudgets = $db->fetchAll(
        "SELECT pb.*, pr.name AS registrant_name 
         FROM pre_budgets pb 
         JOIN profiles pr ON pb.user_id = pr.id 
         WHERE pb.project_id = ? 
         ORDER BY pb.created_at DESC",
        [$projectId]
    );
} catch (PDOException $e) {
    $preBudgets = [];
}

// 5. Calcular métricas financeiras
$budget = (float)$project['budget'];
$totalSpent = 0.00;
$typeSpent = [
    'material' => 0.00,
    'labor' => 0.00,
    'equipment' => 0.00,
    'service' => 0.00,
    'other' => 0.00
];

$phases = json_decode($project['phases'], true) ?? ['Fundação', 'Estrutura', 'Alvenaria', 'Cobertura', 'Acabamentos'];
$phaseSpent = [];
foreach ($phases as $ph) {
    $phaseSpent[$ph] = 0.00;
}

foreach ($expenses as $exp) {
    $cost = (float)$exp['price'] * (float)$exp['quantity'];
    $totalSpent += $cost;
    
    // Agrupar por tipo
    $t = $exp['type'] ?? 'other';
    if (isset($typeSpent[$t])) {
        $typeSpent[$t] += $cost;
    } else {
        $typeSpent['other'] += $cost;
    }
    
    // Agrupar por fase
    $p = $exp['phase'];
    if (isset($phaseSpent[$p])) {
        $phaseSpent[$p] += $cost;
    } else {
        $phaseSpent[$p] = $cost;
    }
}

// Calcular planeamento do pré-orçamento
$totalPlanned = 0.00;
$phasePlanned = [];
foreach ($phases as $ph) {
    $phasePlanned[$ph] = 0.00;
}
foreach ($preBudgets as $pb) {
    $cost = (float)$pb['price'] * (float)$pb['quantity'];
    $totalPlanned += $cost;
    
    $p = $pb['phase'];
    if (isset($phasePlanned[$p])) {
        $phasePlanned[$p] += $cost;
    } else {
        $phasePlanned[$p] = $cost;
    }
}

// Somar Aportes convertidos em Kwanza
$totalFunds = 0.00;
foreach ($funds as $f) {
    $totalFunds += (float)$f['amount'] * (float)($f['exchange_rate'] ?? 1.0000);
}

if ($budget <= 0.00) {
    $budget = $totalPlanned;
    if ($budget <= 0.00) {
        $budget = $totalFunds;
    }
}

// (§5) Valores reais: a derrapagem e o saldo negativo não ficam escondidos
// (apenas as barras visuais são limitadas a 100%)
$spentPercent = $budget > 0 ? ($totalSpent / $budget) * 100 : 0.00;
$remainingBudget = $budget - $totalSpent;
$cashBalance = $totalFunds - $totalSpent;

// Nomes traduzidos de despesas
$typeTranslations = [
    'material' => 'Material',
    'labor' => 'Mão de Obra',
    'equipment' => 'Equipamento',
    'service' => 'Serviço',
    'other' => 'Outros'
];
?>

<div style="display:flex; flex-direction:column; gap:20px;">
    
    <!-- Link de Retorno e Ações Rápidas -->
    <div class="financials-header" style="display:flex; justify-content:space-between; align-items:center;">
        <a href="/projects/detail?id=<?php echo $projectId; ?>" style="display:inline-flex; align-items:center; gap:6px; font-size:14px; color:var(--text-secondary);">
            <i data-lucide="arrow-left" style="width:16px; height:16px;"></i>
            Voltar aos Detalhes da Obra
        </a>
        
        <?php if (in_array($project['role'], ['owner', 'manager'])): ?>
            <div class="financials-actions" style="display:flex; gap:10px;">
                <button class="btn btn-secondary" data-jsaction="openAddFundsModal" style="font-size:13px; padding: 8px 16px;">
                    <i data-lucide="wallet" style="width:16px; height:16px; color:var(--accent-success);"></i>
                    Inserir Fundos (Aporte)
                </button>
                <button class="btn btn-primary" data-jsaction="openAddExpenseModal" style="font-size:13px; padding: 8px 16px;">
                    <i data-lucide="plus" style="width:16px; height:16px;"></i>
                    Lançar Despesa
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Título Principal -->
    <div>
        <h2 style="display:flex; align-items:center; gap:8px;">
            <i data-lucide="piggy-bank" style="color:var(--accent-primary);"></i>
            Gestão Financeira: <?php echo sanitize($project['title']); ?>
        </h2>
        <p style="color:var(--text-secondary); font-size:14px;">Auditoria de despesas, orçamentos consumidos e balanço em Kwanza.</p>
    </div>

    <!-- METRICS CARDS -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
        <div class="card" style="padding:16px; display:flex; align-items:center; gap:16px;">
            <div style="background:rgba(249,115,22,0.05); padding:12px; border-radius:50%;">
                <i data-lucide="calculator" style="color:var(--accent-primary);"></i>
            </div>
            <div>
                <small style="color:var(--text-secondary);">Orçamento Geral</small>
                <h4 style="margin-top:2px;"><?php echo format_currency($budget); ?></h4>
            </div>
        </div>
        
        <div class="card" style="padding:16px; display:flex; align-items:center; gap:16px;">
            <div style="background:rgba(239,68,68,0.05); padding:12px; border-radius:50%;">
                <i data-lucide="trending-up" style="color:var(--accent-danger);"></i>
            </div>
            <div>
                <small style="color:var(--text-secondary);">Total Gasto</small>
                <h4 style="margin-top:2px; color:var(--accent-danger);"><?php echo format_currency($totalSpent); ?></h4>
            </div>
        </div>



        <div class="card" style="padding:16px; display:flex; align-items:center; gap:16px;">
            <div style="background:rgba(16,185,129,0.05); padding:12px; border-radius:50%;">
                <i data-lucide="landmark" style="color:var(--accent-success);"></i>
            </div>
            <div>
                <small style="color:var(--text-secondary);">Capital Injetado</small>
                <h4 style="margin-top:2px; color:var(--accent-success);"><?php echo format_currency($totalFunds); ?></h4>
            </div>
        </div>

        <div class="card" style="padding:16px; display:flex; align-items:center; gap:16px;">
            <div style="background:rgba(59,130,246,0.05); padding:12px; border-radius:50%;">
                <i data-lucide="vault" style="color:var(--accent-secondary);"></i>
            </div>
            <div>
                <small style="color:var(--text-secondary);">Saldo em Caixa</small>
                <h4 style="margin-top:2px; color:var(--accent-secondary);"><?php echo format_currency($cashBalance); ?></h4>
            </div>
        </div>
    </div>

    <!-- CHARTS PANEL (Chart.js via CDN) -->
    <div class="charts-grid" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; align-items:stretch;">
        <div class="card" style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:220px; padding:20px;">
            <h4 style="margin-bottom:10px; font-size:14px; text-transform:uppercase; color:var(--text-secondary);">Consumo do Orçamento</h4>
            <div style="position:relative; width:150px; height:150px;">
                <canvas id="budget-chart"></canvas>
                <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center;">
                    <h3 style="font-size:18px; font-weight:800;"><?php echo number_format($spentPercent, 0); ?>%</h3>
                    <small style="font-size:9px; color:var(--text-muted);">Gasto</small>
                </div>
            </div>
        </div>
        
        <div class="card" style="display:flex; flex-direction:column; min-height:220px; padding:20px;">
            <h4 style="margin-bottom:15px; font-size:14px; text-transform:uppercase; color:var(--text-secondary); text-align:center;">Gastos por Tipo</h4>
            <div style="flex:1; position:relative; min-height:140px;">
                <canvas id="type-chart"></canvas>
            </div>
        </div>

        <div class="card" style="display:flex; flex-direction:column; min-height:220px; padding:20px;">
            <h4 style="margin-bottom:15px; font-size:14px; text-transform:uppercase; color:var(--text-secondary); text-align:center;">Despesas por Fase</h4>
            <div style="flex:1; position:relative; min-height:140px;">
                <canvas id="phase-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- DUAL TABLES: DESPESAS & APORTES -->
    <div class="card" style="padding:0; overflow:hidden;">
        <!-- Navegação Tabs -->
        <div class="financials-tabs" style="display:flex; border-bottom:1px solid var(--border-color); background:rgba(255,255,255,0.01);">
            <button class="btn tab-btn active" id="tab-expenses-btn" data-jsaction="switchTab" data-jsarg="expenses" style="border-radius:0; padding:16px 24px; border-bottom: 2px solid var(--accent-primary);">
                <i data-lucide="shopping-bag" style="width:16px; height:16px; color:var(--accent-primary);"></i>
                Lançamentos de Despesas
            </button>
            <button class="btn tab-btn" id="tab-funds-btn" data-jsaction="switchTab" data-jsarg="funds" style="border-radius:0; padding:16px 24px;">
                <i data-lucide="landmark" style="width:16px; height:16px; color:var(--accent-success);"></i>
                Histórico de Aportes (Fundos)
            </button>
            <button class="btn tab-btn" id="tab-prebudget-btn" data-jsaction="switchTab" data-jsarg="prebudget" style="border-radius:0; padding:16px 24px;">
                <i data-lucide="clipboard-list" style="width:16px; height:16px; color:var(--accent-secondary);"></i>
                Pré-Orçamento (Planeamento)
            </button>
        </div>

        <!-- CONTEÚDO TAB: DESPESAS -->
        <div id="tab-expenses-content" style="padding:20px; overflow-x:auto;">
            <?php if (empty($expenses)): ?>
                <div style="text-align:center; padding: 40px; color:var(--text-secondary);">
                    <i data-lucide="shopping-cart" style="width:40px; height:40px; margin-bottom:10px; stroke-width:1.5;"></i>
                    <p>Nenhuma despesa registada nesta obra.</p>
                </div>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse; min-width:800px;" class="table responsive-table">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--border-color); color:var(--text-secondary); font-size:12px; text-transform:uppercase;">
                            <th style="padding:12px 8px;">Descrição</th>
                            <th style="padding:12px 8px;">Tipo</th>
                            <th style="padding:12px 8px;">Fase</th>
                            <th style="padding:12px 8px;">Valor Unitário</th>
                            <th style="padding:12px 8px;">Qtd</th>
                            <th style="padding:12px 8px;">Total</th>
                            <th style="padding:12px 8px;">Data</th>
                            <th style="padding:12px 8px;">Anexos</th>
                            <th style="padding:12px 8px; text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $exp): ?>
                            <?php
                            $totalExp = (float)$exp['price'] * (float)$exp['quantity'];
                            ?>
                            <tr style="border-bottom:1px solid var(--border-color); font-size:13px;">
                                <td data-label="Descrição" style="padding:12px 8px; font-weight:600; color:var(--text-primary);">
                                    <?php echo sanitize($exp['name']); ?>
                                    <?php if ($exp['supplier']): ?>
                                        <small style="display:block; color:var(--text-muted); font-weight:400;"><?php echo sanitize($exp['supplier']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Tipo" style="padding:12px 8px;">
                                    <span class="badge" style="background:rgba(255,255,255,0.03); color:var(--text-secondary); font-size:10px;">
                                        <?php echo $typeTranslations[$exp['type']] ?? 'Outros'; ?>
                                    </span>
                                </td>
                                <td data-label="Fase" style="padding:12px 8px; color:var(--accent-secondary);"><?php echo sanitize($exp['phase']); ?></td>
                                <td data-label="Valor Unitário" style="padding:12px 8px;"><?php echo format_currency((float)$exp['price']); ?></td>
                                <td data-label="Qtd" style="padding:12px 8px; font-weight:600;"><?php echo (float)$exp['quantity'] . ' ' . sanitize($exp['unit']); ?></td>
                                <td data-label="Total" style="padding:12px 8px; font-weight:700; color:var(--accent-primary);"><?php echo format_currency($totalExp); ?></td>
                                <td data-label="Data" style="padding:12px 8px; white-space:nowrap;"><?php echo date('d/m/Y', strtotime($exp['purchase_date'])); ?></td>
                                <td data-label="Anexos" style="padding:12px 8px; white-space:nowrap;">
                                    <div style="display:flex; gap:8px;">
                                        <?php if (!empty($exp['photo_file_id'])): ?>
                                            <a href="<?php echo APP_URL; ?>/api/files?id=<?php echo (int)$exp['photo_file_id']; ?>" target="_blank" title="Foto do produto (privada)" style="color:var(--accent-primary);"><i data-lucide="image" style="width:16px; height:16px;"></i></a>
                                        <?php endif; ?>
                                        <?php if (!empty($exp['receipt_file_id'])): ?>
                                            <a href="<?php echo APP_URL; ?>/api/files?id=<?php echo (int)$exp['receipt_file_id']; ?>" target="_blank" title="Recibo de compra (privado)" style="color:var(--accent-secondary);"><i data-lucide="file-text" style="width:16px; height:16px;"></i></a>
                                        <?php endif; ?>
                                        <?php if ($exp['youtube_link']): ?>
                                            <a href="<?php echo sanitize($exp['youtube_link']); ?>" target="_blank" title="Link de Vídeo" style="color:var(--accent-danger);"><i data-lucide="video" style="width:16px; height:16px;"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Ações" style="padding:12px 8px; text-align:right; white-space:nowrap;">
                                    <?php if (in_array($project['role'], ['owner', 'manager'])): ?>
                                        <button data-jsaction="openEditExpenseById" data-jsarg="<?php echo (int)$exp['id']; ?>" style="background:none; border:0; color:var(--text-secondary); cursor:pointer; margin-right:8px;" title="Editar">
                                            <i data-lucide="edit-3" style="width:14px; height:14px;"></i>
                                        </button>
                                        <button data-jsaction="deleteExpenseById" data-jsarg="<?php echo (int)$exp['id']; ?>" style="background:none; border:0; color:var(--accent-danger); cursor:pointer;" title="Anular Compra">
                                            <i data-lucide="ban" style="width:14px; height:14px;"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- CONTEÚDO TAB: APORTES -->
        <div id="tab-funds-content" style="padding:20px; display:none; overflow-x:auto;">
            <?php if (empty($funds)): ?>
                <div style="text-align:center; padding: 40px; color:var(--text-secondary);">
                    <i data-lucide="wallet" style="width:40px; height:40px; margin-bottom:10px; stroke-width:1.5;"></i>
                    <p>Nenhum aporte financeiro registado nesta obra.</p>
                </div>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse; min-width:800px;" class="table responsive-table">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--border-color); color:var(--text-secondary); font-size:12px; text-transform:uppercase;">
                            <th style="padding:12px 8px;">Origem / Descrição</th>
                            <th style="padding:12px 8px;">Moeda Original</th>
                            <th style="padding:12px 8px;">Câmbio (Kz)</th>
                            <th style="padding:12px 8px;">Total Convertido (AOA)</th>
                            <th style="padding:12px 8px;">Registado por</th>
                            <th style="padding:12px 8px;">Data</th>
                            <th style="padding:12px 8px; text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funds as $f): ?>
                            <?php
                            $totalConv = (float)$f['amount'] * (float)($f['exchange_rate'] ?? 1.0000);
                            ?>
                            <tr style="border-bottom:1px solid var(--border-color); font-size:13px;">
                                <td data-label="Origem / Descrição" style="padding:12px 8px; font-weight:600; color:var(--text-primary);">
                                    <?php echo sanitize($f['description'] ?: 'Injeção de Capital'); ?>
                                </td>
                                <td data-label="Moeda Original" style="padding:12px 8px; font-weight:600; color:var(--accent-success);">
                                    <?php 
                                    if ($f['currency'] === 'USD') echo '$ ' . number_format((float)$f['amount'], 2);
                                    elseif ($f['currency'] === 'EUR') echo '€ ' . number_format((float)$f['amount'], 2);
                                    else echo number_format((float)$f['amount'], 2) . ' AOA';
                                    ?>
                                </td>
                                <td data-label="Câmbio (Kz)" style="padding:12px 8px; color:var(--text-secondary);">1 = <?php echo number_format((float)($f['exchange_rate'] ?? 1.0000), 2); ?> Kz</td>
                                <td data-label="Total Convertido (AOA)" style="padding:12px 8px; font-weight:700; color:var(--text-primary);"><?php echo format_currency($totalConv); ?></td>
                                <td data-label="Registado por" style="padding:12px 8px; color:var(--text-secondary);"><?php echo sanitize($f['registrant_name']); ?></td>
                                <td data-label="Data" style="padding:12px 8px; white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($f['created_at'])); ?></td>
                                <td data-label="Ações" style="padding:12px 8px; text-align:right; white-space:nowrap;">
                                    <?php if (in_array($project['role'], ['owner', 'manager'])): ?>
                                        <button data-jsaction="openEditFundById" data-jsarg="<?php echo (int)$f['id']; ?>" style="background:none; border:0; color:var(--text-secondary); cursor:pointer; margin-right:8px;" title="Editar">
                                            <i data-lucide="edit-3" style="width:14px; height:14px;"></i>
                                        </button>
                                        <button data-jsaction="deleteFundById" data-jsarg="<?php echo (int)$f['id']; ?>" style="background:none; border:0; color:var(--accent-danger); cursor:pointer;" title="Eliminar Aporte">
                                            <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- CONTEÚDO TAB: PRÉ-ORÇAMENTO -->
        <div id="tab-prebudget-content" style="padding:20px; display:none; flex-direction:column; gap:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:10px;">
                <div>
                    <h3 style="font-size:16px; margin:0; display:flex; align-items:center; gap:8px; color:#ffffff;">
                        <i data-lucide="clipboard-list" style="color:var(--accent-secondary); width:18px; height:18px;"></i>
                        Planeamento de Custos e Materiais
                    </h3>
                    <p style="font-size:13px; color:var(--text-secondary); margin:4px 0 0 0;">Defina estimativas por fase para controlar desvios orçamentais de forma rigorosa.</p>
                </div>
                <?php if (in_array($project['role'], ['owner', 'manager'])): ?>
                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-secondary" data-jsaction="openImportPreModal" style="font-size:13px; padding: 8px 16px; border-color:var(--border-color); background:rgba(255,255,255,0.03); display:inline-flex; align-items:center; gap:6px;">
                            <i data-lucide="file-spreadsheet" style="width:16px; height:16px;"></i>
                            Importar Excel/CSV
                        </button>
                        <button class="btn btn-primary" data-jsaction="openAddPreItemModal" style="font-size:13px; padding: 8px 16px; background:var(--accent-secondary); border-color:var(--accent-secondary); box-shadow:0 4px 14px rgba(99,102,241,0.2); display:inline-flex; align-items:center; gap:6px;">
                            <i data-lucide="plus" style="width:16px; height:16px;"></i>
                            Adicionar Item de Planeamento
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($preBudgets)): ?>
                <div style="text-align:center; padding: 40px; color:var(--text-secondary);">
                    <i data-lucide="clipboard-list" style="width:40px; height:40px; margin-bottom:10px; stroke-width:1.5;"></i>
                    <p>Nenhum planeamento ou pré-orçamento registado nesta obra.</p>
                </div>
            <?php else: ?>
                <!-- Agrupado por Fase -->
                <div style="display:flex; flex-direction:column; gap:30px;">
                    <?php foreach ($phases as $ph): 
                        // Filtrar itens da fase
                        $phItems = array_filter($preBudgets, function($pb) use ($ph) {
                            return $pb['phase'] === $ph;
                        });
                        
                        $plannedSub = $phasePlanned[$ph] ?? 0.00;
                        $spentSub = $phaseSpent[$ph] ?? 0.00;
                        $diff = $plannedSub - $spentSub;
                        
                        // Determinar estado do orçamento da fase
                        if ($plannedSub == 0) {
                            if ($spentSub > 0) {
                                $badgeStyle = "background:rgba(239,68,68,0.1); color:var(--accent-danger); border:1px solid rgba(239,68,68,0.2);";
                                $badgeText = "Fase Sem Planeamento (Gasto: " . format_currency($spentSub) . ")";
                            } else {
                                continue; // Não planeado e não gasto nesta fase - ocultar para limpeza visual
                            }
                        } else {
                            if ($diff < 0) {
                                $badgeStyle = "background:rgba(239,68,68,0.1); color:var(--accent-danger); border:1px solid rgba(239,68,68,0.2);";
                                $badgeText = "Ultrapassado em " . format_currency(abs($diff));
                            } else {
                                $badgeStyle = "background:rgba(16,185,129,0.1); color:var(--accent-success); border:1px solid rgba(16,185,129,0.2);";
                                $badgeText = "Dentro do Orçamento (Poupança de " . format_currency($diff) . ")";
                            }
                        }
                    ?>
                        <div class="card" style="padding:0; overflow:hidden; border:1px solid var(--border-color); background:rgba(30,41,59,0.15);">
                            <!-- Cabeçalho da Fase -->
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:16px 20px; border-bottom:1px solid var(--border-color); background:rgba(255,255,255,0.01);">
                                <div>
                                    <h4 style="margin:0; font-size:14px; font-weight:700; color:#ffffff; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:8px;">
                                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--accent-secondary);"></span>
                                        <?php echo sanitize($ph); ?>
                                    </h4>
                                    <div style="margin-top:4px; font-size:12px; color:var(--text-secondary);">
                                        Planeado: <span style="font-weight:600; color:#ffffff;"><?php echo format_currency($plannedSub); ?></span>
                                        <span style="margin:0 6px;">|</span>
                                        Real Gasto: <span style="font-weight:600; color:<?php echo $diff < 0 ? 'var(--accent-danger)' : 'var(--accent-success)'; ?>;"><?php echo format_currency($spentSub); ?></span>
                                    </div>
                                </div>
                                <span class="badge" style="padding:6px 12px; font-size:11px; font-weight:600; border-radius:6px; <?php echo $badgeStyle; ?>">
                                    <?php echo $badgeText; ?>
                                </span>
                            </div>

                            <!-- Tabela de Itens Planeados da Fase -->
                            <div style="overflow-x:auto;">
                                <?php if (empty($phItems)): ?>
                                    <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">
                                        Nenhum material estimativo planeado para esta fase.
                                    </div>
                                <?php else: ?>
                                    <table style="width:100%; border-collapse:collapse;" class="table responsive-table">
                                        <thead>
                                            <tr style="text-align:left; border-bottom:1px solid var(--border-color); color:var(--text-secondary); font-size:11px; text-transform:uppercase; background:rgba(0,0,0,0.05);">
                                                <th style="padding:10px 16px;">Material / Serviço Estimado</th>
                                                <th style="padding:10px 16px;">Preço Unitário</th>
                                                <th style="padding:10px 16px;">Qtd Planeada</th>
                                                <th style="padding:10px 16px;">Qtd Comprada</th>
                                                <th style="padding:10px 16px;">Total Estimado</th>
                                                <th style="padding:10px 16px; text-align:right;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($phItems as $pbItem): 
                                                $pbTotal = (float)$pbItem['price'] * (float)$pbItem['quantity'];
                                                $purchasedQty = 0.00;
                                                foreach ($expenses as $exp) {
                                                    if (mb_strtolower(trim($exp['name'])) === mb_strtolower(trim($pbItem['name'])) && $exp['phase'] === $pbItem['phase']) {
                                                        $purchasedQty += (float)$exp['quantity'];
                                                    }
                                                }
                                            ?>
                                                <tr style="border-bottom:1px solid var(--border-color); font-size:13px;">
                                                    <td data-label="Material / Serviço Estimado" style="padding:12px 16px; font-weight:600; color:var(--text-primary);">
                                                        <?php echo sanitize($pbItem['name']); ?>
                                                    </td>
                                                    <td data-label="Preço Unitário" style="padding:12px 16px; color:var(--text-secondary);"><?php echo format_currency((float)$pbItem['price']); ?></td>
                                                    <td data-label="Qtd Planeada" style="padding:12px 16px; font-weight:500;"><?php echo (float)$pbItem['quantity'] . ' ' . sanitize($pbItem['unit']); ?></td>
                                                    <td data-label="Qtd Comprada" style="padding:12px 16px; font-weight:600;">
                                                        <?php if ($purchasedQty <= 0): ?>
                                                            <span style="color:var(--text-muted); font-size:12px; font-weight:normal;">—</span>
                                                        <?php else: ?>
                                                            <div style="display:flex; flex-direction:column; gap:4px;">
                                                                <span style="color:#ffffff; font-size:13px; font-weight:700;">
                                                                    <?php echo $purchasedQty . ' ' . sanitize($pbItem['unit']); ?>
                                                                </span>
                                                                <?php 
                                                                $percent = $pbItem['quantity'] > 0 ? ($purchasedQty / (float)$pbItem['quantity']) * 100 : 0;
                                                                if ($percent >= 100) {
                                                                    $badgeBg = 'rgba(16, 185, 129, 0.1)';
                                                                    $badgeColor = 'var(--accent-success)';
                                                                    $badgeBorder = 'rgba(16, 185, 129, 0.2)';
                                                                    $labelText = 'Totalmente Comprado';
                                                                } else {
                                                                    $badgeBg = 'rgba(249, 115, 22, 0.1)';
                                                                    $badgeColor = 'var(--accent-primary)';
                                                                    $badgeBorder = 'rgba(249, 115, 22, 0.2)';
                                                                    $labelText = number_format($percent, 0) . '% Comprado';
                                                                }
                                                                ?>
                                                                <span class="badge" style="background:<?php echo $badgeBg; ?>; color:<?php echo $badgeColor; ?>; border:1px solid <?php echo $badgeBorder; ?>; font-size:10px; padding:2px 8px; border-radius:4px; align-self:flex-start;">
                                                                    <?php echo $labelText; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Total Estimado" style="padding:12px 16px; font-weight:700; color:var(--accent-secondary);"><?php echo format_currency($pbTotal); ?></td>
                                                    <td data-label="Ações" style="padding:12px 16px; text-align:right; white-space:nowrap;">
                                                        <div style="display:inline-flex; gap:12px; align-items:center;">
                                                            <?php if (in_array($project['role'], ['owner', 'manager'])): ?>
                                                                <button class="btn btn-secondary" data-jsaction="prefillPreById" data-jsarg="<?php echo (int)$pbItem['id']; ?>" style="padding:4px 10px; font-size:11px; display:inline-flex; align-items:center; gap:4px; background:rgba(16,185,129,0.05); border-color:rgba(16,185,129,0.1);" title="Lançar como despesa real">
                                                                    <i data-lucide="shopping-cart" style="width:12px; height:12px; color:var(--accent-success);"></i>
                                                                    Registar Compra
                                                                </button>
                                                                
                                                                <button data-jsaction="openEditPreById" data-jsarg="<?php echo (int)$pbItem['id']; ?>" style="background:none; border:0; color:var(--accent-primary); cursor:pointer; padding:4px;" title="Editar Material Estimado">
                                                                    <i data-lucide="edit-3" style="width:14px; height:14px;"></i>
                                                                </button>
                                                                
                                                                <button data-jsaction="deletePreById" data-jsarg="<?php echo (int)$pbItem['id']; ?>" style="background:none; border:0; color:var(--accent-danger); cursor:pointer; padding:4px;" title="Eliminar Planeamento">
                                                                    <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: REGISTAR APORTE DE FUNDOS (CASH) -->
<!-- ========================================== -->
<div class="modal" id="add-funds-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div class="card" style="width:100%; max-width:480px; padding:24px; position:relative; margin:16px;">
        <button data-jsaction="closeAddFundsModal" style="position:absolute; top:16px; right:16px; color:var(--text-secondary); background:none; border:0; cursor:pointer;"><i data-lucide="x"></i></button>
        
        <h3 style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="wallet" style="color:var(--accent-success);"></i>
            Registar Aporte Financeiro
        </h3>
        
        <form id="add-funds-form" data-jsaction="submitAddFunds" data-jsprevent="1">
            <div class="form-group" style="margin-bottom:16px;">
                <label for="fund-amount" style="display:block; font-size:12px; margin-bottom:6px;">Valor do Aporte *</label>
                <input type="number" step="0.01" id="fund-amount" class="form-control" placeholder="Ex: 50000" required style="width:100%;">
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="fund-currency" style="display:block; font-size:12px; margin-bottom:6px;">Moeda Original *</label>
                    <select id="fund-currency" class="form-control" data-jsaction="adjustExchangeRate" data-jsarg="__value__" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="AOA">AOA (Kwanza)</option>
                        <option value="USD">USD (Dólar)</option>
                        <option value="EUR">EUR (Euro)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fund-rate" style="display:block; font-size:12px; margin-bottom:6px;">Taxa Câmbio (em Kz) *</label>
                    <input type="number" step="0.0001" id="fund-rate" class="form-control" value="1.0000" required style="width:100%;">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom:20px;">
                <label for="fund-desc" style="display:block; font-size:12px; margin-bottom:6px;">Origem / Descrição</label>
                <input type="text" id="fund-desc" class="form-control" placeholder="Ex: Transferência Bancária - Sócio A" style="width:100%;">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" data-jsaction="closeAddFundsModal">Cancelar</button>
                <button type="submit" id="add-funds-btn" class="btn btn-primary" style="background:var(--accent-success); box-shadow: 0 4px 14px rgba(16,185,129,0.3);">Registar Entrada</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDITAR APORTE DE FUNDOS (CASH) -->
<!-- ========================================== -->
<div class="modal" id="edit-funds-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div class="card" style="width:100%; max-width:480px; padding:24px; position:relative; margin:16px;">
        <button data-jsaction="closeEditFundsModal" style="position:absolute; top:16px; right:16px; color:var(--text-secondary); background:none; border:0; cursor:pointer;"><i data-lucide="x"></i></button>
        
        <h3 style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="edit-3" style="color:var(--accent-success);"></i>
            Editar Aporte Financeiro
        </h3>
        
        <form id="edit-funds-form" data-jsaction="submitEditFund" data-jsprevent="1">
            <input type="hidden" id="edit-fund-id">
            
            <div class="form-group" style="margin-bottom:16px;">
                <label for="edit-fund-amount" style="display:block; font-size:12px; margin-bottom:6px;">Valor do Aporte *</label>
                <input type="number" step="0.01" id="edit-fund-amount" class="form-control" placeholder="Ex: 50000" required style="width:100%;">
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-fund-currency" style="display:block; font-size:12px; margin-bottom:6px;">Moeda Original *</label>
                    <select id="edit-fund-currency" class="form-control" data-jsaction="adjustEditExchangeRate" data-jsarg="__value__" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="AOA">AOA (Kwanza)</option>
                        <option value="USD">USD (Dólar)</option>
                        <option value="EUR">EUR (Euro)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-fund-rate" style="display:block; font-size:12px; margin-bottom:6px;">Taxa Câmbio (em Kz) *</label>
                    <input type="number" step="0.0001" id="edit-fund-rate" class="form-control" value="1.0000" required style="width:100%;">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom:20px;">
                <label for="edit-fund-desc" style="display:block; font-size:12px; margin-bottom:6px;">Origem / Descrição</label>
                <input type="text" id="edit-fund-desc" class="form-control" placeholder="Ex: Transferência Bancária - Sócio A" style="width:100%;">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" data-jsaction="closeEditFundsModal">Cancelar</button>
                <button type="submit" id="edit-funds-btn" class="btn btn-primary" style="background:var(--accent-success); box-shadow: 0 4px 14px rgba(16,185,129,0.3);">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: IMPORTAR PRÉ-ORÇAMENTO DESDE EXCEL/CSV (NOVO) -->
<!-- ========================================== -->
<div class="modal" id="import-pre-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div class="card" style="width:100%; max-width:520px; padding:24px; position:relative; margin:16px; background:var(--bg-card); border-radius:var(--radius-lg); border:1px solid var(--border-color);">
        <button data-jsaction="closeImportPreModal" style="position:absolute; top:16px; right:16px; color:var(--text-secondary); background:none; border:0; cursor:pointer;"><i data-lucide="x"></i></button>
        
        <h3 style="margin-bottom:8px; display:flex; align-items:center; gap:8px; color:#ffffff;">
            <i data-lucide="file-spreadsheet" style="color:var(--accent-secondary);"></i>
            Importar Pré-Orçamento
        </h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">Envie uma folha de cálculo CSV exportada do Excel para preencher o planeamento da obra instantaneamente.</p>
        
        <!-- Instruções de colunas -->
        <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:16px; margin-bottom:20px; font-size:12.5px;">
            <strong style="display:block; margin-bottom:6px; color:#ffffff;">Instruções do Excel / CSV:</strong>
            <p style="margin-bottom:8px; color:var(--text-secondary); line-height:1.4;">Certifique-se de que a sua folha de cálculo contém exatamente as seguintes colunas no cabeçalho:</p>
            <code style="display:block; background:#0f172a; padding:8px 12px; border-radius:4px; font-family:monospace; color:var(--accent-secondary); font-size:12px; margin-bottom:12px;">
                Item; Preço; Quantidade; Unidade; Fase
            </code>
            <span style="display:block; font-size:11px; color:var(--text-muted);">
                💡 *Nota:* A coluna **Fase** deve corresponder a uma das fases da obra (ex: Fundação, Estrutura, Alvenaria, Cobertura, Acabamentos).
            </span>
        </div>

        <form id="import-pre-form" data-jsaction="submitImportPre" data-jsprevent="1" style="display:flex; flex-direction:column; gap:16px;">
            <div style="display:flex; flex-direction:column; gap:6px;">
                <label style="font-size:12px; font-weight:600; color:#ffffff;">Selecionar Ficheiro (.csv)</label>
                <input type="file" id="import-csv-file" accept=".csv, .txt" required class="form-control" style="font-size:13px; padding:10px; border-color:var(--border-color); background:rgba(0,0,0,0.2); width:100%;">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:8px;">
                <button type="button" class="btn btn-secondary" data-jsaction="closeImportPreModal">Cancelar</button>
                <button type="submit" id="import-pre-btn" class="btn btn-primary" style="background:var(--accent-secondary); border-color:var(--accent-secondary); font-weight:700; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 14px rgba(99,102,241,0.25);">
                    <i data-lucide="upload-cloud" style="width:16px; height:16px;"></i>
                    Iniciar Importação
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: ADICIONAR ITEM DE PLANEAMENTO (PRÉ-ORÇAMENTO) -->
<!-- ========================================== -->
<div class="modal" id="add-pre-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div class="card" style="width:100%; max-width:480px; padding:24px; position:relative; margin:16px;">
        <button data-jsaction="closeAddPreItemModal" style="position:absolute; top:16px; right:16px; color:var(--text-secondary); background:none; border:0; cursor:pointer;"><i data-lucide="x"></i></button>
        
        <h3 style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="clipboard-list" style="color:var(--accent-secondary);"></i>
            Item de Planeamento (Pré-Orçamento)
        </h3>
        
        <form id="add-pre-form" data-jsaction="submitAddPreItem" data-jsprevent="1">
            <div class="form-group" style="margin-bottom:16px;">
                <label for="pre-name" style="display:block; font-size:12px; margin-bottom:6px;">Material ou Serviço Planeado *</label>
                <input type="text" id="pre-name" class="form-control" placeholder="Ex: Cimento Portland 32.5N" required style="width:100%;">
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="pre-price" style="display:block; font-size:12px; margin-bottom:6px;">Preço Unitário Estimado (Kz) *</label>
                    <input type="number" step="0.01" id="pre-price" class="form-control" placeholder="Ex: 5000" required style="width:100%;">
                </div>
                
                <div class="form-group">
                    <label for="pre-qty" style="display:block; font-size:12px; margin-bottom:6px;">Quantidade Planeada *</label>
                    <input type="number" step="0.01" id="pre-qty" class="form-control" value="1.00" required style="width:100%;">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
                <div class="form-group">
                    <label for="pre-unit" style="display:block; font-size:12px; margin-bottom:6px;">Unidade *</label>
                    <input type="text" id="pre-unit" class="form-control" value="un" required style="width:100%;">
                </div>
                
                <div class="form-group">
                    <label for="pre-phase" style="display:block; font-size:12px; margin-bottom:6px;">Fase da Obra *</label>
                    <select id="pre-phase" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <?php foreach ($phases as $ph): ?>
                            <option value="<?php echo sanitize($ph); ?>"><?php echo sanitize($ph); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" data-jsaction="closeAddPreItemModal">Cancelar</button>
                <button type="submit" id="add-pre-btn" class="btn btn-primary" style="background:var(--accent-secondary); border-color:var(--accent-secondary); box-shadow: 0 4px 14px rgba(99,102,241,0.3);">Registar Planeamento</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDITAR ITEM DE PLANEAMENTO (PRÉ-ORÇAMENTO) -->
<!-- ========================================== -->
<div class="modal" id="edit-pre-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div class="card" style="width:100%; max-width:480px; padding:24px; position:relative; margin:16px;">
        <button data-jsaction="closeEditPreItemModal" style="position:absolute; top:16px; right:16px; color:var(--text-secondary); background:none; border:0; cursor:pointer;"><i data-lucide="x"></i></button>
        
        <h3 style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="edit-3" style="color:var(--accent-secondary);"></i>
            Editar Item de Planeamento
        </h3>
        
        <form id="edit-pre-form" data-jsaction="submitEditPreItem" data-jsprevent="1">
            <input type="hidden" id="edit-pre-id">
            
            <div class="form-group" style="margin-bottom:16px;">
                <label for="edit-pre-name" style="display:block; font-size:12px; margin-bottom:6px;">Material ou Serviço Planeado *</label>
                <input type="text" id="edit-pre-name" class="form-control" required style="width:100%;">
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-pre-price" style="display:block; font-size:12px; margin-bottom:6px;">Preço Unitário Estimado (Kz) *</label>
                    <input type="number" step="0.01" id="edit-pre-price" class="form-control" required style="width:100%;">
                </div>
                
                <div class="form-group">
                    <label for="edit-pre-qty" style="display:block; font-size:12px; margin-bottom:6px;">Quantidade Planeada *</label>
                    <input type="number" step="0.01" id="edit-pre-qty" class="form-control" required style="width:100%;">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
                <div class="form-group">
                    <label for="edit-pre-unit" style="display:block; font-size:12px; margin-bottom:6px;">Unidade *</label>
                    <input type="text" id="edit-pre-unit" class="form-control" required style="width:100%;">
                </div>
                
                <div class="form-group">
                    <label for="edit-pre-phase" style="display:block; font-size:12px; margin-bottom:6px;">Fase da Obra *</label>
                    <select id="edit-pre-phase" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <?php foreach ($phases as $ph): ?>
                            <option value="<?php echo sanitize($ph); ?>"><?php echo sanitize($ph); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" data-jsaction="closeEditPreItemModal">Cancelar</button>
                <button type="submit" id="edit-pre-btn" class="btn btn-primary" style="background:var(--accent-secondary); border-color:var(--accent-secondary); box-shadow: 0 4px 14px rgba(99,102,241,0.3);">Guardar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: LANÇAR DESPESA -->
<!-- ========================================== -->
<div class="modal" id="add-expense-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(6px); overflow-y:auto; padding: 20px 0;">
    <div class="card" style="width:100%; max-width:640px; padding:24px; position:relative; margin:auto;">
        <button data-jsaction="closeAddExpenseModal" style="position:absolute; top:16px; right:16px; color:var(--text-secondary); background:none; border:0; cursor:pointer;"><i data-lucide="x"></i></button>
        
        <h3 style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="plus" style="color:var(--accent-primary);"></i>
            Lançar Nova Despesa
        </h3>
        
        <form id="add-expense-form" data-jsaction="submitAddExpense" data-jsprevent="1" enctype="multipart/form-data">
            <!-- Dados Básicos -->
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="exp-name" style="display:block; font-size:12px; margin-bottom:6px;">Material ou Serviço *</label>
                    <input type="text" id="exp-name" class="form-control" placeholder="Ex: Cimento Portland 32.5N (Saco)" required style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="exp-type" style="display:block; font-size:12px; margin-bottom:6px;">Tipo de Despesa *</label>
                    <select id="exp-type" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="material">Material</option>
                        <option value="labor">Mão de Obra</option>
                        <option value="equipment">Equipamento</option>
                        <option value="service">Serviço</option>
                        <option value="other">Outros</option>
                    </select>
                </div>
            </div>

            <!-- Valores -->
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="exp-price" style="display:block; font-size:12px; margin-bottom:6px;">Preço Unitário (Kz) *</label>
                    <input type="number" step="0.01" id="exp-price" class="form-control" required style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="exp-qty" style="display:block; font-size:12px; margin-bottom:6px;">Quantidade *</label>
                    <input type="number" step="0.01" id="exp-qty" class="form-control" value="1.00" required style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="exp-unit" style="display:block; font-size:12px; margin-bottom:6px;">Unidade *</label>
                    <input type="text" id="exp-unit" class="form-control" value="un" required style="width:100%;">
                </div>
            </div>

            <!-- Cronograma e Data -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="exp-phase" style="display:block; font-size:12px; margin-bottom:6px;">Fase Associada *</label>
                    <select id="exp-phase" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <?php foreach ($phases as $ph): ?>
                            <option value="<?php echo sanitize($ph); ?>"><?php echo sanitize($ph); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="exp-date" style="display:block; font-size:12px; margin-bottom:6px;">Data de Compra/Lançamento *</label>
                    <input type="date" id="exp-date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="width:100%;">
                </div>
            </div>

            <!-- Metadados de Fornecedor -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="exp-supplier" style="display:block; font-size:12px; margin-bottom:6px;">Fornecedor</label>
                    <input type="text" id="exp-supplier" class="form-control" placeholder="Ex: Pumangol Talatona" style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="exp-location" style="display:block; font-size:12px; margin-bottom:6px;">Local da Compra</label>
                    <input type="text" id="exp-location" class="form-control" placeholder="Ex: Armazém Talatona" style="width:100%;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label for="exp-yt" style="display:block; font-size:12px; margin-bottom:6px;">Vídeo da Obra (Link YouTube - opcional)</label>
                <input type="url" id="exp-yt" class="form-control" placeholder="https://www.youtube.com/watch?v=..." style="width:100%;">
            </div>

            <!-- Anexos de Ficheiro -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px;">Foto do Produto (Opcional)</label>
                    <input type="file" id="exp-photo" class="form-control" accept="image/*" style="font-size:11px;">
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px;">Fatura/Recibo PDF (Opcional)</label>
                    <input type="file" id="exp-receipt" class="form-control" accept="image/*,application/pdf" style="font-size:11px;">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" data-jsaction="closeAddExpenseModal">Cancelar</button>
                <button type="submit" id="add-expense-btn" class="btn btn-primary">Lançar Despesa</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDITAR DESPESA -->
<!-- ========================================== -->
<div class="modal" id="edit-expense-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(6px); overflow-y:auto; padding:20px 0;">
    <div class="card" style="width:100%; max-width:640px; padding:24px; position:relative; margin:auto;">
        <button data-jsaction="closeEditExpenseModal" style="position:absolute; top:16px; right:16px; color:var(--text-secondary); background:none; border:0; cursor:pointer;"><i data-lucide="x"></i></button>
        
        <h3 style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="edit-3" style="color:var(--accent-primary);"></i>
            Editar Lançamento
        </h3>
        
        <form id="edit-expense-form" data-jsaction="submitEditExpense" data-jsprevent="1" enctype="multipart/form-data">
            <input type="hidden" id="edit-exp-id">
            
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-exp-name" style="display:block; font-size:12px; margin-bottom:6px;">Material ou Serviço *</label>
                    <input type="text" id="edit-exp-name" class="form-control" required style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="edit-exp-type" style="display:block; font-size:12px; margin-bottom:6px;">Tipo de Despesa *</label>
                    <select id="edit-exp-type" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="material">Material</option>
                        <option value="labor">Mão de Obra</option>
                        <option value="equipment">Equipamento</option>
                        <option value="service">Serviço</option>
                        <option value="other">Outros</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-exp-price" style="display:block; font-size:12px; margin-bottom:6px;">Preço Unitário (Kz) *</label>
                    <input type="number" step="0.01" id="edit-exp-price" class="form-control" required style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="edit-exp-qty" style="display:block; font-size:12px; margin-bottom:6px;">Quantidade *</label>
                    <input type="number" step="0.01" id="edit-exp-qty" class="form-control" required style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="edit-exp-unit" style="display:block; font-size:12px; margin-bottom:6px;">Unidade *</label>
                    <input type="text" id="edit-exp-unit" class="form-control" required style="width:100%;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-exp-phase" style="display:block; font-size:12px; margin-bottom:6px;">Fase Associada *</label>
                    <select id="edit-exp-phase" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <?php foreach ($phases as $ph): ?>
                            <option value="<?php echo sanitize($ph); ?>"><?php echo sanitize($ph); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-exp-date" style="display:block; font-size:12px; margin-bottom:6px;">Data *</label>
                    <input type="date" id="edit-exp-date" class="form-control" required style="width:100%;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-exp-supplier" style="display:block; font-size:12px; margin-bottom:6px;">Fornecedor</label>
                    <input type="text" id="edit-exp-supplier" class="form-control" style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="edit-exp-location" style="display:block; font-size:12px; margin-bottom:6px;">Local da Compra</label>
                    <input type="text" id="edit-exp-location" class="form-control" style="width:100%;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label for="edit-exp-yt" style="display:block; font-size:12px; margin-bottom:6px;">Vídeo da Obra (Link YouTube)</label>
                <input type="url" id="edit-exp-yt" class="form-control" style="width:100%;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px;">Nova Foto do Produto</label>
                    <input type="file" id="edit-exp-photo" class="form-control" accept="image/*" style="font-size:11px;">
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px;">Novo Recibo PDF</label>
                    <input type="file" id="edit-exp-receipt" class="form-control" accept="image/*,application/pdf" style="font-size:11px;">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" data-jsaction="closeEditExpenseModal">Cancelar</button>
                <button type="submit" id="edit-expense-btn-submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
window.FINANCIAL_METRICS = {
    phasePlanned: <?php echo json_encode($phasePlanned); ?>,
    phaseSpent: <?php echo json_encode($phaseSpent); ?>
};
// --- SWITCH TAB CONTROLLER ---

// (CJ-12) Índice dos itens por id para as ações das linhas (sem handlers inline)
window.FINANCIAL_ITEMS = {
    expenses: <?php echo json_encode($expenses); ?>,
    funds: <?php echo json_encode($funds); ?>,
    prebudget: <?php echo json_encode($preBudgets); ?>
};
(function () {
    function indexById(list) {
        var map = {};
        (list || []).forEach(function (item) { if (item && item.id != null) map[item.id] = item; });
        return map;
    }
    window.FINANCIAL_ITEMS.expenses = indexById(window.FINANCIAL_ITEMS.expenses);
    window.FINANCIAL_ITEMS.funds = indexById(window.FINANCIAL_ITEMS.funds);
    window.FINANCIAL_ITEMS.prebudget = indexById(window.FINANCIAL_ITEMS.prebudget);
})();

function finItem(list, id) {
    return window.FINANCIAL_ITEMS && window.FINANCIAL_ITEMS[list] ? window.FINANCIAL_ITEMS[list][id] : null;
}
function openEditExpenseById(id) { var it = finItem('expenses', id); if (it) openEditExpenseModal(it); }
function deleteExpenseById(id) { App.Projects.deleteExpense(id); }
function openEditFundById(id) { var it = finItem('funds', id); if (it) openEditFundModal(it); }
function deleteFundById(id) { deleteFund(id); }
function openEditPreById(id) { var it = finItem('prebudget', id); if (it) openEditPreItemModal(it); }
function deletePreById(id) { deletePreItem(id); }
function prefillPreById(id) { var it = finItem('prebudget', id); if (it) preFillExpense(it.name, it.price, it.quantity, it.unit, it.phase); }
function switchTab(tab) {
    const expensesBtn = document.getElementById('tab-expenses-btn');
    const fundsBtn = document.getElementById('tab-funds-btn');
    const prebudgetBtn = document.getElementById('tab-prebudget-btn');
    
    const expensesContent = document.getElementById('tab-expenses-content');
    const fundsContent = document.getElementById('tab-funds-content');
    const prebudgetContent = document.getElementById('tab-prebudget-content');
    
    // Reset buttons
    [expensesBtn, fundsBtn, prebudgetBtn].forEach(btn => {
        if (btn) {
            btn.classList.remove('active');
            btn.style.borderBottom = 'none';
        }
    });
    
    // Hide content
    if (expensesContent) expensesContent.style.display = 'none';
    if (fundsContent) fundsContent.style.display = 'none';
    if (prebudgetContent) prebudgetContent.style.display = 'none';
    
    // Set active
    if (tab === 'expenses' && expensesBtn && expensesContent) {
        expensesBtn.classList.add('active');
        expensesBtn.style.borderBottom = '2px solid var(--accent-primary)';
        expensesContent.style.display = 'block';
    } else if (tab === 'funds' && fundsBtn && fundsContent) {
        fundsBtn.classList.add('active');
        fundsBtn.style.borderBottom = '2px solid var(--accent-primary)';
        fundsContent.style.display = 'block';
    } else if (tab === 'prebudget' && prebudgetBtn && prebudgetContent) {
        prebudgetBtn.classList.add('active');
        prebudgetBtn.style.borderBottom = '2px solid var(--accent-primary)';
        prebudgetContent.style.display = 'flex';
    }
}

// --- MODAL CONTROLLERS ---
function openAddFundsModal() {
    document.getElementById('add-funds-modal').style.display = 'flex';
}
function closeAddFundsModal() {
    document.getElementById('add-funds-modal').style.display = 'none';
}
function openEditFundModal(fund) {
    document.getElementById('edit-fund-id').value = fund.id;
    document.getElementById('edit-fund-amount').value = fund.amount;
    document.getElementById('edit-fund-currency').value = fund.currency;
    document.getElementById('edit-fund-rate').value = fund.exchange_rate || '1.0000';
    document.getElementById('edit-fund-desc').value = fund.description || '';
    
    // Adjust exchange rate input disabled state based on currency
    const rateInput = document.getElementById('edit-fund-rate');
    rateInput.disabled = (fund.currency === 'AOA');
    
    document.getElementById('edit-funds-modal').style.display = 'flex';
}
function closeEditFundsModal() {
    document.getElementById('edit-funds-modal').style.display = 'none';
}
function openAddExpenseModal() {
    document.getElementById('add-expense-modal').style.display = 'flex';
}
function closeAddExpenseModal() {
    document.getElementById('add-expense-modal').style.display = 'none';
}
function openAddPreItemModal() {
    document.getElementById('add-pre-modal').style.display = 'flex';
}
function closeAddPreItemModal() {
    document.getElementById('add-pre-modal').style.display = 'none';
}

function openEditExpenseModal(expense) {
    document.getElementById('edit-exp-id').value = expense.id;
    document.getElementById('edit-exp-name').value = expense.name;
    document.getElementById('edit-exp-type').value = expense.type;
    document.getElementById('edit-exp-price').value = expense.price;
    document.getElementById('edit-exp-qty').value = expense.quantity;
    document.getElementById('edit-exp-unit').value = expense.unit;
    document.getElementById('edit-exp-phase').value = expense.phase;
    document.getElementById('edit-exp-date').value = expense.purchase_date;
    document.getElementById('edit-exp-supplier').value = expense.supplier || '';
    document.getElementById('edit-exp-location').value = expense.purchase_location || '';
    document.getElementById('edit-exp-yt').value = expense.youtube_link || '';
    
    document.getElementById('edit-expense-modal').style.display = 'flex';
}

function closeEditExpenseModal() {
    document.getElementById('edit-expense-modal').style.display = 'none';
}

// --- CURRENCY & EXCHANGE RATE HELPERS ---
function adjustExchangeRate(currency) {
    const rateInput = document.getElementById('fund-rate');
    if (currency === 'AOA') {
        rateInput.value = '1.0000';
        rateInput.disabled = true;
    } else if (currency === 'USD') {
        rateInput.value = '830.0000'; // Média estimada angolana
        rateInput.disabled = false;
    } else if (currency === 'EUR') {
        rateInput.value = '900.0000';
        rateInput.disabled = false;
    }
}
function adjustEditExchangeRate(currency) {
    const rateInput = document.getElementById('edit-fund-rate');
    if (currency === 'AOA') {
        rateInput.value = '1.0000';
        rateInput.disabled = true;
    } else if (currency === 'USD') {
        rateInput.value = '830.0000';
        rateInput.disabled = false;
    } else if (currency === 'EUR') {
        rateInput.value = '900.0000';
        rateInput.disabled = false;
    }
}

// --- AJAX SUBMISSIONS ---
async function submitAddFunds() {
    const btn = document.getElementById('add-funds-btn');
    const amount = document.getElementById('fund-amount').value;
    const currency = document.getElementById('fund-currency').value;
    const exchange_rate = document.getElementById('fund-rate').value;
    const description = document.getElementById('fund-desc').value.trim();

    App.setLoading(btn, true);

    try {
        await App.post('/api/funds/add', {
            project_id: <?php echo $projectId; ?>,
            amount,
            currency,
            exchange_rate,
            description
        });
        App.showToast('Entrada de capital registada!', 'success');
        localStorage.setItem('active_financial_tab', 'funds');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao adicionar fundos.', 'danger');
        App.setLoading(btn, false);
    }
}

async function submitEditFund() {
    const btn = document.getElementById('edit-funds-btn');
    const id = document.getElementById('edit-fund-id').value;
    const amount = document.getElementById('edit-fund-amount').value;
    const currency = document.getElementById('edit-fund-currency').value;
    const exchange_rate = document.getElementById('edit-fund-rate').value;
    const description = document.getElementById('edit-fund-desc').value.trim();

    App.setLoading(btn, true);

    try {
        await App.post('/api/funds/update', {
            id,
            amount,
            currency,
            exchange_rate,
            description
        });
        App.showToast('Aporte financeiro atualizado com sucesso!', 'success');
        localStorage.setItem('active_financial_tab', 'funds');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao atualizar aporte financeiro.', 'danger');
        App.setLoading(btn, false);
    }
}

async function deleteFund(id) {
    if (!confirm('Tem a certeza de que deseja eliminar este aporte financeiro?')) {
        return;
    }

    try {
        await App.post('/api/funds/delete', { id });
        App.showToast('Aporte financeiro eliminado com sucesso!', 'success');
        localStorage.setItem('active_financial_tab', 'funds');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao eliminar aporte financeiro.', 'danger');
    }
}

// --- WHATSAPP SIMULATED PUSH NOTIFICATION & TONE SYNTHESIS ---
function triggerWhatsAppNotification(phase, percent, amount) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        
        // Primeiro Beep do WhatsApp (880 Hz)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, ctx.currentTime);
        gain1.gain.setValueAtTime(0, ctx.currentTime);
        gain1.gain.linearRampToValueAtTime(0.12, ctx.currentTime + 0.04);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        
        // Segundo Beep do WhatsApp (1046.50 Hz - um pouco mais agudo, espaçado)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(1046.50, ctx.currentTime + 0.1);
        gain2.gain.setValueAtTime(0, ctx.currentTime + 0.1);
        gain2.gain.linearRampToValueAtTime(0.12, ctx.currentTime + 0.14);
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        
        osc1.start(ctx.currentTime);
        osc1.stop(ctx.currentTime + 0.35);
        
        osc2.start(ctx.currentTime + 0.1);
        osc2.stop(ctx.currentTime + 0.5);
    } catch (e) {
        console.error('WhatsApp synthesis sound failed:', e);
    }

    // Criar elemento da notificação
    const toast = document.createElement('div');
    toast.className = 'whatsapp-toast shake';
    
    const formattedAmount = new Intl.NumberFormat('pt-AO', { minimumFractionDigits: 2 }).format(amount) + ' Kz';
    
    toast.innerHTML = `
        <div class="whatsapp-header">
            <div class="whatsapp-brand">
                <i data-lucide="message-square" style="width: 14px; height: 14px; color: #25D366; fill: #25D366;"></i>
                <span>WHATSAPP</span>
            </div>
            <span class="whatsapp-time">agora</span>
        </div>
        <div class="whatsapp-body">
            <div class="whatsapp-avatar">
                <i data-lucide="hard-hat" style="width: 18px; height: 18px; color: white;"></i>
            </div>
            <div class="whatsapp-message">
                <h5 class="whatsapp-title">Constrói Já • Obras</h5>
                <p class="whatsapp-text">⚠️ <strong>Alerta de Desvio!</strong> O orçamento da fase <strong>"${phase}"</strong> foi ultrapassado em <strong>${percent}%</strong> (Excesso de <strong>${formattedAmount}</strong>).</p>
            </div>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    if (window.lucide) {
        window.lucide.createIcons();
    }
    
    if (navigator.vibrate) {
        navigator.vibrate([100, 50, 100]);
    }
    
    setTimeout(() => {
        toast.style.animation = 'whatsappSlideOut 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
        setTimeout(() => toast.remove(), 500);
    }, 4500);
}

async function submitAddExpense() {
    const btn = document.getElementById('add-expense-btn');
    const name = document.getElementById('exp-name').value.trim();
    const type = document.getElementById('exp-type').value;
    const price = document.getElementById('exp-price').value;
    const quantity = document.getElementById('exp-qty').value;
    const unit = document.getElementById('exp-unit').value.trim();
    const phase = document.getElementById('exp-phase').value;
    const purchase_date = document.getElementById('exp-date').value;
    const supplier = document.getElementById('exp-supplier').value.trim();
    const purchase_location = document.getElementById('exp-location').value.trim();
    const youtube_link = document.getElementById('exp-yt').value.trim();
    
    const photoFile = document.getElementById('exp-photo').files[0];
    const receiptFile = document.getElementById('exp-receipt').files[0];

    App.setLoading(btn, true);

    const formData = new FormData();
    formData.append('project_id', <?php echo $projectId; ?>);
    formData.append('name', name);
    formData.append('type', type);
    formData.append('price', price);
    formData.append('quantity', quantity);
    formData.append('unit', unit);
    formData.append('phase', phase);
    formData.append('purchase_date', purchase_date);
    formData.append('supplier', supplier);
    formData.append('purchase_location', purchase_location);
    formData.append('youtube_link', youtube_link);

    if (photoFile) formData.append('photo', photoFile);
    if (receiptFile) formData.append('receipt', receiptFile);

    try {
        await App.upload('/api/expenses/create', formData);
        
        // --- VERIFICAÇÃO EM REAL-TIME PARA ALERTA DE ORÇAMENTO WHATSAPP ---
        const planned = parseFloat(window.FINANCIAL_METRICS.phasePlanned[phase]) || 0;
        const spent = parseFloat(window.FINANCIAL_METRICS.phaseSpent[phase]) || 0;
        const newCost = parseFloat(price) * parseFloat(quantity);
        const newSpent = spent + newCost;
        
        if (planned > 0 && newSpent > planned) {
            const excessAmount = newSpent - planned;
            const excessPercent = Math.round((excessAmount / planned) * 100);
            
            // Dispara notificação push móvel com som e haptic vibration simulados
            triggerWhatsAppNotification(phase, excessPercent, excessAmount);
            
            // Recarrega de forma controlada após 5 segundos para o utilizador ver o alerta
            setTimeout(() => {
                window.location.reload();
            }, 5000);
        } else {
            App.showToast('Lançamento efetuado com sucesso!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao lançar despesa.', 'danger');
        App.setLoading(btn, false);
    }
}

async function submitEditExpense() {
    const btn = document.getElementById('edit-expense-btn-submit');
    const expense_id = document.getElementById('edit-exp-id').value;
    const name = document.getElementById('edit-exp-name').value.trim();
    const type = document.getElementById('edit-exp-type').value;
    const price = document.getElementById('edit-exp-price').value;
    const quantity = document.getElementById('edit-exp-qty').value;
    const unit = document.getElementById('edit-exp-unit').value.trim();
    const phase = document.getElementById('edit-exp-phase').value;
    const purchase_date = document.getElementById('edit-exp-date').value;
    const supplier = document.getElementById('edit-exp-supplier').value.trim();
    const purchase_location = document.getElementById('edit-exp-location').value.trim();
    const youtube_link = document.getElementById('edit-exp-yt').value.trim();

    const photoFile = document.getElementById('edit-exp-photo').files[0];
    const receiptFile = document.getElementById('edit-exp-receipt').files[0];

    App.setLoading(btn, true);

    const formData = new FormData();
    formData.append('expense_id', expense_id);
    formData.append('name', name);
    formData.append('type', type);
    formData.append('price', price);
    formData.append('quantity', quantity);
    formData.append('unit', unit);
    formData.append('phase', phase);
    formData.append('purchase_date', purchase_date);
    formData.append('supplier', supplier);
    formData.append('purchase_location', purchase_location);
    formData.append('youtube_link', youtube_link);

    if (photoFile) formData.append('photo', photoFile);
    if (receiptFile) formData.append('receipt', receiptFile);

    try {
        await App.upload('/api/expenses/update', formData);
        App.showToast('Lançamento atualizado com sucesso!', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao editar despesa.', 'danger');
        App.setLoading(btn, false);
    }
}

// --- PRÉ-ORÇAMENTO / PLANEAMENTO ACTIONS ---
async function submitAddPreItem() {
    const btn = document.getElementById('add-pre-btn');
    const name = document.getElementById('pre-name').value.trim();
    const price = document.getElementById('pre-price').value;
    const quantity = document.getElementById('pre-qty').value;
    const unit = document.getElementById('pre-unit').value.trim();
    const phase = document.getElementById('pre-phase').value;
    
    App.setLoading(btn, true);
    
    try {
        await App.post('/api/pre-budget/add', {
            project_id: <?php echo $projectId; ?>,
            name,
            price,
            quantity,
            unit,
            phase
        });
        App.showToast('Item planeado adicionado com sucesso!', 'success');
        localStorage.setItem('active_financial_tab', 'prebudget');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao adicionar item de planeamento.', 'danger');
        App.setLoading(btn, false);
    }
}

function openEditPreItemModal(item) {
    document.getElementById('edit-pre-id').value = item.id;
    document.getElementById('edit-pre-name').value = item.name || '';
    document.getElementById('edit-pre-price').value = item.price || 0;
    document.getElementById('edit-pre-qty').value = item.quantity || 1;
    document.getElementById('edit-pre-unit').value = item.unit || 'un';
    document.getElementById('edit-pre-phase').value = item.phase || '';
    
    const modal = document.getElementById('edit-pre-modal');
    if (modal) modal.style.display = 'flex';
}

function closeEditPreItemModal() {
    const modal = document.getElementById('edit-pre-modal');
    if (modal) modal.style.display = 'none';
}

async function submitEditPreItem() {
    const btn = document.getElementById('edit-pre-btn');
    const id = document.getElementById('edit-pre-id').value;
    const name = document.getElementById('edit-pre-name').value.trim();
    const price = document.getElementById('edit-pre-price').value;
    const quantity = document.getElementById('edit-pre-qty').value;
    const unit = document.getElementById('edit-pre-unit').value.trim();
    const phase = document.getElementById('edit-pre-phase').value;
    
    App.setLoading(btn, true);
    
    try {
        await App.post('/api/pre-budget/update', {
            id,
            name,
            price,
            quantity,
            unit,
            phase
        });
        App.showToast('Item de planeamento atualizado com sucesso!', 'success');
        localStorage.setItem('active_financial_tab', 'prebudget');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao atualizar item de planeamento.', 'danger');
        App.setLoading(btn, false);
    }
}

async function deletePreItem(id) {
    if (!confirm('Tem a certeza que deseja remover este item do planeamento?')) {
        return;
    }
    try {
        await App.post('/api/pre-budget/delete', { id });
        App.showToast('Item de planeamento removido!', 'success');
        localStorage.setItem('active_financial_tab', 'prebudget');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao remover item.', 'danger');
    }
}

function openImportPreModal() {
    document.getElementById('import-pre-modal').style.display = 'flex';
}

function closeImportPreModal() {
    document.getElementById('import-pre-modal').style.display = 'none';
    document.getElementById('import-pre-form').reset();
}

async function submitImportPre() {
    const btn = document.getElementById('import-pre-btn');
    const fileInput = document.getElementById('import-csv-file');
    const file = fileInput.files[0];
    
    if (!file) return;
    
    App.setLoading(btn, true);
    
    const formData = new FormData();
    formData.append('project_id', <?php echo $projectId; ?>);
    formData.append('file', file);
    
    try {
        const response = await App.upload('/api/pre-budget/import', formData);
        App.showToast(response.message || 'Importação efetuada com sucesso!', 'success');
        localStorage.setItem('active_financial_tab', 'prebudget');
        setTimeout(() => window.location.reload(), 1200);
    } catch (e) {
        App.showToast(e.message || 'Erro ao importar planeamento.', 'danger');
        App.setLoading(btn, false);
    }
}

function preFillExpense(name, price, qty, unit, phase) {
    document.getElementById('exp-name').value = name;
    document.getElementById('exp-type').value = 'material';
    document.getElementById('exp-price').value = price;
    document.getElementById('exp-qty').value = qty;
    document.getElementById('exp-unit').value = unit;
    document.getElementById('exp-phase').value = phase;
    openAddExpenseModal();
}

// --- INITIALIZE CHARTS ---
document.addEventListener('DOMContentLoaded', () => {
    // 1. Budget consumption doughnut
    App.Charts.renderBudget('budget-chart', <?php echo $totalSpent; ?>, <?php echo $totalPlanned; ?>);

    // 2. Spent by type donut
    const typeData = <?php echo json_encode($typeSpent); ?>;
    const translatedTypeData = {};
    const translations = <?php echo json_encode($typeTranslations); ?>;
    
    for (const key in typeData) {
        translatedTypeData[translations[key] || key] = typeData[key];
    }
    App.Charts.renderByType('type-chart', translatedTypeData);

    // 3. Spent by phase bar
    const phaseData = <?php echo json_encode($phaseSpent); ?>;
    App.Charts.renderByPhase('phase-chart', phaseData);
    
    // Iniciar com câmbio adequado
    adjustExchangeRate('AOA');

    // Restaurar tab ativa se existir
    const activeTab = localStorage.getItem('active_financial_tab');
    if (activeTab) {
        switchTab(activeTab);
        localStorage.removeItem('active_financial_tab');
    }
});
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
