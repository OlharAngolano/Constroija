<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Security.php';

$token = trim((string)input('token', ''));
$db = db();

$isValid = false;
$project = null;
$expired = false;

$preBudgets = [];
$phasePlanned = [];
$phasesArray = [];

try {
    if ($token !== '') {
        // Obter link público e dados do projeto e dono
        $linkData = $db->fetch(
            "SELECT pl.expires_at, p.*, pr.name as owner_name, pr.currency as owner_currency
             FROM public_links pl
             JOIN projects p ON pl.project_id = p.id
             JOIN profiles pr ON p.user_id = pr.id
             WHERE pl.token = ?",
            [$token]
        );

        if ($linkData) {
            if (strtotime($linkData['expires_at']) >= time()) {
                $isValid = true;
                $project = $linkData;
                
                $projectId = (int)$project['id'];
                $currency = $project['owner_currency'] ?? 'AOA';

                // Buscar total planeado do pré-orçamento se o orçamento do projeto for 0
                $budget = (float)$project['budget'];
                if ($budget <= 0.00) {
                    $totalPlanned = (float)$db->fetch(
                        "SELECT SUM(price * quantity) as total FROM pre_budgets WHERE project_id = ?",
                        [$projectId]
                    )['total'] ?? 0.00;
                    $project['budget'] = $totalPlanned;
                }

                // Buscar fundos totais
                $fundsSum = $db->fetch(
                    "SELECT SUM(amount * exchange_rate) as total FROM project_funds WHERE project_id = ?",
                    [$projectId]
                )['total'] ?? 0.00;

                // Buscar total gasto (despesas ativas)
                $spentSum = $db->fetch(
                    "SELECT SUM(price * quantity) as total FROM expenses WHERE project_id = ? AND deleted_at IS NULL",
                    [$projectId]
                )['total'] ?? 0.00;

                // Despesas por tipo (para gráfico de pizza/donut)
                $expensesByType = $db->fetchAll(
                    "SELECT type, SUM(price * quantity) as total 
                     FROM expenses 
                     WHERE project_id = ? AND deleted_at IS NULL 
                     GROUP BY type",
                    [$projectId]
                );

                // Despesas por fase (para gráfico de barras)
                $expensesByPhase = $db->fetchAll(
                    "SELECT phase, SUM(price * quantity) as total 
                     FROM expenses 
                     WHERE project_id = ? AND deleted_at IS NULL 
                     GROUP BY phase",
                    [$projectId]
                );

                // Listagem de despesas ativas
                $expenses = $db->fetchAll(
                    "SELECT * FROM expenses WHERE project_id = ? AND deleted_at IS NULL ORDER BY purchase_date DESC",
                    [$projectId]
                );

                // Listagem de planeamento do pré-orçamento (Novo - Relatório Unificado)
                $preBudgets = $db->fetchAll(
                    "SELECT pb.*, pr.name AS registrant_name 
                     FROM pre_budgets pb 
                     JOIN profiles pr ON pb.user_id = pr.id 
                     WHERE pb.project_id = ? 
                     ORDER BY pb.created_at DESC",
                    [$projectId]
                );

                // Fases decodificadas
                if (!empty($project['phases'])) {
                    $phasesArray = is_string($project['phases']) ? (json_decode($project['phases'], true) ?? []) : $project['phases'];
                }
                if (empty($phasesArray)) {
                    $phasesArray = ["Fundação", "Estrutura", "Alvenaria", "Cobertura", "Acabamentos"];
                }

                // Buscar totais planeados por fase para o orçamento comparativo (Novo)
                $plannedByPhase = $db->fetchAll(
                    "SELECT phase, SUM(price * quantity) as total 
                     FROM pre_budgets 
                     WHERE project_id = ? 
                     GROUP BY phase",
                    [$projectId]
                );
                foreach ($phasesArray as $ph) {
                    $phasePlanned[$ph] = 0.00;
                }
                foreach ($plannedByPhase as $pb) {
                    $phasePlanned[$pb['phase']] = (float)$pb['total'];
                }

            } else {
                $expired = true;
            }
        }
    }
} catch (PDOException $e) {
    die("Erro ao processar relatório público: " . $e->getMessage());
}

