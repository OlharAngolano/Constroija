<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Rota estritamente autenticada
middleware_require_auth();

$user = current_user();
$projectId = (int)input('id', 0);
$db = db();

// 1. Procurar o projeto e o cargo do utilizador logado
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
    set_flash_message('danger', 'Projeto de obra não encontrado ou acesso negado.');
    redirect('/projects');
}

$title = sanitize($project['title']) . ' — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';

// 2. Procurar equipa do projeto
$collaborators = $db->fetchAll(
    "SELECT pm.id, pm.role, pr.name, pr.username, pr.avatar_url, pr.is_verified 
     FROM project_managers pm 
     JOIN profiles pr ON pm.user_id = pr.id 
     WHERE pm.project_id = ?
     ORDER BY FIELD(pm.role, 'owner', 'manager', 'viewer')",
    [$projectId]
);

// 3. Resumo de gastos por fase
$expensesSummary = $db->fetchAll(
    "SELECT phase, SUM(price * quantity) AS total 
     FROM expenses 
     WHERE project_id = ? AND deleted_at IS NULL 
     GROUP BY phase",
    [$projectId]
);
$phaseSpent = [];
$totalSpent = 0.00;
foreach ($expensesSummary as $es) {
    $phaseSpent[$es['phase']] = (float)$es['total'];
    $totalSpent += (float)$es['total'];
}

// 4. Decodificar as fases
$phases = json_decode($project['phases'], true);
if (empty($phases)) {
    $phases = ['Fundação', 'Estrutura', 'Alvenaria', 'Cobertura', 'Acabamentos'];
}

$budget = (float)$project['budget'];
if ($budget <= 0.00) {
    try {
        $plannedResult = $db->fetch(
            "SELECT SUM(price * quantity) AS total FROM pre_budgets WHERE project_id = ?",
            [$projectId]
        );
        $totalPlanned = (float)($plannedResult['total'] ?? 0.00);
        if ($totalPlanned > 0) {
            $budget = $totalPlanned;
        }
    } catch (PDOException $e) {
        // Fallback silenciando
    }
}

