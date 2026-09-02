<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Exige permissões de administrador
middleware_require_admin();

$user = current_user();
$db = db();

try {
    // Carregar estatísticas gerais
    $totalUsers = (int)$db->fetch("SELECT COUNT(*) as total FROM profiles")['total'];
    $activeUsers = (int)$db->fetch("SELECT COUNT(*) as total FROM profiles WHERE status = 'active'")['total'];
    $suspendedUsers = (int)$db->fetch("SELECT COUNT(*) as total FROM profiles WHERE status = 'suspended'")['total'];
    
    $totalProjects = (int)$db->fetch("SELECT COUNT(*) as total FROM projects")['total'];
    
    $totalExpensesCount = (int)$db->fetch("SELECT COUNT(*) as total FROM expenses WHERE deleted_at IS NULL")['total'];
    $totalSpent = (float)$db->fetch("SELECT SUM(price * quantity) as total FROM expenses WHERE deleted_at IS NULL")['total'];

    $totalPosts = (int)$db->fetch("SELECT COUNT(*) as total FROM posts")['total'];
    $totalQuestions = (int)$db->fetch("SELECT COUNT(*) as total FROM questions")['total'];

    // Obter últimos 5 utilizadores registados
    $recentUsers = $db->fetchAll(
        "SELECT id, name, username, email, status, created_at FROM profiles ORDER BY created_at DESC LIMIT 5"
    );

    // Obter utilizadores online ativos nos últimos 5 minutos
    $onlineUsers = $db->fetchAll(
        "SELECT id, name, username, email, avatar_url, last_activity_at 
         FROM profiles 
         WHERE last_activity_at >= NOW() - INTERVAL 5 MINUTE 
         ORDER BY last_activity_at DESC"
    );

} catch (PDOException $e) {
    die("Erro ao carregar dados de administração: " . $e->getMessage());
}

