<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

middleware_require_auth();

$query = input('q') ? trim((string)input('q')) : '';
$user = current_user();

$title = 'Pesquisa Global — Constrói Já';
require_once __DIR__ . '/../templates/header.php';

$db = db();

$users = [];
$projects = [];

if ($query !== '') {
    try {
        // 1. Procurar Perfis
        $users = $db->fetchAll(
            "SELECT id, name, username, avatar_url, bio, is_verified 
             FROM profiles 
             WHERE (name LIKE ? OR username LIKE ?) AND status = 'active'
             LIMIT 20",
            ['%' . $query . '%', '%' . $query . '%']
        );

        // 2. Procurar Projetos onde sou membro
        $projects = $db->fetchAll(
            "SELECT p.*, pm.role 
             FROM projects p
             JOIN project_managers pm ON p.id = pm.project_id
             WHERE pm.user_id = ? AND (p.title LIKE ? OR p.description LIKE ?)
             ORDER BY p.created_at DESC",
            [$user['id'], '%' . $query . '%', '%' . $query . '%']
        );
    } catch (PDOException $e) {
        // Erro silencioso ou logado
    }
}
?>

<div style="display:flex; flex-direction:column; gap:30px;">
    
    <div>
        <h2>Resultados da Pesquisa</h2>
        <p style="color:var(--text-secondary); font-size:14px; margin-top:4px;">
            A procurar por: <strong style="color:var(--accent-primary);">"<?php echo sanitize($query); ?>"</strong>
        </p>
    </div>

    <!-- Barra de Pesquisa Manual Principal -->
    <div class="card" style="padding:16px;">
        <form method="GET" action="/search" style="display:flex; gap:12px;">
            <input type="text" name="q" value="<?php echo sanitize($query); ?>" class="form-control" placeholder="Pesquise por engenheiros, construtoras ou obras específicas..." style="flex:1;" required>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search"></i>
                Procurar
            </button>
        </form>
    </div>

    <?php if ($query === ''): ?>
        <div class="card text-center" style="padding:60px; color:var(--text-secondary);">
            <i data-lucide="search" style="width:48px; height:48px; margin-bottom:16px; color:var(--text-muted); display:inline-block;"></i>
            <h3>Iniciar Pesquisa</h3>
            <p style="font-size:14px; margin-top:8px;">Insira um termo acima para pesquisar utilizadores e obras.</p>
        </div>
    <?php else: ?>
        
        <!-- COLUNAS DE RESULTADOS -->
        <div style="display:grid; grid-template-columns: 1fr; gap:30px;">
            
            <!-- SEÇÃO DE UTILIZADORES -->
            <div>
                <h3 style="margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="users" style="color:var(--accent-primary);"></i>
                    Utilizadores (<?php echo count($users); ?>)
                </h3>
                
                <?php if (count($users) === 0): ?>
                    <div class="card" style="color:var(--text-secondary); text-align:center; padding:30px;">
                        Sem utilizadores correspondentes encontrados.
                    </div>
                <?php else: ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px;">
                        <?php foreach ($users as $u): ?>
                            <div class="card" style="display:flex; flex-direction:column; justify-content:space-between; gap:16px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <img src="<?php echo get_avatar_url($u['avatar_url'], $u['name']); ?>" class="avatar avatar-md">
                                    <div style="overflow:hidden;">
                                        <h4 style="font-size:15px; white-space:nowrap; text-overflow:ellipsis; overflow:hidden; display:flex; align-items:center; gap:4px;">
                                            <?php echo sanitize($u['name']); ?>
                                            <?php if ($u['is_verified']): ?>
                                            <i data-lucide="check-circle-2" style="width:14px; height:14px; color:var(--accent-secondary); fill:var(--accent-secondary); --lucide-stroke: #0a0f1e;"></i>
                                            <?php endif; ?>
                                        </h4>
                                        <small style="color:var(--text-secondary);">@<?php echo sanitize($u['username']); ?></small>
                                    </div>
                                </div>
                                <p style="font-size:13px; color:var(--text-secondary); line-height:1.4; height:40px; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                                    <?php echo sanitize($u['bio'] ?? 'Sem bio descritiva.'); ?>
                                </p>
                                <a href="/profile/<?php echo sanitize($u['username']); ?>" class="btn btn-secondary" style="font-size:12px; padding:8px 12px; text-align:center; width:100%;">
                                    Ver Perfil Completo
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SEÇÃO DE OBRAS / PROJETOS -->
            <div style="margin-top:10px;">
                <h3 style="margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="briefcase" style="color:var(--accent-secondary);"></i>
                    Minhas Obras Correspondentes (<?php echo count($projects); ?>)
                </h3>
                
                <?php if (count($projects) === 0): ?>
                    <div class="card" style="color:var(--text-secondary); text-align:center; padding:30px;">
                        Sem obras associadas correspondentes encontradas.
                    </div>
                <?php else: ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
                        <?php foreach ($projects as $p): ?>
                            <div class="card" style="display:flex; flex-direction:column; justify-content:space-between; gap:16px;">
                                <div>
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                                        <h4 style="font-size:16px;"><?php echo sanitize($p['title']); ?></h4>
                                        <span class="badge badge-info"><?php echo sanitize($p['role']); ?></span>
                                    </div>
                                    <small style="color:var(--text-muted); display:flex; align-items:center; gap:4px; margin-bottom:10px;">
                                        <i data-lucide="map-pin" style="width:12px; height:12px;"></i>
                                        <?php echo sanitize($p['location'] ?? 'Localização não definida'); ?>
                                    </small>
                                    <p style="font-size:13px; color:var(--text-secondary); line-height:1.4;">
                                        <?php echo sanitize($p['description'] ?? 'Sem descrição da obra.'); ?>
                                    </p>
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <a href="/projects/detail?id=<?php echo $p['id']; ?>" class="btn btn-secondary" style="flex:1; font-size:12px; padding:8px;">Timeline</a>
                                    <a href="/projects/financials?id=<?php echo $p['id']; ?>" class="btn btn-primary" style="flex:1; font-size:12px; padding:8px;">Finanças</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
