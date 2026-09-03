<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Rota estritamente autenticada
middleware_require_auth();

$user = current_user();
$title = 'Minhas Obras — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';

$db = db();

// Buscar todos os projetos em que o utilizador é proprietário, gestor ou membro da equipa
try {
    $projects = $db->fetchAll(
        "SELECT DISTINCT p.*, COALESCE(pm.role, 'owner') AS role,
                (SELECT SUM(price * quantity) FROM expenses WHERE project_id = p.id AND deleted_at IS NULL) AS total_spent,
                (SELECT SUM(price * quantity) FROM pre_budgets WHERE project_id = p.id) AS total_planned,
                (SELECT SUM(amount * exchange_rate) FROM project_funds WHERE project_id = p.id) AS total_funds
         FROM projects p
         LEFT JOIN project_managers pm ON p.id = pm.project_id AND pm.user_id = ?
         WHERE p.user_id = ? OR pm.user_id = ?
         ORDER BY p.created_at DESC",
        [$user['id'], $user['id'], $user['id']]
    );
} catch (PDOException $e) {
    $projects = [];
}
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    @media (max-width: 768px) {
        .projects-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: 16px;
        }
        .projects-header .btn {
            justify-content: center;
            width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .projects-grid {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
        }
    }
</style>

<div style="display:flex; flex-direction:column; gap:24px;">
    
    <!-- CABEÇALHO DA PÁGINA -->
    <div class="projects-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2>Painel de Gestão de Obras</h2>
            <p style="color:var(--text-secondary); font-size:14px;">Controle o planeamento, timeline física e orçamentos das suas construções civis.</p>
        </div>
        <a href="/projects/create" class="btn btn-primary">
            <i data-lucide="plus"></i>
            Nova Obra
        </a>
    </div>

    <!-- PAINEL DE ESTATÍSTICAS RÁPIDAS -->
    <?php if (!empty($projects)): ?>
        <?php
        $totalBudget = 0;
        $totalSpent = 0;
        $activeCount = 0;
        foreach ($projects as $p) {
            $projBudget = (float)$p['budget'];
            if ($projBudget <= 0) {
                $projBudget = (float)($p['total_planned'] ?? 0.00);
                if ($projBudget <= 0) {
                    $projBudget = (float)($p['total_funds'] ?? 0.00);
                }
            }
            $totalBudget += $projBudget;
            $totalSpent += (float)($p['total_spent'] ?? 0);
            if ($p['status'] === 'active') {
                $activeCount++;
            }
        }
        ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
            <div class="card" style="padding: 16px; display:flex; align-items:center; gap:16px;">
                <div style="background:rgba(249,115,22,0.1); padding: 12px; border-radius: var(--radius-sm);">
                    <i data-lucide="calculator" style="color:var(--accent-primary);"></i>
                </div>
                <div>
                    <small style="color:var(--text-secondary);">Orçamento Geral</small>
                    <h4 style="margin-top:2px;"><?php echo format_currency($totalBudget); ?></h4>
                </div>
            </div>
            
            <div class="card" style="padding: 16px; display:flex; align-items:center; gap:16px;">
                <div style="background:rgba(59,130,246,0.1); padding: 12px; border-radius: var(--radius-sm);">
                    <i data-lucide="trending-up" style="color:var(--accent-secondary);"></i>
                </div>
                <div>
                    <small style="color:var(--text-secondary);">Despesa Acumulada</small>
                    <h4 style="margin-top:2px; color:var(--accent-primary);"><?php echo format_currency($totalSpent); ?></h4>
                </div>
            </div>

            <div class="card" style="padding: 16px; display:flex; align-items:center; gap:16px;">
                <div style="background:rgba(16,185,129,0.1); padding: 12px; border-radius: var(--radius-sm);">
                    <i data-lucide="check-circle" style="color:var(--accent-success);"></i>
                </div>
                <div>
                    <small style="color:var(--text-secondary);">Obras Ativas</small>
                    <h4 style="margin-top:2px; color:var(--accent-success);"><?php echo $activeCount; ?> ativas</h4>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- LISTA DE OBRAS (GRID) -->
    <?php if (empty($projects)): ?>
        <div class="card" style="text-align:center; padding:60px 40px;">
            <div style="background:rgba(249,115,22,0.05); width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin: 0 auto 20px;">
                <i data-lucide="briefcase" style="width:32px; height:32px; color:var(--accent-primary);"></i>
            </div>
            <h3>Nenhuma obra registada</h3>
            <p style="color:var(--text-secondary); font-size:14px; max-width:340px; margin: 8px auto 20px;">Comece a controlar as finanças da sua construção civil agora mesmo. Crie a sua primeira obra!</p>
            <a href="/projects/create" class="btn btn-primary">Começar Já</a>
        </div>
    <?php else: ?>
        <div class="projects-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
            <?php foreach ($projects as $project): ?>
                <?php
                $spent = (float)($project['total_spent'] ?? 0);
                $budget = (float)$project['budget'];
                if ($budget <= 0) {
                    $budget = (float)($project['total_planned'] ?? 0.00);
                    if ($budget <= 0) {
                        $budget = (float)($project['total_funds'] ?? 0.00);
                    }
                }
                $percent = $budget > 0 ? ($spent / $budget) * 100 : 0;
                // (§5) Número real (pode exceder 100%); só a barra visual é limitada
                $percentText = number_format($percent, 0);
                $percentBar = min(100, $percent);
                
                // Tratar imagem de capa
                $cover = $project['cover_image_url'] ? APP_URL . '/' . $project['cover_image_url'] : 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?q=80&w=600&auto=format&fit=crop';
                
                // Tratar badges de status
                $statusText = 'Planeamento';
                $statusClass = 'badge-secondary';
                if ($project['status'] === 'active') {
                    $statusText = 'Ativo';
                    $statusClass = 'badge-success';
                } elseif ($project['status'] === 'paused') {
                    $statusText = 'Pausado';
                    $statusClass = 'badge-warning';
                } elseif ($project['status'] === 'completed') {
                    $statusText = 'Concluído';
                    $statusClass = 'badge-info';
                }
                ?>
                <div class="card" style="padding:0; overflow:hidden; display:flex; flex-direction:column; position:relative;">
                    
                    <!-- Cover do Projeto -->
                    <div style="height: 160px; background: url('<?php echo $cover; ?>') center/cover no-repeat; position:relative;">
                        <div style="position:absolute; top:12px; right:12px;">
                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </div>
                        <div style="position:absolute; top:12px; left:12px;">
                            <span class="badge" style="background: rgba(10,15,30,0.8); border:1px solid rgba(255,255,255,0.1); color:#ffffff; font-size:10px;">
                                <i data-lucide="shield" style="width:10px; height:10px; margin-right:4px;"></i>
                                <?php echo ucfirst($project['role']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Conteúdo do Card -->
                    <div style="padding:20px; flex:1; display:flex; flex-direction:column; gap:16px;">
                        <div>
                            <h3 style="font-size:18px; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo sanitize($project['title']); ?></h3>
                            <span style="font-size:12px; color:var(--text-secondary); display:flex; align-items:center; gap:4px; margin-top:4px;">
                                <i data-lucide="map-pin" style="width:12px; height:12px;"></i>
                                <?php echo sanitize($project['location'] ?: 'Sem localização'); ?>
                            </span>
                        </div>

                        <!-- Barra de consumo de orçamento -->
                        <div>
                            <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:6px;">
                                <span style="color:var(--text-secondary);">Consumo: <strong><?php echo $percentText; ?>%</strong></span>
                                <span style="font-weight:600; color:<?php echo $percent > 100 ? 'var(--accent-danger)' : 'var(--text-primary)'; ?>;">
                                    <?php echo format_currency($spent); ?>
                                </span>
                            </div>
                            <div style="width:100%; height:6px; background:var(--bg-elevated); border-radius:3px; overflow:hidden;">
                                <div style="width: <?php echo $percentBar; ?>%; height:100%; background: <?php echo $percent > 100 ? 'var(--accent-danger)' : 'var(--accent-primary)'; ?>; border-radius:3px;"></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:10px; color:var(--text-muted); margin-top:4px;">
                                <span>Gasto</span>
                                <span>Orçamento: <?php echo format_currency($budget); ?></span>
                            </div>
                        </div>

                        <!-- Links Rápidos -->
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:auto; padding-top:10px; border-top:1px solid var(--border-color);">
                            <a href="/projects/detail?id=<?php echo $project['id']; ?>" class="btn btn-secondary" style="font-size:12px; padding: 8px; justify-content:center;">
                                <i data-lucide="timeline" style="width:14px; height:14px;"></i>
                                Cronograma
                            </a>
                            <a href="/projects/financials?id=<?php echo $project['id']; ?>" class="btn btn-primary" style="font-size:12px; padding: 8px; justify-content:center;">
                                <i data-lucide="dollar-sign" style="width:14px; height:14px;"></i>
                                Financeiro
                            </a>
                        </div>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