$title = 'Painel de Administração — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- Topo Admin -->
    <div class="admin-header-flex" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2 style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="shield" style="color:var(--accent-secondary); width:28px; height:28px;"></i>
                Painel de Administração
            </h2>
            <p style="color:var(--text-secondary); font-size:14px;">Visão geral da atividade da plataforma, moderação e gestão de contas.</p>
        </div>
        
        <!-- Sub-navegação interna de Admin -->
        <div class="admin-subnav">
            <a href="/admin" class="btn btn-primary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="bar-chart-3" style="width:16px; height:16px;"></i>
                Geral
            </a>
            <a href="/admin/users" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="users" style="width:16px; height:16px;"></i>
                Utilizadores
            </a>
            <a href="/admin/moderation" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="message-square" style="width:16px; height:16px;"></i>
                Moderação
            </a>
            <a href="/admin/partners" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="shopping-bag" style="width:16px; height:16px;"></i>
                Parceiros B2B
            </a>
        </div>
    </div>

    <!-- Grid de Métricas Principais (Aesthetics: Glassmorphism com glows) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
        
        <!-- Card 1: Utilizadores -->
        <div class="card" style="display: flex; align-items: center; gap: 16px; position: relative; overflow: hidden;">
            <div style="background: rgba(59,130,246,0.1); color: var(--accent-secondary); padding: 12px; border-radius: var(--radius-sm);">
                <i data-lucide="users" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <span style="color:var(--text-muted); font-size:12px; font-weight:600; text-transform:uppercase;">Utilizadores</span>
                <h3 style="font-size:24px; font-weight:800; margin-top:2px;"><?php echo $totalUsers; ?></h3>
                <span style="font-size:11px; color:#22c55e; display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                    <?php echo $activeUsers; ?> ativos 
                    <span style="color:var(--text-muted);">|</span>
                    <span style="color:#22c55e; font-weight:700; display:inline-flex; align-items:center; gap:3px;">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#22c55e; animation: pulse 1.5s infinite;"></span>
                        <?php echo count($onlineUsers); ?> online
                    </span>
                </span>
            </div>
        </div>

        <!-- Card 2: Obras Activas -->
        <div class="card" style="display: flex; align-items: center; gap: 16px; position: relative; overflow: hidden;">
            <div style="background: rgba(249,115,22,0.1); color: var(--accent-primary); padding: 12px; border-radius: var(--radius-sm);">
                <i data-lucide="hard-hat" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <span style="color:var(--text-muted); font-size:12px; font-weight:600; text-transform:uppercase;">Obras Criadas</span>
                <h3 style="font-size:24px; font-weight:800; margin-top:2px;"><?php echo $totalProjects; ?></h3>
                <span style="font-size:11px; color:var(--text-secondary);">Projetos de engenharia</span>
            </div>
        </div>

        <!-- Card 3: Transações / Total Gasto -->
        <div class="card" style="display: flex; align-items: center; gap: 16px; position: relative; overflow: hidden;">
            <div style="background: rgba(34,197,94,0.1); color: #22c55e; padding: 12px; border-radius: var(--radius-sm);">
                <i data-lucide="trending-up" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <span style="color:var(--text-muted); font-size:12px; font-weight:600; text-transform:uppercase;">Movimentado</span>
                <h3 style="font-size:20px; font-weight:800; margin-top:4px;"><?php echo format_currency($totalSpent, 'AOA'); ?></h3>
                <span style="font-size:11px; color:var(--text-muted);"><?php echo $totalExpensesCount; ?> despesas registadas</span>
            </div>
        </div>

        <!-- Card 4: Social & Q&A -->
        <div class="card" style="display: flex; align-items: center; gap: 16px; position: relative; overflow: hidden;">
            <div style="background: rgba(168,85,247,0.1); color: #a855f7; padding: 12px; border-radius: var(--radius-sm);">
                <i data-lucide="help-circle" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <span style="color:var(--text-muted); font-size:12px; font-weight:600; text-transform:uppercase;">Social e Q&A</span>
                <h3 style="font-size:24px; font-weight:800; margin-top:2px;"><?php echo $totalPosts + $totalQuestions; ?></h3>
                <span style="font-size:11px; color:var(--text-muted);"><?php echo $totalPosts; ?> posts / <?php echo $totalQuestions; ?> perguntas</span>
            </div>
        </div>
    </div>

    <!-- Utilizadores Recém-Registados -->
    <div class="card" style="display: flex; flex-direction: column; gap: 16px;">
        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); display:flex; align-items:center; gap:8px;">
            <i data-lucide="clock" style="color: var(--accent-secondary); width: 18px; height: 18px;"></i>
            Utilizadores Registados Recentemente
        </h3>

        <div style="overflow-x: auto;">
            <table class="responsive-table" style="width: 100%; border-collapse: collapse; font-size: 14px; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        <th style="padding: 12px;">Nome</th>
                        <th style="padding: 12px;">Email</th>
                        <th style="padding: 12px;">Estado</th>
                        <th style="padding: 12px;">Data de Registo</th>
                        <th style="padding: 12px; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsers as $recent): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle;">
                            <td data-label="Nome" style="padding: 12px; font-weight: 600;">
                                <a href="/profile/<?php echo sanitize($recent['username'] ?? ''); ?>" target="_blank" style="color: var(--text-primary); text-decoration:none;">
                                    <?php echo sanitize($recent['name']); ?>
                                </a>
                                <div style="font-size:11px; color:var(--text-muted); font-weight:normal;">@<?php echo sanitize($recent['username'] ?? ''); ?></div>
                            </td>
                            <td data-label="Email" style="padding: 12px; color: var(--text-secondary);"><?php echo sanitize($recent['email']); ?></td>
                            <td data-label="Estado" style="padding: 12px;">
                                <?php if ($recent['status'] === 'active'): ?>
                                    <span class="badge" style="background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid #22c55e; font-size: 11px; padding: 2px 6px;">Ativo</span>
                                <?php elseif ($recent['status'] === 'suspended'): ?>
                                    <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid #ef4444; font-size: 11px; padding: 2px 6px;">Suspenso</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(249,115,22,0.1); color: var(--accent-primary); border: 1px solid var(--accent-primary); font-size: 11px; padding: 2px 6px;">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Data de Registo" style="padding: 12px; color: var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($recent['created_at'])); ?></td>
                            <td data-label="Ações" style="padding: 12px; text-align: right;">
                                <div style="display:flex; justify-content:flex-end; gap:8px;">
                                    <a href="/profile/<?php echo sanitize($recent['username'] ?? ''); ?>" target="_blank" class="btn btn-secondary" style="font-size:12px; padding:4px 8px;">
                                        <i data-lucide="eye" style="width:14px; height:14px;"></i>
                                    </a>
                                    <a href="/admin/users" class="btn btn-secondary" style="font-size:12px; padding:4px 8px;">
                                        <i data-lucide="settings" style="width:14px; height:14px;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Utilizadores Online Ativos (Admin Only) -->
    <div class="card" style="display: flex; flex-direction: column; gap: 16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); display:flex; align-items:center; gap:8px;">
                <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#22c55e; box-shadow: 0 0 10px #22c55e; animation: pulse 1.5s infinite;"></span>
                Utilizadores Online Ativos (<?php echo count($onlineUsers); ?>)
            </h3>
            <span style="font-size:12px; color:var(--text-muted);">Nos últimos 5 minutos</span>
        </div>

        <?php if (empty($onlineUsers)): ?>
            <p style="color:var(--text-secondary); font-size:13px; text-align:center; padding:24px 0;">Nenhum utilizador online neste momento.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="responsive-table" style="width: 100%; border-collapse: collapse; font-size: 14px; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            <th style="padding: 12px;">Nome</th>
                            <th style="padding: 12px;">Email</th>
                            <th style="padding: 12px;">Última Atividade</th>
                            <th style="padding: 12px; text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($onlineUsers as $online): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle;">
                                <td data-label="Nome" style="padding: 12px; font-weight: 600;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <img src="<?php echo get_avatar_url($online['avatar_url'], $online['name']); ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                                        <div>
                                            <a href="/profile/<?php echo sanitize($online['username'] ?? ''); ?>" target="_blank" style="color: var(--text-primary); text-decoration:none;">
                                                <?php echo sanitize($online['name']); ?>
                                            </a>
                                            <div style="font-size:11px; color:var(--text-muted); font-weight:normal;">@<?php echo sanitize($online['username'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Email" style="padding: 12px; color: var(--text-secondary);"><?php echo sanitize($online['email']); ?></td>
                                <td data-label="Última Atividade" style="padding: 12px; color: var(--text-muted);">
                                    <?php echo time_ago($online['last_activity_at']); ?>
                                </td>
                                <td data-label="Ações" style="padding: 12px; text-align: right;">
                                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                                        <a href="/profile/<?php echo sanitize($online['username'] ?? ''); ?>" target="_blank" class="btn btn-secondary" style="font-size:12px; padding:4px 8px;">
                                            <i data-lucide="eye" style="width:14px; height:14px;"></i>
                                        </a>
                                        <a href="/admin/users" class="btn btn-secondary" style="font-size:12px; padding:4px 8px;">
                                            <i data-lucide="settings" style="width:14px; height:14px;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style nonce="<?php echo Security::getNonce(); ?>">
    @keyframes pulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
</style>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