// Tratar imagem de capa
$cover = $project['cover_image_url'] ? APP_URL . '/' . $project['cover_image_url'] : 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?q=80&w=1200&auto=format&fit=crop';
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    .project-banner {
        height: 240px;
        background-position: center;
        background-size: cover;
        background-repeat: no-repeat;
        display: flex;
        align-items: flex-end;
        padding: 30px;
        transition: all var(--transition-normal);
    }
    .project-banner-title {
        color: #ffffff;
        font-size: 28px;
        font-weight: 800;
        line-height: 1.3;
        margin: 0;
    }
    
    @media (max-width: 992px) {
        .project-detail-layout {
            grid-template-columns: 1fr !important;
            gap: 20px !important;
        }
    }
    @media (max-width: 768px) {
        .detail-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: 16px;
        }
        .detail-header > div {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            width: 100%;
        }
        .detail-header .btn, .detail-header button {
            flex: 1;
            min-width: 120px;
            justify-content: center;
        }
        .project-banner {
            height: auto;
            min-height: 200px;
            padding: 40px 20px 20px 20px;
        }
        .project-banner-title {
            font-size: 22px !important;
        }
        .project-banner-row {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 16px;
        }
    }
    @media (max-width: 576px) {
        .timeline-phase-card {
            padding: 12px !important;
            margin-left: 4px !important;
        }
        .timeline-phase-row {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 10px !important;
        }
        .timeline-phase-meta {
            text-align: left !important;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 8px;
            margin-top: 4px;
        }
        .timeline-phase-meta p {
            margin-top: 0 !important;
        }
        .form-grid-2 {
            grid-template-columns: 1fr !important;
            gap: 12px !important;
            margin-bottom: 12px !important;
        }
        .checkbox-group {
            margin-top: 8px !important;
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr !important;
        }
        .detail-header > div {
            flex-direction: column;
        }
        .detail-header .btn, .detail-header button {
            width: 100%;
        }
        .project-banner {
            padding: 30px 16px 16px 16px;
        }
        .project-banner-title {
            font-size: 18px !important;
        }
        .modal-footer-buttons {
            flex-direction: column-reverse;
            align-items: stretch;
        }
        .modal-footer-buttons button {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div style="display:flex; flex-direction:column; gap:20px;">
    
    <!-- Link de Retorno e Ações Rápidas -->
    <div class="detail-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <a href="/projects" style="display:inline-flex; align-items:center; gap:6px; font-size:14px; color:var(--text-secondary);">
            <i data-lucide="arrow-left" style="width:16px; height:16px;"></i>
            Voltar ao Painel Geral
        </a>
        
        <div style="display:flex; gap:10px;">
            <a href="/projects/financials?id=<?php echo $projectId; ?>" class="btn btn-primary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="dollar-sign" style="width:16px; height:16px;"></i>
                Dashboard Financeiro
            </a>
            <?php if (in_array($project['role'], ['owner', 'manager']) || (int)($user['is_admin'] ?? 0) === 1): ?>
                <button  data-jsaction="openEditProjectModal" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                    <i data-lucide="edit" style="width:16px; height:16px; color:var(--accent-secondary);"></i>
                    Editar Obra
                </button>
            <?php endif; ?>
            <?php if ($project['role'] === 'owner' || (int)($user['is_admin'] ?? 0) === 1): ?>
                <button  data-jsaction="confirmDeleteProject" class="btn btn-danger" style="font-size:13px; padding: 8px 16px;">
                    <i data-lucide="trash-2" style="width:16px; height:16px;"></i>
                    Apagar Obra
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Banner Principal da Obra -->
    <div class="card" style="padding:0; overflow:hidden; border-radius:var(--radius-lg);">
        <div class="project-banner" style="background-image: linear-gradient(rgba(10,15,30,0.1), rgba(10,15,30,0.8)), url('<?php echo $cover; ?>');">
            <div style="display:flex; flex-direction:column; gap:6px; width:100%;">
                <div class="project-banner-row" style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; width:100%;">
                    <div>
                        <span class="badge badge-primary" style="margin-bottom:8px;">Obra #<?php echo $project['id']; ?></span>
                        <h2 class="project-banner-title"><?php echo sanitize($project['title']); ?></h2>
                        <span style="font-size:14px; color:var(--text-primary); display:flex; align-items:center; gap:4px; margin-top:4px;">
                            <i data-lucide="map-pin" style="width:14px; height:14px; color:var(--accent-primary);"></i>
                            <?php echo sanitize($project['location'] ?: 'Localização indefinida'); ?>
                        </span>
                    </div>
                    
                    <!-- Mudança de Status Rápida (para Owners e Managers) -->
                    <?php if (in_array($project['role'], ['owner', 'manager'])): ?>
                        <div class="form-group" style="background:rgba(10,15,30,0.8); padding:8px 12px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                            <label for="project-status-select" style="font-size:10px; color:var(--text-secondary); display:block; margin-bottom:4px;">Estado Físico da Obra</label>
                            <select id="project-status-select" class="form-control"  data-jsaction="updateProjectStatus" data-jsarg="__value__" style="background:transparent; padding:0; border:0; height:auto; width:auto; font-weight:700; color:var(--accent-primary); cursor:pointer;">
                                <option value="planning" <?php echo $project['status'] === 'planning' ? 'selected' : ''; ?>>Planeamento</option>
                                <option value="active" <?php echo $project['status'] === 'active' ? 'selected' : ''; ?>>Ativo (Em Curso)</option>
                                <option value="paused" <?php echo $project['status'] === 'paused' ? 'selected' : ''; ?>>Pausado</option>
                                <option value="completed" <?php echo $project['status'] === 'completed' ? 'selected' : ''; ?>>Concluído</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <span class="badge badge-success" style="padding: 10px 16px; font-weight:700;">
                            <?php echo strtoupper($project['status'] === 'planning' ? 'Planeamento' : ($project['status'] === 'active' ? 'Ativo' : ($project['status'] === 'paused' ? 'Pausado' : 'Concluído'))); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Layout Dual Columns -->
    <div class="project-detail-layout" style="display:grid; grid-template-columns: 1fr 340px; gap:24px; align-items:start;">
        
        <!-- COLUNA ESQUERDA: TIMELINE FÍSICA E PROGRESSO -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            
            <!-- Descrição e Métricas Básicas -->
            <div class="card" style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <h3 style="margin-bottom:8px; border-left:3px solid var(--accent-primary); padding-left:10px;">Sobre a Obra</h3>
                    <p style="color:var(--text-secondary); line-height:1.6; font-size:14px; white-space:pre-line;">
                        <?php echo sanitize($project['description'] ?: 'Sem descrição sumária de objetivos de construção.'); ?>
                    </p>
                </div>
                
                <div class="stats-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; border-top:1px solid var(--border-color); padding-top:16px; margin-top:8px;">
                    <div>
                        <small style="color:var(--text-muted);">Data de Início</small>
                        <p style="font-weight:600; font-size:14px;"><?php echo $project['start_date'] ? date('d/m/Y', strtotime($project['start_date'])) : 'Indefinida'; ?></p>
                    </div>
                    <div>
                        <small style="color:var(--text-muted);">Previsão de Entrega</small>
                        <p style="font-weight:600; font-size:14px;"><?php echo $project['end_date'] ? date('d/m/Y', strtotime($project['end_date'])) : 'Indefinida'; ?></p>
                    </div>
                </div>
            </div>

            <!-- TIMELINE VISUAL GLOWING -->
            <div class="card">
                <h3 style="margin-bottom:20px; border-left:3px solid var(--accent-secondary); padding-left:10px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="git-commit" style="color:var(--accent-secondary);"></i>
                    Timeline Física & Cronograma
                </h3>
                
                <div class="glowing-timeline" style="display:flex; flex-direction:column; gap:0; position:relative; padding-left:24px;">
                    <!-- Linha Vertical de Fundo -->
                    <div style="position:absolute; left:7px; top:10px; bottom:10px; width:2px; background:var(--border-color); z-index:1;"></div>

                    <?php foreach ($phases as $index => $phase): ?>
                        <?php
                        $spentInPhase = $phaseSpent[$phase] ?? 0.00;
                        $hasActivity = $spentInPhase > 0;
                        $isCompleted = ($project['status'] === 'completed');
                        
                        // Estado visual do nó
                        $nodeBg = 'var(--bg-card)';
                        $nodeBorder = 'var(--border-color)';
                        $nodeGlow = '';
                        $statusLabel = 'Planeada';
                        
                        if ($isCompleted) {
                            $nodeBg = 'var(--accent-success)';
                            $nodeBorder = 'var(--accent-success)';
                            $statusLabel = 'Concluída';
                        } elseif ($hasActivity) {
                            $nodeBg = 'var(--accent-secondary)';
                            $nodeBorder = 'var(--accent-secondary)';
                            $nodeGlow = 'box-shadow: 0 0 12px var(--accent-secondary);';
                            $statusLabel = 'Em Curso';
                        }
                        ?>
                        <div style="position:relative; padding-bottom: 24px; z-index:2; display:flex; gap:16px; align-items:flex-start;">
                            
                            <!-- Indicador Circular (Nó) -->
                            <div style="width:16px; height:16px; border-radius:50%; background:<?php echo $nodeBg; ?>; border:3px solid <?php echo $nodeBorder; ?>; margin-left:-23px; margin-top:4px; z-index:3; <?php echo $nodeGlow; ?> transition: var(--transition-fast);"></div>
                            
                            <!-- Conteúdo da Fase -->
                            <div class="card timeline-phase-card" style="flex:1; padding:16px; margin-left:8px; background:rgba(255,255,255,0.015); border-color:<?php echo $hasActivity ? 'rgba(59,130,246,0.15)' : 'var(--border-color)'; ?>;">
                                <div class="timeline-phase-row" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                    <div>
                                        <h4 style="color:#ffffff; font-size:15px; font-weight:700;"><?php echo sanitize($phase); ?></h4>
                                        <small style="color:var(--text-secondary); display:block; margin-top:2px;">Fase <?php echo $index + 1; ?></small>
                                    </div>
                                    <div class="timeline-phase-meta" style="text-align:right;">
                                        <span class="badge" style="background:<?php echo $hasActivity ? 'rgba(59,130,246,0.1)' : 'rgba(255,255,255,0.02)'; ?>; color:<?php echo $hasActivity ? 'var(--accent-secondary)' : 'var(--text-muted)'; ?>; font-size:11px;">
                                            <?php echo $statusLabel; ?>
                                        </span>
                                        <?php if ($spentInPhase > 0): ?>
                                            <p style="font-size:12px; font-weight:600; color:var(--accent-primary); margin-top:4px;"><?php echo format_currency($spentInPhase); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- COLUNA DIREITA: EQUIPA DO PROJETO -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            
            <!-- CARD EQUIPA -->
            <div class="card" style="padding: 20px;">
                <h4 style="margin-bottom:16px; display:flex; align-items:center; gap:8px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    <i data-lucide="users" style="color:var(--accent-primary);"></i>
                    Equipa da Obra
                </h4>
                
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <?php foreach ($collaborators as $collab): ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px; cursor:pointer;"  data-jsaction="__go__" data-jsarg="'/profile/<?php echo sanitize($collab['username']); ?>'">
                                <img src="<?php echo get_avatar_url($collab['avatar_url'], $collab['name']); ?>" class="avatar avatar-sm" style="width:32px; height:32px;">
                                <div>
                                    <span style="font-weight:600; font-size:13px; display:flex; align-items:center; gap:3px;">
                                        <?php echo sanitize(explode(' ', $collab['name'])[0]); ?>
                                        <?php if ($collab['is_verified']): ?>
                                            <i data-lucide="check-circle-2" style="width:10px; height:10px; color:var(--accent-secondary); fill:var(--accent-secondary); --lucide-stroke: #0a0f1e;"></i>
                                        <?php endif; ?>
                                    </span>
                                    <small style="color:var(--text-muted); font-size:10px; display:block;">@<?php echo sanitize($collab['username']); ?></small>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span class="badge" style="font-size:10px; padding:3px 8px; background:<?php echo $collab['role'] === 'owner' ? 'rgba(249,115,22,0.1)' : ($collab['role'] === 'manager' ? 'rgba(59,130,246,0.1)' : 'rgba(255,255,255,0.05)'); ?>; color:<?php echo $collab['role'] === 'owner' ? 'var(--accent-primary)' : ($collab['role'] === 'manager' ? 'var(--accent-secondary)' : 'var(--text-secondary)'); ?>;">
                                    <?php echo $collab['role'] === 'owner' ? 'Dono' : ($collab['role'] === 'manager' ? 'Gestor' : 'Leitor'); ?>
                                </span>
                                <?php if ($project['role'] === 'owner' && $collab['role'] !== 'owner'): ?>
                                    <button  data-jsaction="removeCollaborator" data-jsarg="<?php echo (int)$collab['id']; ?>" data-jsarg2="<?php echo sanitize($collab['name']); ?>" style="background:none; border:0; color:var(--accent-danger); cursor:pointer; padding:2px; display:inline-flex; align-items:center; justify-content:center;" title="Remover da Equipa">
                                        <i data-lucide="user-minus" style="width:14px; height:14px;"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- FORM CONVITE COLABORADOR (APENAS PROPRIETÁRIO) -->
                <?php if ($project['role'] === 'owner'): ?>
                    <div style="border-top: 1px solid var(--border-color); margin-top:20px; padding-top:20px;">
                        <h4 style="font-size:13px; margin-bottom:10px; color:var(--text-primary);">Convidar Profissional</h4>
                        <form id="invite-collaborator-form"  data-jsaction="inviteCollaborator" data-jsprevent="1" style="display:flex; flex-direction:column; gap:10px;">
                            <input type="text" id="collab-username" class="form-control" placeholder="Username (ex: eng.silva)" required style="font-size:12px; padding:8px 12px;">
                            
                            <select id="collab-role" class="form-control" style="font-size:12px; padding:6px 12px; height:34px; background:var(--bg-secondary);">
                                <option value="manager">Gestor (Adiciona despesas)</option>
                                <option value="viewer">Leitor (Apenas visualiza)</option>
                            </select>
                            
                            <button type="submit" id="invite-btn" class="btn btn-secondary" style="font-size:12px; padding:8px; justify-content:center;">
                                <i data-lucide="user-plus" style="width:14px; height:14px;"></i>
                                Convidar
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PUBLIC REPORT SHARING CARD -->
            <?php if (in_array($project['role'], ['owner', 'manager'])): ?>
                <div class="card" style="padding: 20px;">
                    <h4 style="margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                        <i data-lucide="share-2" style="color:var(--accent-secondary);"></i>
                        Relatório Público
                    </h4>
                    <p style="font-size:12px; color:var(--text-secondary); line-height:1.5; margin-bottom:16px;">Gere um link temporário seguro para partilhar o progresso financeiro e físico da obra com investidores ou proprietários externos sem conta.</p>
                    
                    <button class="btn btn-secondary"  data-jsaction="generatePublicLink" style="width:100%; font-size:12px; padding: 10px; justify-content:center;">
                        <i data-lucide="link" style="width:14px; height:14px;"></i>
                        Gerar Link Seguro
                    </button>
                    <div id="public-link-container" style="margin-top:12px; display:none;">
                        <input type="text" id="public-link-input" class="form-control" style="font-size:11px; padding: 6px; text-align:center; background: rgba(0,0,0,0.2);" readonly data-jsaction="__select__">
                        <small style="color:var(--accent-success); display:block; text-align:center; margin-top:4px;">Link copiado com sucesso!</small>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Modal Editar Obra -->
<div id="edit-project-modal" class="modal-overlay">
    <div class="modal" style="max-width: 650px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3 style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="edit" style="color:var(--accent-primary);"></i>
                Editar Definições da Obra
            </h3>
            <i class="modal-close" data-lucide="x"  data-jsaction="App.hideModal" data-jsarg="edit-project-modal"></i>
        </div>
        
        <form id="edit-project-form"  data-jsaction="submitEditProject" data-jsprevent="1">
            <!-- upload da imagem de capa -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-primary);">Nova Imagem de Capa (Opcional)</label>
                <div class="upload-zone" id="edit-project-upload-zone" data-preview-id="edit-project-upload-preview" style="padding: 20px; text-align:center; border: 1.5px dashed var(--border-color); border-radius: var(--radius-md); cursor:pointer; background: rgba(255,255,255,0.01);">
                    <input type="file" id="edit-project-cover" name="cover" accept="image/*" style="display:none;">
                    <i data-lucide="image" style="width:28px; height:28px; color:var(--text-muted); margin-bottom:6px;"></i>
                    <p style="font-size:12px; color:var(--text-secondary);">Arraste ou clique para carregar nova foto de capa (JPG, PNG, WEBP — Máx 10MB)</p>
                    <div id="edit-project-upload-preview" style="margin-top:12px;"></div>
                </div>
            </div>

            <!-- Dados Básicos -->
            <div style="display:grid; grid-template-columns: 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-project-title" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Nome / Título da Obra *</label>
                    <input type="text" id="edit-project-title" class="form-control" value="<?php echo sanitize($project['title']); ?>" required style="width:100%;">
                </div>
                
                <div class="form-group">
                    <label for="edit-project-description" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Descrição Sumária</label>
                    <textarea id="edit-project-description" class="form-control" style="min-height:80px;"><?php echo sanitize($project['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Dados Financeiros e Localização -->
            <div class="form-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-project-budget" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Orçamento Estimado (AOA / Kwanza) *</label>
                    <input type="number" step="0.01" id="edit-project-budget" class="form-control" value="<?php echo $budget; ?>" required style="width:100%;">
                </div>

                <div class="form-group">
                    <label for="edit-project-location" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Localização (Província / Município) *</label>
                    <input type="text" id="edit-project-location" class="form-control" value="<?php echo sanitize($project['location'] ?? ''); ?>" required style="width:100%;">
                </div>
            </div>

            <!-- Prazos -->
            <div class="form-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-project-start-date" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Data de Início</label>
                    <input type="date" id="edit-project-start-date" class="form-control" value="<?php echo $project['start_date'] ? date('Y-m-d', strtotime($project['start_date'])) : ''; ?>" style="width:100%;">
                </div>

                <div class="form-group">
                    <label for="edit-project-end-date" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Previsão de Entrega</label>
                    <input type="date" id="edit-project-end-date" class="form-control" value="<?php echo $project['end_date'] ? date('Y-m-d', strtotime($project['end_date'])) : ''; ?>" style="width:100%;">
                </div>
            </div>

            <!-- Estado e Visibilidade -->
            <div class="form-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-project-status" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Estado Físico da Obra</label>
                    <select id="edit-project-status" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="planning" <?php echo $project['status'] === 'planning' ? 'selected' : ''; ?>>Planeamento</option>
                        <option value="active" <?php echo $project['status'] === 'active' ? 'selected' : ''; ?>>Ativo (Em Curso)</option>
                        <option value="paused" <?php echo $project['status'] === 'paused' ? 'selected' : ''; ?>>Pausado</option>
                        <option value="completed" <?php echo $project['status'] === 'completed' ? 'selected' : ''; ?>>Concluído</option>
                    </select>
                </div>
                
                <div class="form-group checkbox-group" style="display:flex; align-items:center; gap:8px; margin-top:28px;">
                    <input type="checkbox" id="edit-project-is-public" style="width: 18px; height: 18px; cursor:pointer;" <?php echo $project['is_public'] ? 'checked' : ''; ?>>
                    <label for="edit-project-is-public" style="font-size:13px; font-weight:600; cursor:pointer; color:var(--text-primary);">
                        Tornar obra pública
                    </label>
                </div>
            </div>

            <!-- Botões de Submissão -->
            <div class="modal-footer-buttons" style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px; border-top:1px solid var(--border-color); padding-top:16px;">
                <button type="button" class="btn btn-secondary"  data-jsaction="App.hideModal" data-jsarg="edit-project-modal">Cancelar</button>
                <button type="submit" id="edit-project-submit" class="btn btn-primary" style="padding: 10px 24px;">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function updateProjectStatus(newStatus) {
    try {
        await App.post('/api/projects/update', {
            project_id: <?php echo $projectId; ?>,
            title: "<?php echo sanitize($project['title']); ?>",
            description: "<?php echo sanitize($project['description'] ?? ''); ?>",
            location: "<?php echo sanitize($project['location'] ?? ''); ?>",
            is_public: <?php echo $project['is_public']; ?>,
            budget: <?php echo $budget; ?>,
            status: newStatus,
            start_date: "<?php echo $project['start_date'] ?? ''; ?>",
            end_date: "<?php echo $project['end_date'] ?? ''; ?>"
        });
        App.showToast('Estado físico atualizado!', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao atualizar estado físico.', 'danger');
    }
}

async function inviteCollaborator() {
    const btn = document.getElementById('invite-btn');
    const username = document.getElementById('collab-username').value.trim();
    const role = document.getElementById('collab-role').value;
    
    if (!username) return;
    
    App.setLoading(btn, true);
    
    try {
        await App.post('/api/projects/add-collaborator', {
            project_id: <?php echo $projectId; ?>,
            username: username,
            role: role
        });
        App.showToast('Colaborador adicionado!', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao convidar colaborador.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}

async function removeCollaborator(relationId, collabName) {
    App.openConfirm(`Tem a certeza que deseja remover ${collabName} da equipa desta obra?`, async () => {
        try {
            await App.post('/api/projects/remove-collaborator', { id: relationId });
            App.showToast('Colaborador removido da equipa!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (e) {
            App.showToast(e.message || 'Erro ao remover colaborador.', 'danger');
        }
    });
}

async function generatePublicLink() {
    try {
        const response = await App.post('/api/public-links/create', {
            project_id: <?php echo $projectId; ?>
        });
        
        const link = App.url + '/report?token=' + response.data.token;
        const container = document.getElementById('public-link-container');
        const input = document.getElementById('public-link-input');
        
        input.value = link;
        container.style.display = 'block';
        
        // Copiar para o clipboard de forma resiliente
        input.select();
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(link);
                App.showToast('Link seguro gerado e copiado para o clipboard!', 'success');
            } else {
                const successful = document.execCommand('copy');
                if (successful) {
                    App.showToast('Link seguro gerado e copiado para o clipboard!', 'success');
                } else {
                    App.showToast('Link seguro gerado! Copie-o manualmente da caixa abaixo.', 'warning');
                }
            }
        } catch (clipErr) {
            console.warn('Falha ao copiar automaticamente:', clipErr);
            App.showToast('Link seguro gerado! Copie-o manualmente da caixa abaixo.', 'warning');
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao gerar link público.', 'danger');
    }
}

function openEditProjectModal() {
    App.showModal('edit-project-modal');
}

async function submitEditProject() {
    const btn = document.getElementById('edit-project-submit');
    const title = document.getElementById('edit-project-title').value.trim();
    const description = document.getElementById('edit-project-description').value.trim();
    const budget = document.getElementById('edit-project-budget').value;
    const location = document.getElementById('edit-project-location').value.trim();
    const start_date = document.getElementById('edit-project-start-date').value;
    const end_date = document.getElementById('edit-project-end-date').value;
    const status = document.getElementById('edit-project-status').value;
    const is_public = document.getElementById('edit-project-is-public').checked ? 1 : 0;
    const coverInput = document.getElementById('edit-project-cover');

    if (!title || !budget || !location) {
        App.showToast('Por favor, preencha todos os campos obrigatórios (*).', 'warning');
        return;
    }

    App.setLoading(btn, true);

    const formData = new FormData();
    formData.append('project_id', <?php echo $projectId; ?>);
    formData.append('title', title);
    formData.append('description', description);
    formData.append('budget', budget);
    formData.append('location', location);
    formData.append('start_date', start_date);
    formData.append('end_date', end_date);
    formData.append('status', status);
    formData.append('is_public', is_public);
    formData.append('_token', App.csrfToken);

    if (coverInput.files.length > 0) {
        formData.append('cover', coverInput.files[0]);
    }

    try {
        await App.upload('/api/projects/update', formData);
        App.showToast('Obra atualizada com sucesso!', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao atualizar obra.', 'danger');
        App.setLoading(btn, false);
    }
}

function confirmDeleteProject() {
    App.openConfirm('ATENÇÃO: Tem a certeza absoluta de que deseja ELIMINAR esta obra? Todos os dados (despesas, equipa, fundo) serão perdidos permanentemente!', async () => {
        try {
            await App.delete('/api/projects/delete', {
                project_id: <?php echo $projectId; ?>,
                confirm: 1,
                _token: App.csrfToken
            });
            App.showToast('Obra eliminada com sucesso.', 'success');
            setTimeout(() => window.location.href = '/projects', 1000);
        } catch (e) {
            App.showToast(e.message || 'Erro ao eliminar obra.', 'danger');
        }
    });
}
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
