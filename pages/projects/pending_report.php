<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Exige autenticação
middleware_require_auth();

$user = current_user();
$projectId = (int)input('id', 0);
$db = db();

// Obter projeto e validação de permissões
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

// Obter despesas e pré-orçamentos
try {
    $expenses = $db->fetchAll(
        "SELECT e.*, pr.name AS registrant_name 
         FROM expenses e 
         JOIN profiles pr ON e.user_id = pr.id 
         WHERE e.project_id = ? 
         ORDER BY e.created_at DESC",
        [$projectId]
    );
} catch (PDOException $e) {
    $expenses = [];
}

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

$phases = json_decode($project['phases'], true) ?? ['Fundação', 'Estrutura', 'Alvenaria', 'Cobertura', 'Acabamentos'];

// Pré-calcular quantidades compradas e estado de cada item do pré-orçamento
$pendingItems = [];
$partialItems = [];
$fullItems = [];
$totalPlannedCost = 0.00;
$totalPendingCost = 0.00;

foreach ($preBudgets as &$pb) {
    $itemPlannedCost = (float)$pb['price'] * (float)$pb['quantity'];
    $totalPlannedCost += $itemPlannedCost;
    
    // Qtd já comprada nas despesas reais
    $purchasedQty = 0.00;
    foreach ($expenses as $exp) {
        if (mb_strtolower(trim($exp['name'])) === mb_strtolower(trim($pb['name'])) && $exp['phase'] === $pb['phase']) {
            $purchasedQty += (float)$exp['quantity'];
        }
    }
    
    $plannedQty = (float)$pb['quantity'];
    $remQty = max(0.00, $plannedQty - $purchasedQty);
    $remCost = (float)$pb['price'] * $remQty;
    
    $pb['_purchased_qty'] = $purchasedQty;
    $pb['_rem_qty'] = $remQty;
    $pb['_rem_cost'] = $remCost;
    
    if ($purchasedQty <= 0) {
        $pb['_status'] = 'none';
        $pendingItems[] = $pb;
        $totalPendingCost += $remCost;
    } elseif ($remQty > 0) {
        $pb['_status'] = 'partial';
        $partialItems[] = $pb;
        $totalPendingCost += $remCost;
    } else {
        $pb['_status'] = 'full';
        $fullItems[] = $pb;
    }
}
unset($pb);

$allPendingOrPartial = array_merge($pendingItems, $partialItems);

