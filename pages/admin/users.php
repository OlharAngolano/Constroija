<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Exige permissão de administrador
middleware_require_admin();

$user = current_user();
$db = db();

$searchQuery = trim((string)input('search', ''));

try {
    if ($searchQuery !== '') {
        $sql = "SELECT id, name, username, email, status, is_verified, is_admin, created_at, last_activity_at 
                FROM profiles 
                WHERE name LIKE :q1 OR email LIKE :q2 OR username LIKE :q3
                ORDER BY created_at DESC";
        $likeVal = '%' . $searchQuery . '%';
        $params = ['q1' => $likeVal, 'q2' => $likeVal, 'q3' => $likeVal];
    } else {
        $sql = "SELECT id, name, username, email, status, is_verified, is_admin, created_at, last_activity_at 
                FROM profiles 
                ORDER BY created_at DESC";
        $params = [];
    }

    $profiles = $db->fetchAll($sql, $params);

} catch (PDOException $e) {
    die("Erro ao pesquisar utilizadores: " . $e->getMessage());
}

$title = 'Gestão de Utilizadores — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- Topo Admin -->
    <div class="admin-header-flex" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2>Gestão de Contas de Utilizadores</h2>
            <p style="color:var(--text-secondary); font-size:14px;">Suspenda, ative ou monitorize perfis de empreiteiros, especialistas ou particulares.</p>
        </div>
        
        <!-- Navegação interna Admin -->
        <div class="admin-subnav">
            <a href="/admin" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="bar-chart-3" style="width:16px; height:16px;"></i>
                Geral
            </a>
            <a href="/admin/users" class="btn btn-primary" style="font-size:13px; padding: 8px 16px;">
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

    <!-- Barra de Pesquisa de Utilizadores -->
    <div class="card" style="padding: 16px;">
        <form method="GET" action="/admin/users" class="admin-search-form" style="display:flex; gap:12px;">
            <div style="position:relative; flex-grow:1;">
                <input type="text" name="search" class="form-control" placeholder="Procurar por nome, email ou username..." value="<?php echo sanitize($searchQuery); ?>" style="width:100%; padding-left:40px;">
                <i data-lucide="search" style="position:absolute; left:12px; top:11px; color:var(--text-muted); width:18px; height:18px;"></i>
            </div>
            <button type="submit" class="btn btn-secondary" style="padding: 0 20px;">Pesquisar</button>
            <?php if ($searchQuery !== ''): ?>
                <a href="/admin/users" class="btn btn-secondary" style="display:flex; align-items:center; justify-content:center; padding: 0 16px;"><i data-lucide="x" style="width:16px; height:16px;"></i> Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabela Geral de Utilizadores -->
    <div class="card" style="padding: 20px;">
        <div style="overflow-x: auto;">
            <table class="responsive-table" style="width: 100%; border-collapse: collapse; font-size: 14px; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        <th style="padding: 12px;">Perfil</th>
                        <th style="padding: 12px;">Email</th>
                        <th style="padding: 12px;">Tipo</th>
                        <th style="padding: 12px;">Estado</th>
                        <th style="padding: 12px;">Data Registo</th>
                        <th style="padding: 12px;">Último Acesso</th>
                        <th style="padding: 12px; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($profiles)): ?>
                        <tr>
                            <td colspan="7" style="padding: 30px; text-align: center; color: var(--text-muted);">
                                Nenhum utilizador corresponde à sua pesquisa.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($profiles as $profile): 
                            $isOnline = false;
                            if (!empty($profile['last_activity_at'])) {
                                $isOnline = (time() - strtotime($profile['last_activity_at'])) <= 300;
                            }
                        ?>
                            <tr id="row-user-<?php echo $profile['id']; ?>" style="border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle;">
                                <td data-label="Perfil" style="padding: 12px; font-weight: 600;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="font-weight: 700;">
                                            <a href="/profile/<?php echo sanitize($profile['username'] ?? ''); ?>" target="_blank" style="color:var(--text-primary); text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                                <?php echo sanitize($profile['name']); ?>
                                                <?php if ($isOnline): ?>
                                                    <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#22c55e; box-shadow:0 0 6px #22c55e; animation: pulse 1.5s infinite;" title="Online agora"></span>
                                                <?php endif; ?>
                                            </a>
                                            <div style="font-size:11px; color:var(--text-muted); font-weight:normal;">@<?php echo sanitize($profile['username'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Email" style="padding: 12px; color: var(--text-secondary);"><?php echo sanitize($profile['email']); ?></td>
                                <td data-label="Tipo" style="padding: 12px;">
                                    <?php if ((int)$profile['is_admin'] === 1): ?>
                                        <span class="badge" style="background: rgba(168,85,247,0.1); color: #a855f7; border: 1px solid #a855f7; font-size:11px;">Administrador</span>
                                    <?php elseif ((int)$profile['is_verified'] === 1): ?>
                                        <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--accent-secondary); border: 1px solid var(--accent-secondary); font-size:11px;">Verificado</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(255,255,255,0.03); color: var(--text-muted); border: 1px solid var(--border-color); font-size:11px;">Membro</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Estado" style="padding: 12px;" id="status-cell-<?php echo $profile['id']; ?>">
                                    <?php if ($profile['status'] === 'active'): ?>
                                        <span class="badge" style="background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid #22c55e; font-size: 11px;">Ativo</span>
                                    <?php elseif ($profile['status'] === 'suspended'): ?>
                                        <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid #ef4444; font-size: 11px;">Suspenso</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(249,115,22,0.1); color: var(--accent-primary); border: 1px solid var(--accent-primary); font-size: 11px;">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Data Registo" style="padding: 12px; color: var(--text-muted); font-size: 13px;">
                                    <?php echo date('d/m/Y', strtotime($profile['created_at'])); ?>
                                </td>
                                <td data-label="Último Acesso" style="padding: 12px; color: var(--text-muted); font-size: 13px;">
                                    <?php echo !empty($profile['last_activity_at']) ? time_ago($profile['last_activity_at']) : '<span style="color:var(--text-muted); font-style:italic;">Nunca</span>'; ?>
                                </td>
                                <td data-label="Ações" style="padding: 12px; text-align: right;">
                                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                                        <!-- Impedir que o administrador suspenda a si próprio -->
                                        <?php if ($profile['id'] !== $user['id'] && (int)$profile['is_admin'] !== 1): ?>
                                            <button id="btn-toggle-<?php echo $profile['id']; ?>" 
                                                    onclick="toggleUserStatus(<?php echo $profile['id']; ?>, '<?php echo $profile['status'] === 'active' ? 'suspended' : 'active'; ?>')" 
                                                    class="btn <?php echo $profile['status'] === 'active' ? 'btn-secondary' : 'btn-primary'; ?>" 
                                                    style="font-size:12px; padding:6px 12px;">
                                                <i id="icon-toggle-<?php echo $profile['id']; ?>" data-lucide="<?php echo $profile['status'] === 'active' ? 'user-x' : 'user-check'; ?>" style="width:14px; height:14px;"></i>
                                                <span id="text-toggle-<?php echo $profile['id']; ?>"><?php echo $profile['status'] === 'active' ? 'Suspender' : 'Ativar'; ?></span>
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size:12px; color:var(--text-muted); font-style:italic;">Sem Ações</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function toggleUserStatus(userId, newStatus) {
    const btn = document.getElementById(`btn-toggle-${userId}`);
    const textSpan = document.getElementById(`text-toggle-${userId}`);
    const icon = document.getElementById(`icon-toggle-${userId}`);
    const cell = document.getElementById(`status-cell-${userId}`);

    if (!btn) return;

    let months = 1;
    if (newStatus === 'active') {
        const inputMonths = prompt("Quantos meses de subscrição VIP deseja conceder a este utilizador?", "1");
        if (inputMonths === null) {
            return; // Abortar se clicar em cancelar
        }
        months = parseInt(inputMonths);
        if (isNaN(months) || months <= 0) {
            App.showToast("Número de meses inválido. Assumido 1 mês por padrão.", "warning");
            months = 1;
        }
    }

    App.setLoading(btn, true);

    try {
        const res = await App.post('/api/admin/toggle-user', {
            user_id: userId,
            status: newStatus,
            months: months
        });

        if (res.success) {
            App.showToast(res.message, 'success');
            
            // Atualizar UI
            if (newStatus === 'active') {
                cell.innerHTML = '<span class="badge" style="background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid #22c55e; font-size: 11px;">Ativo</span>';
                btn.className = "btn btn-secondary";
                textSpan.textContent = "Suspender";
                icon.setAttribute('data-lucide', 'user-x');
                // Alterar callback para o próximo clique
                btn.setAttribute('onclick', `toggleUserStatus(${userId}, 'suspended')`);
            } else {
                cell.innerHTML = '<span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid #ef4444; font-size: 11px;">Suspenso</span>';
                btn.className = "btn btn-primary";
                textSpan.textContent = "Ativar";
                icon.setAttribute('data-lucide', 'user-check');
                // Alterar callback para o próximo clique
                btn.setAttribute('onclick', `toggleUserStatus(${userId}, 'active')`);
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } else {
            App.showToast(res.error || 'Erro ao processar alteração.', 'danger');
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao comunicar com a API.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}
</script>

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