$title = $isValid ? "Relatório Unificado: " . sanitize($project['title']) . " — Constrói Já" : "Relatório Inválido";
require_once __DIR__ . '/../templates/header.php';
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    /* Elemento de cabeçalho corporativo oculto na tela */
    .print-header {
        display: none;
    }

    /* Tabs customizadas */
    .report-tabs-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 25px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 15px;
        align-items: center;
    }

    .report-tab-btn {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        border-radius: var(--radius-md);
        font-size: 13.5px;
        font-weight: 600;
        padding: 10px 18px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .report-tab-btn:hover {
        background: rgba(255, 255, 255, 0.07);
        color: var(--text-primary);
        border-color: var(--text-muted);
    }

    .report-tab-btn.active {
        background: var(--accent-primary);
        color: #ffffff !important;
        border-color: var(--accent-primary);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.25);
    }

    /* ==================== EXCEL PREMIUM DESIGN SYSTEM ==================== */
    .excel-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
        margin: 10px 0;
        background: rgba(255, 255, 255, 0.01);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .excel-table th {
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        color: #f1f5f9;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 12px 14px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        text-align: left;
    }

    .excel-table td {
        padding: 12px 14px;
        border: 1px solid rgba(255, 255, 255, 0.06);
        color: var(--text-secondary);
        vertical-align: middle;
        transition: background 0.15s ease;
    }

    .excel-table tbody tr:nth-child(odd) {
        background: rgba(255, 255, 255, 0.01);
    }

    .excel-table tbody tr:nth-child(even) {
        background: rgba(255, 255, 255, 0.03);
    }

    .excel-table tbody tr:hover {
        background: rgba(249, 115, 22, 0.06);
    }

    /* Alinhamentos standard de Excel */
    .excel-text-left {
        text-align: left !important;
    }

    .excel-text-center {
        text-align: center !important;
    }

    .excel-text-right {
        text-align: right !important;
        font-family: 'Outfit', monospace !important; /* Alinhamento vertical numérico */
        font-weight: 600;
    }

    /* Linha de Total de Contabilidade */
    .excel-total-row {
        background: rgba(255, 255, 255, 0.04) !important;
        font-weight: 800 !important;
    }

    .excel-total-row td {
        color: var(--text-primary) !important;
        font-size: 13.5px !important;
        border-top: 2px solid var(--accent-primary) !important;
        border-bottom: 3px double var(--accent-primary) !important; /* Linha dupla de contabilidade */
    }

    .excel-deviation-positive {
        color: #22c55e !important;
        font-weight: 700;
        background: rgba(34, 197, 94, 0.04);
    }

    .excel-deviation-negative {
        color: #ef4444 !important;
        font-weight: 700;
        background: rgba(239, 68, 68, 0.04);
    }

    .excel-badge-category {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.03);
        color: var(--text-secondary);
    }

    /* Print media rules para descarregamento de PDF Unificado de Luxo */
    @media print {
        .no-print, 
        .toast-container,
        header,
        footer,
        .report-tabs-bar,
        .btn,
        .flash-message {
            display: none !important;
        }

        /* Mostrar todos os conteúdos consecutivamente para formar um único PDF */
        .tab-content {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            page-break-after: auto !important;
        }

        body {
            background: #ffffff !important;
            color: #000000 !important;
            padding: 0 !important;
            margin: 0 !important;
            font-size: 11px !important;
        }

        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000000;
            padding-bottom: 12px;
        }

        .print-header h1 {
            font-size: 22px !important;
            color: #000000 !important;
            font-weight: 800 !important;
            margin-bottom: 4px !important;
        }

        .card {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #000000 !important;
            box-shadow: none !important;
            padding: 20px !important;
            margin-bottom: 24px !important;
            page-break-inside: avoid !important;
            border-radius: 8px !important;
        }

        h2, h3, h4, th, td, span, p, strong, small {
            color: #000000 !important;
        }

        /* Overrides de tabela Excel para impressão limpa em A4 */
        .excel-table {
            border: 1px solid #94a3b8 !important;
            background: #ffffff !important;
            box-shadow: none !important;
        }

        .excel-table th {
            background: #e2e8f0 !important;
            color: #000000 !important;
            border: 1px solid #94a3b8 !important;
            font-weight: 800 !important;
        }

        .excel-table td {
            border: 1px solid #cbd5e1 !important;
            color: #000000 !important;
        }

        .excel-table tbody tr:nth-child(even) {
            background: #f8fafc !important;
        }

        .excel-table tbody tr:nth-child(odd) {
            background: #ffffff !important;
        }

        .excel-total-row td {
            border-top: 2px solid #000000 !important;
            border-bottom: 3px double #000000 !important;
            color: #000000 !important;
            background: #f1f5f9 !important;
        }
        
        .excel-deviation-green {
            background: none !important;
            color: #15803d !important;
        }

        .excel-deviation-red {
            background: none !important;
            color: #b91c1c !important;
        }

        .excel-badge-category {
            background: none !important;
            border: 1px solid #94a3b8 !important;
            color: #000000 !important;
        }

        /* Otimização de quebra de páginas nos gráficos e extratos */
        .chart-print-container {
            page-break-inside: avoid !important;
        }
    }