$title = 'Relatório de Materiais Pendentes — ' . sanitize($project['title']);
require_once __DIR__ . '/../../templates/header.php';
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    .report-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0b1329 100%);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 28px 32px;
        margin-bottom: 24px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        position: relative;
        overflow: hidden;
    }

    .report-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(239, 68, 68, 0.12) 0%, rgba(0, 0, 0, 0) 70%);
        pointer-events: none;
    }

    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        box-shadow: var(--shadow-sm);
        transition: transform 0.2s;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .phase-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
    }

    .phase-header {
        padding: 16px 20px;
        background: rgba(255, 255, 255, 0.02);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    @media print {
        header, footer, .no-print, .btn, .main-sidebar, .bottom-nav {
            display: none !important;
        }
        body {
            background: #ffffff !important;
            color: #000000 !important;
            font-size: 12pt;
        }
        .report-page-container {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .report-hero {
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
            box-shadow: none !important;
        }
        .report-hero h1, .report-hero p, .report-hero span {
            color: #0f172a !important;
        }
        .kpi-card, .phase-section {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #000000 !important;
            box-shadow: none !important;
        }
        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }
        th, td {
            border: 1px solid #cbd5e1 !important;
            color: #000000 !important;
            padding: 8px 12px !important;
        }
        .print-header-only {
            display: block !important;
        }
    }

    .print-header-only {
        display: none;
    }
</style>

<div style="max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;" class="slideUp report-page-container">

    <!-- BARRA SUPERIOR DE AÇÕES / VOLTAR -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;" class="no-print">
        <a href="/projects/financials?id=<?php echo $projectId; ?>" class="btn btn-secondary" style="font-size:13px; display:inline-flex; align-items:center; gap:8px;">
            <i data-lucide="arrow-left" style="width:16px; height:16px;"></i>
            Voltar ao Painel Financeiro
        </a>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button data-jsaction="__print__" class="btn btn-primary" style="background:#10b981; border-color:#10b981; font-weight:800; font-size:13px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 14px rgba(16, 185, 129, 0.3);">
                <i data-lucide="printer" style="width:16px; height:16px;"></i>
                Imprimir / Guardar em PDF
            </button>

            <button data-jsaction="shareWhatsAppReport" class="btn" style="background:#22c55e; border-color:#22c55e; color:white; font-weight:800; font-size:13px; display:inline-flex; align-items:center; gap:8px;">
                <i data-lucide="message-square" style="width:16px; height:16px;"></i>
                Enviar via WhatsApp
            </button>

            <a href="/marketplace" class="btn btn-secondary" style="font-size:13px; display:inline-flex; align-items:center; gap:8px;">
                <i data-lucide="shopping-bag" style="width:16px; height:16px;"></i>
                Ir ao Marketplace B2B
            </a>
        </div>
    </div>

    <!-- CABEÇALHO OFICIAL DA OBRA PARA IMPRESSÃO -->
    <div class="print-header-only" style="margin-bottom:20px; border-bottom:2px solid #000; padding-bottom:15px;">
        <h2 style="margin:0; font-size:22px; font-weight:bold;">CONSTRÓI JÁ — RELATÓRIO OFICIAL DE MATERIAIS PENDENTES</h2>
        <p style="margin:4px 0 0 0; font-size:14px;"><strong>Obra:</strong> <?php echo sanitize($project['title']); ?> | <strong>Local:</strong> <?php echo sanitize($project['location']); ?> | <strong>Data:</strong> <?php echo date('d/m/Y H:i'); ?></p>
    </div>

    <!-- HERO HEADER -->
    <div class="report-hero">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:20px;">
            <div>
                <span style="font-size:11px; background:rgba(239, 68, 68, 0.15); border:1px solid rgba(239, 68, 68, 0.3); color:#ef4444; padding:4px 14px; border-radius:50px; text-transform:uppercase; font-weight:800; letter-spacing:0.5px; display:inline-block; margin-bottom:12px;">
                    📋 Relatório de Planeamento & Compras Pendentes
                </span>
                <h1 style="margin:0; font-family:'Outfit', sans-serif; font-size:26px; font-weight:800; color:#ffffff;">
                    <?php echo sanitize($project['title']); ?>
                </h1>
                <p style="margin:6px 0 0 0; color:#94a3b8; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="map-pin" style="width:14px; height:14px; color:var(--accent-primary);"></i>
                    <?php echo sanitize($project['location'] ?: 'Angola'); ?>
                    • Gerado a <?php echo date('d/m/Y \à\s H:i'); ?>
                </p>
            </div>

            <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:var(--radius-md); padding:14px 20px; text-align:right;">
                <span style="font-size:11px; color:#94a3b8; font-weight:700; text-transform:uppercase;">Orçamento Estimado Pendente</span>
                <div style="font-size:24px; font-weight:800; color:#ef4444; margin-top:2px;">
                    <?php echo format_currency($totalPendingCost); ?>
                </div>
                <small style="color:var(--text-secondary); font-size:11px;">Valor total ainda por adquirir</small>
            </div>
        </div>
    </div>

    <!-- CARDS KPI DE RESUMO -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
        <div class="kpi-card" style="border-left:4px solid #ef4444;">
            <span style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Materiais Não Comprados</span>
            <div style="font-size:24px; font-weight:800; color:#ef4444;"><?php echo count($pendingItems); ?> itens</div>
            <small style="color:var(--text-secondary); font-size:12px;">Com 0% de compra efetuada</small>
        </div>

        <div class="kpi-card" style="border-left:4px solid var(--accent-primary);">
            <span style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Materiais Parciais</span>
            <div style="font-size:24px; font-weight:800; color:var(--accent-primary);"><?php echo count($partialItems); ?> itens</div>
            <small style="color:var(--text-secondary); font-size:12px;">Em processo de aquisição</small>
        </div>

        <div class="kpi-card" style="border-left:4px solid var(--accent-success);">
            <span style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Materiais Concluídos</span>
            <div style="font-size:24px; font-weight:800; color:var(--accent-success);"><?php echo count($fullItems); ?> itens</div>
            <small style="color:var(--text-secondary); font-size:12px;">Totalmente adquiridos</small>
        </div>

        <div class="kpi-card" style="border-left:4px solid var(--accent-secondary);">
            <span style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Total de Itens Planeados</span>
            <div style="font-size:24px; font-weight:800; color:#ffffff;"><?php echo count($preBudgets); ?> itens</div>
            <small style="color:var(--text-secondary); font-size:12px;">Orçamento global de <?php echo format_currency($totalPlannedCost); ?></small>
        </div>
    </div>

    <!-- LISTAGEM DE MATERIAIS AGRUPADA POR FASE -->
    <div style="margin-top:10px;">
        <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; color:#ffffff; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="layers" style="color:var(--accent-secondary);"></i>
            Detalhamento por Fase da Obra
        </h3>

        <?php 
        $hasAnyPending = false;
        foreach ($phases as $ph):
            $phPending = array_filter($preBudgets, function($pb) use ($ph) {
                return $pb['phase'] === $ph && in_array($pb['_status'], ['none', 'partial'], true);
            });

            if (empty($phPending)) continue;
            $hasAnyPending = true;

            $phPendingTotal = 0.00;
            foreach ($phPending as $pItem) {
                $phPendingTotal += $pItem['_rem_cost'];
            }
        ?>
            <div class="phase-section">
                <div class="phase-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="width:10px; height:10px; border-radius:50%; background:var(--accent-secondary); display:inline-block;"></span>
                        <h4 style="margin:0; font-family:'Outfit', sans-serif; font-size:15px; font-weight:800; color:#ffffff; text-transform:uppercase;">
                            <?php echo sanitize($ph); ?>
                        </h4>
                        <span class="badge" style="background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.2); font-size:11px; font-weight:700;">
                            <?php echo count($phPending); ?> materiais pendentes
                        </span>
                    </div>

                    <div style="font-size:13px; font-weight:800; color:#ef4444;">
                        Valor a Investir: <?php echo format_currency($phPendingTotal); ?>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="table responsive-table" style="width:100%; border-collapse:collapse; font-size:13.5px; text-align:left;">
                        <thead>
                            <tr style="background:rgba(0,0,0,0.1); border-bottom:1px solid var(--border-color); color:var(--text-muted); font-size:11px; text-transform:uppercase;">
                                <th style="padding:12px 16px;">Material / Serviço Estimado</th>
                                <th style="padding:12px 16px;">Preço Unit. Est.</th>
                                <th style="padding:12px 16px;">Qtd Planeada</th>
                                <th style="padding:12px 16px;">Já Comprado</th>
                                <th style="padding:12px 16px;">Qtd Pendente</th>
                                <th style="padding:12px 16px;">Custo em Falta</th>
                                <th style="padding:12px 16px; text-align:right;" class="no-print">Ação Rápida</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($phPending as $pItem): ?>
                                <tr style="border-bottom:1px solid var(--border-color);">
                                    <td data-label="Material" style="padding:14px 16px; font-weight:700; color:#ffffff;">
                                        <?php echo sanitize($pItem['name']); ?>
                                        <?php if ($pItem['_status'] === 'none'): ?>
                                            <span class="badge" style="background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid rgba(239,68,68,0.3); font-size:10px; margin-left:6px;">Por Comprar</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:rgba(249,115,22,0.15); color:var(--accent-primary); border:1px solid rgba(249,115,22,0.3); font-size:10px; margin-left:6px;">Parcialmente Comprado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Preço Unit." style="padding:14px 16px; color:var(--text-secondary);">
                                        <?php echo format_currency((float)$pItem['price']); ?>
                                    </td>
                                    <td data-label="Qtd Planeada" style="padding:14px 16px; font-weight:600;">
                                        <?php echo (float)$pItem['quantity'] . ' ' . sanitize($pItem['unit']); ?>
                                    </td>
                                    <td data-label="Já Comprado" style="padding:14px 16px; color:var(--accent-success); font-weight:700;">
                                        <?php echo $pItem['_purchased_qty'] . ' ' . sanitize($pItem['unit']); ?>
                                    </td>
                                    <td data-label="Qtd Pendente" style="padding:14px 16px; font-weight:800; color:#ef4444;">
                                        <?php echo $pItem['_rem_qty'] . ' ' . sanitize($pItem['unit']); ?>
                                    </td>
                                    <td data-label="Custo em Falta" style="padding:14px 16px; font-weight:800; color:var(--accent-secondary);">
                                        <?php echo format_currency($pItem['_rem_cost']); ?>
                                    </td>
                                    <td data-label="Ação Rápida" style="padding:14px 16px; text-align:right;" class="no-print">
                                        <a href="/marketplace?search=<?php echo urlencode($pItem['name']); ?>" class="btn btn-secondary" style="font-size:11px; padding:4px 10px; display:inline-flex; align-items:center; gap:4px; background:rgba(16,185,129,0.08); border-color:rgba(16,185,129,0.2); color:#10b981;" target="_blank">
                                            <i data-lucide="shopping-cart" style="width:12px; height:12px;"></i>
                                            Cotar no Marketplace
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$hasAnyPending): ?>
            <div class="card" style="text-align:center; padding:60px 20px; color:var(--accent-success);">
                <i data-lucide="check-circle-2" style="width:56px; height:56px; margin-bottom:14px; stroke-width:1.5;"></i>
                <h3 style="margin:0; font-family:'Outfit', sans-serif; font-size:20px; font-weight:800;">Todos os materiais planeados já foram adquiridos!</h3>
                <p style="margin:6px 0 0 0; font-size:14px; color:var(--text-secondary);">Não existem materiais pendentes de compra nesta obra de momento.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script nonce="<?php echo Security::getNonce(); ?>">
    function shareWhatsAppReport() {
        let msg = `🏗️ *RELATÓRIO DE MATERIAIS PENDENTES - CONSTRÓI JÁ*\n`;
        msg += `-------------------------------------------\n`;
        msg += `📌 *Obra:* <?php echo sanitize($project['title']); ?>\n`;
        msg += `📍 *Local:* <?php echo sanitize($project['location'] ?: 'Luanda'); ?>\n`;
        msg += `📅 *Data:* <?php echo date('d/m/Y H:i'); ?>\n\n`;
        msg += `💸 *VALOR TOTAL NECESSÁRIO:* <?php echo format_currency($totalPendingCost); ?>\n`;
        msg += `-------------------------------------------\n\n`;
        msg += `📦 *ITENS AINDA POR COMPRAR:*\n`;

        <?php foreach ($allPendingOrPartial as $idx => $item): ?>
            msg += `• *<?php echo sanitize($item['name']); ?>* (<?php echo sanitize($item['phase']); ?>)\n`;
            msg += `  Falta: <?php echo $item['_rem_qty'] . ' ' . sanitize($item['unit']); ?> | Estimado: <?php echo format_currency($item['_rem_cost']); ?>\n`;
        <?php endforeach; ?>

        msg += `\n-------------------------------------------\n`;
        msg += `Gerado via Constrói Já: ${window.location.origin}/projects/pending-report?id=<?php echo $projectId; ?>`;

        const waUrl = `https://wa.me/?text=${encodeURIComponent(msg)}`;
        window.open(waUrl, '_blank');
    }
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