</style>

<div style="max-width: 1150px; margin: <?php echo $isLoggedIn ? '0 auto' : '40px auto'; ?>; padding: <?php echo $isLoggedIn ? '0' : '0 20px'; ?>; display: flex; flex-direction: column; gap: 30px;">

    <!-- Branding Constrói Já para utilizadores não autenticados -->
    <?php if (!$isLoggedIn): ?>
        <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 10px;" class="no-print">
            <i data-lucide="hard-hat" style="color: var(--accent-primary); width: 32px; height: 32px;"></i>
            <span style="font-size: 24px; font-weight: 800; letter-spacing: 0.5px;" class="logo-text">Constrói Já</span>
            <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--accent-secondary); border: 1px solid var(--accent-secondary); font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 700;">Relatório Externo</span>
        </div>
    <?php endif; ?>

    <?php if (!$isValid): ?>
        <!-- CARD ERRO DE TOKEN -->
        <div class="card" style="text-align: center; padding: 60px 20px; max-width: 600px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
            <div style="background: rgba(239,68,68,0.1); color: #ef4444; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                <i data-lucide="shield-alert" style="width: 32px; height: 32px;"></i>
            </div>
            
            <div>
                <?php if ($expired): ?>
                    <h2 style="font-weight: 800; color: var(--text-primary);">Link de Partilha Expirado</h2>
                    <p style="color: var(--text-secondary); margin-top: 8px; font-size: 14px; line-height: 1.6;">
                        Este link público expirou. Os tokens de partilha financeira do Constrói Já são válidos por 7 dias por motivos de segurança corporativa. Contacte o administrador da obra para gerar um novo link.
                    </p>
                <?php else: ?>
                    <h2 style="font-weight: 800; color: var(--text-primary);">Relatório não Encontrado</h2>
                    <p style="color: var(--text-secondary); margin-top: 8px; font-size: 14px; line-height: 1.6;">
                        O token de acesso financeiro fornecido é inválido, inexistente ou foi revogado pelo proprietário do projeto de construção.
                    </p>
                <?php endif; ?>
            </div>
            
            <div style="border-top: 1px solid var(--border-color); padding-top: 20px; margin-top: 10px;">
                <a href="/" class="btn btn-primary" style="margin: 0 auto; width: fit-content; font-size: 13px;">
                    Ir para Constrói Já
                </a>
            </div>
        </div>

    <?php else: ?>
        
        <!-- CABEÇALHO PARA IMPRESSÃO PDF -->
        <div class="print-header">
            <h1>CONSTRÓI JÁ — RELATÓRIO DE OBRA UNIFICADO</h1>
            <p>Exportado em: <?php echo date('d/m/Y H:i'); ?> | Relatório gerado via token de partilha seguro</p>
        </div>

        <!-- CABEÇALHO DO RELATÓRIO DO PROJETO -->
        <div class="card" style="position: relative; overflow: hidden; padding: 0;">
            <div style="height: 100px; background: linear-gradient(135deg, rgba(249,115,22,0.1) 0%, rgba(59,130,246,0.1) 100%); border-bottom: 1px solid var(--border-color);" class="no-print"></div>
            <div style="padding: 24px; display: flex; flex-direction: column; gap: 16px; margin-top: -50px;" class="report-header-padded">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px;">
                    <div>
                        <span class="badge" style="background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid #22c55e; font-size: 10px; text-transform: uppercase; font-weight: 700; margin-bottom: 8px; display: inline-block;">Visualização Autorizada</span>
                        <h2 style="font-weight: 800; font-size: 24px; color: var(--text-primary);"><?php echo sanitize($project['title']); ?></h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">
                            Obra gerida por: <strong><?php echo sanitize($project['owner_name']); ?></strong>
                        </p>
                    </div>

                    <div style="text-align: right; font-size: 13px; color: var(--text-muted);">
                        <div>Link válido até:</div>
                        <strong style="color: var(--text-primary);"><?php echo date('d/m/Y H:i', strtotime($project['expires_at'])); ?></strong>
                    </div>
                </div>

                <?php if (!empty($project['description'])): ?>
                    <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;"><?php echo sanitize($project['description']); ?></p>
                <?php endif; ?>

                <div style="display: flex; flex-wrap: wrap; gap: 20px; font-size: 13px; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <?php if (!empty($project['location'])): ?>
                        <span style="display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="map-pin" style="width: 16px; height: 16px; color: var(--accent-primary);"></i>
                            <?php echo sanitize($project['location']); ?>
                        </span>
                    <?php endif; ?>
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="calendar" style="width: 16px; height: 16px; color: var(--accent-secondary);"></i>
                        Início: <?php echo $project['start_date'] ? date('d/m/Y', strtotime($project['start_date'])) : 'Não agendado'; ?>
                    </span>
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="check-square" style="width: 16px; height: 16px; color: #22c55e;"></i>
                        Estado: <strong><?php echo strtoupper($project['status']); ?></strong>
                    </span>
                </div>
            </div>
        </div>

        <!-- BARRA DE SELEÇÃO DE TABS E EXPORTAÇÃO PDF -->
        <div class="report-tabs-bar no-print">
            <button class="report-tab-btn active" id="btn-tab-dashboard" onclick="switchReportTab('dashboard')">
                <i data-lucide="layout-dashboard" style="width: 15px; height: 15px;"></i>
                Painel Financeiro
            </button>
            <button class="report-tab-btn" id="btn-tab-budget" onclick="switchReportTab('budget')">
                <i data-lucide="calculator" style="width: 15px; height: 15px;"></i>
                Orçamento de Obra
            </button>
            <button class="report-tab-btn" id="btn-tab-prebudget" onclick="switchReportTab('prebudget')">
                <i data-lucide="clipboard-list" style="width: 15px; height: 15px;"></i>
                Pré-Orçamento Planeado
            </button>

            <button class="btn btn-primary" onclick="downloadUnifiedPDF()" style="margin-left: auto; background: var(--accent-primary); border: none; font-weight: 700; height: 40px; padding: 0 16px; display: inline-flex; align-items: center; gap: 8px; border-radius: var(--radius-md); box-shadow: 0 4px 15px rgba(249,115,22,0.25);">
                <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
                Baixar PDF Unificado
            </button>
        </div>

        <!-- ==================== TAB 1: PAINEL FINANCEIRO (DASHBOARD) ==================== -->
        <div id="tab-content-dashboard" class="tab-content">
            <div style="display: flex; flex-direction: column; gap: 30px;">
                
                <!-- INDICADORES / KPI CARDS -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
                    <!-- Orçamento Inicial -->
                    <div class="card" style="display: flex; flex-direction: column; gap: 6px;">
                        <span style="color: var(--text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase;">Orçamento Planeado</span>
                        <h3 style="font-size: 22px; font-weight: 800; color: var(--text-primary);"><?php echo format_currency((float)$project['budget'], $currency); ?></h3>
                        <span style="font-size: 11px; color: var(--text-muted);">Teto limite financeiro</span>
                    </div>

                    <!-- Fundos Garantidos -->
                    <div class="card" style="display: flex; flex-direction: column; gap: 6px;">
                        <span style="color: var(--text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase;">Fundos Alocados</span>
                        <h3 style="font-size: 22px; font-weight: 800; color: #3b82f6;"><?php echo format_currency((float)$fundsSum, $currency); ?></h3>
                        <span style="font-size: 11px; color: var(--text-muted);">Financiamento assegurado</span>
                    </div>

                    <!-- Total Gasto -->
                    <div class="card" style="display: flex; flex-direction: column; gap: 6px;">
                        <span style="color: var(--text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase;">Total Gasto em Obra</span>
                        <h3 style="font-size: 22px; font-weight: 800; color: var(--accent-primary);"><?php echo format_currency((float)$spentSum, $currency); ?></h3>
                        <?php 
                        $pctBudget = $project['budget'] > 0 ? ((float)$spentSum / (float)$project['budget']) * 100 : 0;
                        $colorPct = $pctBudget > 90 ? '#ef4444' : ($pctBudget > 75 ? 'var(--accent-primary)' : '#22c55e');
                        ?>
                        <span style="font-size: 11px; color: <?php echo $colorPct; ?>; font-weight: 600;"><?php echo number_format($pctBudget, 1); ?>% do orçamento consumido</span>
                    </div>

                    <!-- Saldo Disponível -->
                    <div class="card" style="display: flex; flex-direction: column; gap: 6px;">
                        <span style="color: var(--text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase;">Saldo Atual da Obra</span>
                        <?php $balance = (float)$fundsSum - (float)$spentSum; ?>
                        <h3 style="font-size: 22px; font-weight: 800; color: <?php echo $balance >= 0 ? '#22c55e' : '#ef4444'; ?>;">
                            <?php echo format_currency($balance, $currency); ?>
                        </h3>
                        <span style="font-size: 11px; color: var(--text-muted);">Fundos menos despesas</span>
                    </div>
                </div>

                <!-- GRÁFICOS FINANCEIROS (Chart.js) -->
                <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px; flex-wrap: wrap;" class="chart-print-container">
                    <!-- Gráfico de Donut: Despesa por Categoria -->
                    <div class="card" style="display: flex; flex-direction: column; gap: 16px; min-height: 350px;">
                        <h3 style="font-size: 15px; font-weight: 700; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                            Distribuição por Categoria
                        </h3>
                        <div style="flex-grow: 1; position: relative; max-height: 250px; display: flex; align-items: center; justify-content: center;">
                            <canvas id="publicTypeChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Barras: Despesa por Fase -->
                    <div class="card" style="display: flex; flex-direction: column; gap: 16px; min-height: 350px;">
                        <h3 style="font-size: 15px; font-weight: 700; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                            Consumo Financeiro por Fase da Obra
                        </h3>
                        <div style="flex-grow: 1; position: relative; max-height: 250px; display: flex; align-items: center; justify-content: center;">
                            <canvas id="publicPhaseChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- LISTAGEM DETALHADA DE DESPESAS DA OBRA (AUDITÁVEL E TRANSPARENTE) -->
                <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                        <h3 style="font-size: 16px; font-weight: 800; color: var(--text-primary); display:flex; align-items:center; gap:8px;">
                            <i data-lucide="receipt" style="color: var(--accent-primary); width:18px; height:18px;"></i>
                            Extrato de Despesas e Comprovativos
                        </h3>
                        <p style="color:var(--text-muted); font-size:12px; margin-top:2px;">Registo fiscal auditável e transparente das aquisições efetuadas para a obra.</p>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="excel-table">
                            <thead>
                                <tr>
                                    <th class="excel-text-left">Artigo/Serviço</th>
                                    <th class="excel-text-left">Fase</th>
                                    <th class="excel-text-center">Categoria</th>
                                    <th class="excel-text-center">Data de Compra</th>
                                    <th class="excel-text-right">Preço Unitário</th>
                                    <th class="excel-text-center">Quantidade</th>
                                    <th class="excel-text-right">Total Gasto</th>
                                    <th class="excel-text-center no-print">Recibo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="8" class="excel-text-center" style="padding: 24px; color: var(--text-muted);">
                                            Nenhuma despesa ou encargo financeiro lançado nesta obra de momento.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($expenses as $exp): ?>
                                        <tr>
                                            <td data-label="Artigo/Serviço" class="excel-text-left" style="font-weight: 700; color: var(--text-primary);">
                                                <?php echo sanitize($exp['name']); ?>
                                                <?php if (!empty($exp['supplier'])): ?>
                                                    <div style="font-size:10px; color:var(--text-muted); font-weight:normal; margin-top:2px;">Fornecedor: <?php echo sanitize($exp['supplier']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Fase" class="excel-text-left"><?php echo sanitize($exp['phase']); ?></td>
                                            <td data-label="Categoria" class="excel-text-center">
                                                <span class="excel-badge-category">
                                                    <?php echo strtoupper($exp['type']); ?>
                                                </span>
                                            </td>
                                            <td data-label="Data de Compra" class="excel-text-center" style="color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($exp['purchase_date'])); ?></td>
                                            <td data-label="Preço Unitário" class="excel-text-right"><?php echo format_currency((float)$exp['price'], $currency); ?></td>
                                            <td data-label="Quant." class="excel-text-center"><?php echo (float)$exp['quantity'] . ' ' . sanitize($exp['unit']); ?></td>
                                            <td data-label="Total Gasto" class="excel-text-right" style="font-weight: 700; color: var(--text-primary);"><?php echo format_currency((float)$exp['price'] * (float)$exp['quantity'], $currency); ?></td>
                                            <td data-label="Recibo" class="excel-text-center no-print">
                                                <?php if (!empty($exp['receipt_url'])): ?>
                                                    <a href="<?php echo APP_URL . '/' . $exp['receipt_url']; ?>" target="_blank" class="btn btn-secondary" style="font-size:11px; padding: 4px 8px; width: fit-content; display: inline-flex; align-items: center; gap: 4px;">
                                                        <i data-lucide="eye" style="width: 12px; height: 12px;"></i>
                                                        Anexo
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted); font-size:11px; font-style:italic;">Sem anexo</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ==================== TAB 2: ORÇAMENTO COMPARATIVO POR FASE ==================== -->
        <div id="tab-content-budget" class="tab-content" style="display: none;">
            <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <h3 style="font-size: 16px; font-weight: 800; color: var(--text-primary); display:flex; align-items:center; gap:8px;">
                        <i data-lucide="calculator" style="color: var(--accent-secondary); width:18px; height:18px;"></i>
                        Orçamento de Obra por Fase (Planeado vs. Real)
                    </h3>
                    <p style="color:var(--text-muted); font-size:12px; margin-top:2px;">Comparação analítica das estimativas iniciais com os custos reais incorridos por cada fase da construção.</p>
                </div>

                <div style="overflow-x: auto;">
                    <table class="excel-table">
                        <thead>
                            <tr>
                                <th class="excel-text-left">Fase da Obra</th>
                                <th class="excel-text-right">Orçamento Planeado</th>
                                <th class="excel-text-right">Custo Real Gasto</th>
                                <th class="excel-text-right">Desvio Financeiro</th>
                                <th class="excel-text-center">Estado de Desvio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalPlannedAll = 0.00;
                            $totalSpentAll = 0.00;
                            foreach ($phasesArray as $ph): 
                                $plannedVal = $phasePlanned[$ph] ?? 0.00;
                                
                                // Para o real gasto
                                $spentVal = 0.00;
                                foreach ($expensesByPhase as $ebp) {
                                    if (trim(mb_strtolower($ebp['phase'])) === trim(mb_strtolower($ph))) {
                                        $spentVal = (float)$ebp['total'];
                                        break;
                                    }
                                }
                                
                                $deviationVal = $plannedVal - $spentVal;
                                $devPercent = $plannedVal > 0 ? ($spentVal / $plannedVal) * 100 : 0;
                                
                                $totalPlannedAll += $plannedVal;
                                $totalSpentAll += $spentVal;
                                
                                $stateText = 'Dentro do limite';
                                $stateClass = 'excel-deviation-positive';
                                if ($plannedVal > 0 && $spentVal > $plannedVal) {
                                    $stateText = 'Excedido em ' . round($devPercent - 100) . '%';
                                    $stateClass = 'excel-deviation-negative';
                                } elseif ($plannedVal > 0 && $spentVal > 0) {
                                    $stateText = 'Poupado ' . round(100 - $devPercent) . '%';
                                    $stateClass = 'excel-deviation-positive';
                                } elseif ($spentVal > 0 && $plannedVal <= 0) {
                                    $stateText = 'Não planeado';
                                    $stateClass = 'excel-deviation-negative';
                                }
                            ?>
                                <tr>
                                    <td data-label="Fase da Obra" class="excel-text-left" style="font-weight: 700; color: var(--text-primary);">
                                        <?php echo sanitize($ph); ?>
                                    </td>
                                    <td data-label="Orçamento Planeado" class="excel-text-right">
                                        <?php echo format_currency($plannedVal, $currency); ?>
                                    </td>
                                    <td data-label="Custo Real Gasto" class="excel-text-right" style="color: var(--text-primary);">
                                        <?php echo format_currency($spentVal, $currency); ?>
                                    </td>
                                    <td data-label="Desvio Financeiro" class="excel-text-right <?php echo $deviationVal >= 0 ? 'excel-deviation-positive' : 'excel-deviation-negative'; ?>">
                                        <?php echo ($deviationVal >= 0 ? '+ ' : '- ') . format_currency(abs($deviationVal), $currency); ?>
                                    </td>
                                    <td data-label="Estado de Desvio" class="excel-text-center">
                                        <span class="badge <?php echo $stateClass; ?>" style="border: 1px solid currentColor; font-size: 11px;">
                                            <?php echo $stateText; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="excel-total-row">
                                <td class="excel-text-left">Total Geral</td>
                                <td class="excel-text-right"><?php echo format_currency($totalPlannedAll, $currency); ?></td>
                                <td class="excel-text-right"><?php echo format_currency($totalSpentAll, $currency); ?></td>
                                <?php $totalDev = $totalPlannedAll - $totalSpentAll; ?>
                                <td class="excel-text-right <?php echo $totalDev >= 0 ? 'excel-deviation-positive' : 'excel-deviation-negative'; ?>">
                                    <?php echo ($totalDev >= 0 ? '+ ' : '- ') . format_currency(abs($totalDev), $currency); ?>
                                </td>
                                <td class="excel-text-center">
                                    <?php 
                                    $totalPct = $totalPlannedAll > 0 ? ($totalSpentAll / $totalPlannedAll) * 100 : 0;
                                    echo round($totalPct) . '% Consumido';
                                    ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 3: PRÉ-ORÇAMENTO PLANEADO DETALHADO ==================== -->
        <div id="tab-content-prebudget" class="tab-content" style="display: none;">
            <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <h3 style="font-size: 16px; font-weight: 800; color: var(--text-primary); display:flex; align-items:center; gap:8px;">
                        <i data-lucide="clipboard-list" style="color: var(--accent-primary); width:18px; height:18px;"></i>
                        Planeamento de Pré-Orçamento Detalhado
                    </h3>
                    <p style="color:var(--text-muted); font-size:12px; margin-top:2px;">Lista itemizada dos materiais, mão de obra e serviços planeados para o canteiro de obras.</p>
                </div>

                <div style="overflow-x: auto;">
                    <table class="excel-table">
                        <thead>
                            <tr>
                                <th class="excel-text-left">Item Estimado</th>
                                <th class="excel-text-left">Fase Relacionada</th>
                                <th class="excel-text-right">Preço Unitário</th>
                                <th class="excel-text-center">Quantidade</th>
                                <th class="excel-text-center">Qtd Comprada</th>
                                <th class="excel-text-right">Total Estimado</th>
                                <th class="excel-text-center">Registado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($preBudgets)): ?>
                                <tr>
                                    <td colspan="7" class="excel-text-center" style="padding: 24px; color: var(--text-muted);">
                                        Nenhum planeamento de pré-orçamento lançado nesta obra até ao momento.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($preBudgets as $pbItem): 
                                    $purchasedQty = 0.00;
                                    foreach ($expenses as $exp) {
                                        if (mb_strtolower(trim($exp['name'])) === mb_strtolower(trim($pbItem['name'])) && $exp['phase'] === $pbItem['phase']) {
                                            $purchasedQty += (float)$exp['quantity'];
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td data-label="Item Estimado" class="excel-text-left" style="font-weight: 700; color: var(--text-primary);">
                                            <?php echo sanitize($pbItem['name']); ?>
                                        </td>
                                        <td data-label="Fase Relacionada" class="excel-text-left"><?php echo sanitize($pbItem['phase']); ?></td>
                                        <td data-label="Preço Unitário" class="excel-text-right"><?php echo format_currency((float)$pbItem['price'], $currency); ?></td>
                                        <td data-label="Quantidade" class="excel-text-center"><?php echo (float)$pbItem['quantity'] . ' ' . sanitize($pbItem['unit']); ?></td>
                                        <td data-label="Qtd Comprada" class="excel-text-center" style="font-weight: 600;">
                                            <?php if ($purchasedQty <= 0): ?>
                                                <span style="color:var(--text-muted); font-size:12px;">—</span>
                                            <?php else: ?>
                                                <div style="display:inline-flex; flex-direction:column; gap:4px; align-items:center;">
                                                    <span style="color:var(--text-primary); font-size:13px; font-weight:700;">
                                                        <?php echo $purchasedQty . ' ' . sanitize($pbItem['unit']); ?>
                                                    </span>
                                                    <?php 
                                                    $percent = $pbItem['quantity'] > 0 ? ($purchasedQty / (float)$pbItem['quantity']) * 100 : 0;
                                                    if ($percent >= 100) {
                                                        $badgeBg = 'rgba(16, 185, 129, 0.1)';
                                                        $badgeColor = 'var(--accent-success)';
                                                        $badgeBorder = 'rgba(16, 185, 129, 0.2)';
                                                        $labelText = 'Total';
                                                    } else {
                                                        $badgeBg = 'rgba(249, 115, 22, 0.1)';
                                                        $badgeColor = 'var(--accent-primary)';
                                                        $badgeBorder = 'rgba(249, 115, 22, 0.2)';
                                                        $labelText = number_format($percent, 0) . '%';
                                                    }
                                                    ?>
                                                    <span class="badge" style="background:<?php echo $badgeBg; ?>; color:<?php echo $badgeColor; ?>; border:1px solid <?php echo $badgeBorder; ?>; font-size:10px; padding:2px 8px; border-radius:4px;">
                                                        <?php echo $labelText; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Total Estimado" class="excel-text-right" style="font-weight: 700; color: var(--text-primary);">
                                            <?php echo format_currency((float)$pbItem['price'] * (float)$pbItem['quantity'], $currency); ?>
                                        </td>
                                        <td data-label="Registado por" class="excel-text-center" style="color: var(--text-muted); font-size: 11px;">
                                            <?php echo sanitize($pbItem['registrant_name']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SCRIPT INTERATIVO DE TABS & EXPORTAÇÃO PDF -->
        <script nonce="<?php echo Security::getNonce(); ?>">
        function switchReportTab(tabId) {
            // Esconder todas as tabs
            document.querySelectorAll('.tab-content').forEach(el => {
                el.style.display = 'none';
            });
            
            // Remover classe ativa dos botões de tab
            document.querySelectorAll('.report-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar conteúdo selecionado
            const targetContent = document.getElementById('tab-content-' + tabId);
            if (targetContent) {
                targetContent.style.display = 'block';
            }
            
            // Ativar botão selecionado
            const targetBtn = document.getElementById('btn-tab-' + tabId);
            if (targetBtn) {
                targetBtn.classList.add('active');
            }
        }

        function downloadUnifiedPDF() {
            // Alterar o título temporariamente para que o ficheiro PDF gerado herde o nome da obra formatado
            const originalTitle = document.title;
            const projectTitle = "<?php echo addslashes($project['title']); ?>";
            document.title = "Relatorio_Obra_" + projectTitle.replace(/\s+/g, '_') + "_" + new Date().toISOString().slice(0, 10);
            
            // Disparar o assistente de exportação PDF/impressão nativo
            window.print();
            
            // Repor o título original
            document.title = originalTitle;
        }

        document.addEventListener('DOMContentLoaded', () => {
            // 1. Configurar Gráfico de Pizza/Donut por Categoria
            const typeCtx = document.getElementById('publicTypeChart').getContext('2d');
            const typeData = <?php 
                $typesMap = ['material' => 0.0, 'labor' => 0.0, 'equipment' => 0.0, 'service' => 0.0, 'other' => 0.0];
                foreach ($expensesByType as $ebt) {
                    $typesMap[$ebt['type']] = (float)$ebt['total'];
                }
                echo json_encode(array_values($typesMap)); 
            ?>;

            new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Materiais', 'Mão de Obra', 'Equipamento', 'Serviço', 'Outros'],
                    datasets: [{
                        data: typeData,
                        backgroundColor: ['#f97316', '#3b82f6', '#22c55e', '#a855f7', '#64748b'],
                        borderColor: '#111827',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#9ca3af', font: { family: 'Inter', size: 11 } }
                        }
                    }
                }
            });

            // 2. Configurar Gráfico de Barras por Fase
            const phaseCtx = document.getElementById('publicPhaseChart').getContext('2d');
            const phases = <?php echo json_encode($phasesArray); ?>;
            
            const phaseTotals = <?php 
                $totalsMap = [];
                foreach ($expensesByPhase as $ebp) {
                    $totalsMap[$ebp['phase']] = (float)$ebp['total'];
                }
                $orderedTotals = [];
                foreach ($phasesArray as $ph) {
                    $orderedTotals[] = $totalsMap[$ph] ?? 0.00;
                }
                echo json_encode($orderedTotals);
            ?>;

            new Chart(phaseCtx, {
                type: 'bar',
                data: {
                    labels: phases,
                    datasets: [{
                        label: 'Gasto por Fase',
                        data: phaseTotals,
                        backgroundColor: 'rgba(249, 115, 22, 0.85)',
                        borderColor: '#f97316',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#9ca3af', font: { family: 'Inter' } } },
                        y: { 
                            grid: { color: 'rgba(255,255,255,0.03)' }, 
                            ticks: { 
                                color: '#9ca3af',
                                callback: (val) => val.toLocaleString('pt-AO')
                            } 
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        });
        </script>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
