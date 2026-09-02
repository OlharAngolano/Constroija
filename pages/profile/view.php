<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Rota opcionalmente autenticada, mas necessita de auth para interações (seguir, enviar mensagem)
$currentUser = current_user();
$username = $_GET['username'] ?? '';

$db = db();

try {
    // Obter dados do utilizador do perfil
    $profile = $db->fetch("SELECT * FROM profiles WHERE username = ?", [$username]);
    
    if (!$profile) {
        // Redireciona ou mostra erro 404
        http_response_code(404);
        require_once __DIR__ . '/../404.php';
        exit;
    }

    // Incrementar visualizações do portfólio/perfil
    $db->execute("UPDATE profiles SET portfolio_views = portfolio_views + 1 WHERE id = ?", [$profile['id']]);

    // Contadores
    $followersCount = (int)$db->fetch("SELECT COUNT(*) AS total FROM followers WHERE following_id = ?", [$profile['id']])['total'];
    $followingCount = (int)$db->fetch("SELECT COUNT(*) AS total FROM followers WHERE follower_id = ?", [$profile['id']])['total'];
    
    // Verificar se o utilizador logado já segue este utilizador
    $isFollowing = false;
    if ($currentUser) {
        $followCheck = $db->fetch("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?", [$currentUser['id'], $profile['id']]);
        $isFollowing = $followCheck !== null;
    }

    // Projetos públicos deste utilizador
    $publicProjects = $db->fetchAll(
        "SELECT * FROM projects WHERE user_id = ? AND is_public = 1 ORDER BY created_at DESC",
        [$profile['id']]
    );

    // Decode do portfólio
    $portfolio = [];
    if (!empty($profile['portfolio_data'])) {
        $portfolio = json_decode($profile['portfolio_data'], true) ?? [];
    }

} catch (PDOException $e) {
    die("Erro ao carregar perfil público: " . $e->getMessage());
}

$title = "Perfil de " . sanitize($profile['name']) . " — Constrói Já";
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- Cabeçalho do Perfil (Glassmorphism premium) -->
    <div class="card" style="position: relative; overflow: hidden; padding: 0;">
        <!-- Banner decorativo com gradiente vibrante -->
        <div style="height: 120px; background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); opacity: 0.15;"></div>
        
        <div style="padding: 24px; display: flex; flex-direction: column; gap: 20px; margin-top: -60px;">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px;">
                <!-- Avatar e Identificação -->
                <div style="display: flex; align-items: flex-end; gap: 20px; flex-wrap: wrap;">
                    <img src="<?php echo get_avatar_url($profile['avatar_url'] ?? null, $profile['name']); ?>" 
                         alt="<?php echo sanitize($profile['name']); ?>" 
                         style="width: 120px; height: 120px; border-radius: var(--radius-md); border: 4px solid var(--card-bg); background: var(--card-bg); object-fit: cover; box-shadow: var(--shadow-lg);">
                    
                    <div style="margin-bottom: 8px;">
                        <h2 style="display: flex; align-items: center; gap: 8px; font-weight: 800;">
                            <?php echo sanitize($profile['name']); ?>
                            <?php if ((int)($profile['is_verified'] ?? 0) === 1): ?>
                                <i data-lucide="verified" style="color: var(--accent-secondary); width: 20px; height: 20px; fill: rgba(59,130,246,0.2);"></i>
                            <?php endif; ?>
                        </h2>
                        <p style="color: var(--text-muted); font-size: 14px;">@<?php echo sanitize($profile['username'] ?? ''); ?></p>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div style="display: flex; gap: 12px; margin-bottom: 8px;">
                    <?php if ($currentUser && $currentUser['id'] !== $profile['id']): ?>
                        <!-- Seguir / Deixar de seguir -->
                        <button id="follow-btn" onclick="toggleFollow(<?php echo $profile['id']; ?>)" class="btn <?php echo $isFollowing ? 'btn-secondary' : 'btn-primary'; ?>" style="padding: 8px 18px; font-size: 13px;">
                            <i id="follow-icon" data-lucide="<?php echo $isFollowing ? 'user-check' : 'user-plus'; ?>" style="width:16px; height:16px;"></i>
                            <span id="follow-text"><?php echo $isFollowing ? 'A Seguir' : 'Seguir'; ?></span>
                        </button>
                        
                        <!-- Enviar Mensagem -->
                        <a href="/messages?recipient_id=<?php echo $profile['id']; ?>" class="btn btn-secondary" style="padding: 8px 18px; font-size: 13px;">
                            <i data-lucide="message-square" style="width:16px; height:16px;"></i>
                            Mensagem
                        </a>
                    <?php elseif (!$currentUser): ?>
                        <a href="/login" class="btn btn-primary" style="padding: 8px 18px; font-size: 13px;">
                            <i data-lucide="user-plus" style="width:16px; height:16px;"></i>
                            Seguir
                        </a>
                    <?php else: ?>
                        <!-- Próprio perfil -->
                        <a href="/profile/settings" class="btn btn-secondary" style="padding: 8px 18px; font-size: 13px;">
                            <i data-lucide="edit" style="width:16px; height:16px;"></i>
                            Editar Perfil
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bio, Localização e Redes -->
            <div>
                <?php if (!empty($profile['bio'])): ?>
                    <p style="line-height: 1.6; margin-bottom: 16px; font-size: 15px; color: var(--text-primary);"><?php echo sanitize($profile['bio']); ?></p>
                <?php endif; ?>

                <div style="display: flex; flex-wrap: wrap; gap: 20px; font-size: 13px; color: var(--text-secondary);">
                    <?php if (!empty($profile['location'])): ?>
                        <span style="display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="map-pin" style="width: 16px; height: 16px; color: var(--accent-primary);"></i>
                            <?php echo sanitize($profile['location']); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($profile['whatsapp'])): ?>
                        <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $profile['whatsapp']); ?>" target="_blank" style="display: flex; align-items: center; gap: 6px; color: var(--text-secondary); text-decoration: none;">
                            <i data-lucide="phone" style="width: 16px; height: 16px; color: #22c55e;"></i>
                            WhatsApp: <?php echo sanitize($profile['whatsapp']); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($profile['website'])): ?>
                        <a href="<?php echo sanitize($profile['website']); ?>" target="_blank" style="display: flex; align-items: center; gap: 6px; color: var(--text-secondary); text-decoration: none;">
                            <i data-lucide="globe" style="width: 16px; height: 16px; color: var(--accent-secondary);"></i>
                            <?php echo sanitize($profile['website']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Estatísticas e Seguidores -->
            <div style="display: flex; gap: 24px; border-top: 1px solid var(--border-color); padding-top: 16px; font-size: 14px;">
                <div>
                    <strong style="color: var(--text-primary);" id="followers-count"><?php echo $followersCount; ?></strong>
                    <span style="color: var(--text-muted);"> Seguidores</span>
                </div>
                <div>
                    <strong style="color: var(--text-primary);"><?php echo $followingCount; ?></strong>
                    <span style="color: var(--text-muted);"> A seguir</span>
                </div>
                <div>
                    <strong style="color: var(--text-primary);"><?php echo (int)($profile['portfolio_views'] ?? 0); ?></strong>
                    <span style="color: var(--text-muted);"> Visualizações</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Conteúdo em Duas Colunas: Portfólio Técnico e Obras Públicas -->
    <div style="display: grid; grid-template-columns: 1fr; gap: 24px;">
        
        <!-- Portfólio Profissional (se configurado) -->
        <?php if (!empty($portfolio['title']) || !empty($portfolio['description']) || !empty($portfolio['skills'])): ?>
            <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
                        <i data-lucide="briefcase"></i>
                        Montra Profissional
                    </h3>
                    <a href="/portfolio/<?php echo sanitize($profile['username'] ?? ''); ?>" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">
                        <i data-lucide="folder-git-2" style="width:14px; height:14px;"></i>
                        Ver Portfólio Completo
                    </a>
                </div>

                <?php if (!empty($portfolio['title'])): ?>
                    <div>
                        <h4 style="color: var(--text-primary); margin-bottom: 4px; font-weight: 700; font-size: 16px;"><?php echo sanitize($portfolio['title']); ?></h4>
                        <?php if (!empty($portfolio['experience'])): ?>
                            <span style="font-size: 13px; color: var(--accent-secondary); font-weight: 600;"><?php echo sanitize($portfolio['experience']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($portfolio['description'])): ?>
                    <div>
                        <p style="color: var(--text-secondary); line-height: 1.7; font-size: 14px; white-space: pre-line;"><?php echo sanitize($portfolio['description']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($portfolio['skills'])): ?>
                    <div>
                        <h5 style="color: var(--text-muted); font-size: 13px; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Especialidades Civis</h5>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($portfolio['skills'] as $skill): ?>
                                <span class="badge" style="background: rgba(249,115,22,0.08); color: var(--accent-primary); border: 1px solid rgba(249,115,22,0.15); padding: 4px 10px; border-radius: var(--radius-sm); font-size: 12px;">
                                    <?php echo sanitize($skill); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Obras e Projetos Públicos -->
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <h3 style="display: flex; align-items: center; gap: 8px; font-weight: 700; margin-left: 4px;">
                <i data-lucide="hard-hat" style="color: var(--accent-secondary);"></i>
                Obras Ativas e Portfólio (<?php echo count($publicProjects); ?>)
            </h3>

            <?php if (empty($publicProjects)): ?>
                <div class="card" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <i data-lucide="folder-open" style="width: 48px; height: 48px; stroke-width: 1; margin: 0 auto 12px; color: var(--text-muted);"></i>
                    <p style="font-size: 15px;">Este construtor ainda não tem projetos públicos no Constrói Já.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($publicProjects as $project): ?>
                        <div class="card project-card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 100%;">
                            <!-- Cover Image -->
                            <div style="height: 140px; background: <?php echo !empty($project['cover_image_url']) ? "url('" . APP_URL . '/' . $project['cover_image_url'] . "') center/cover no-repeat" : "linear-gradient(135deg, rgba(26,34,53,0.8) 0%, rgba(10,15,30,0.9) 100%)"; ?>; position: relative;">
                                <?php 
                                $statusColors = [
                                    'planning' => 'rgba(59,130,246,0.15)',
                                    'active' => 'rgba(249,115,22,0.15)',
                                    'paused' => 'rgba(239,68,68,0.15)',
                                    'completed' => 'rgba(34,197,94,0.15)'
                                ];
                                $statusTextColors = [
                                    'planning' => '#3b82f6',
                                    'active' => '#f97316',
                                    'paused' => '#ef4444',
                                    'completed' => '#22c55e'
                                ];
                                $statusLabels = [
                                    'planning' => 'Planeamento',
                                    'active' => 'Em Obra',
                                    'paused' => 'Pausada',
                                    'completed' => 'Concluída'
                                ];
                                $status = $project['status'] ?? 'planning';
                                ?>
                                <span class="badge" style="position: absolute; top: 12px; right: 12px; background: <?php echo $statusColors[$status] ?? 'var(--border-color)'; ?>; color: <?php echo $statusTextColors[$status] ?? 'var(--text-muted)'; ?>; border: 1px solid <?php echo $statusTextColors[$status] ?? 'var(--border-color)'; ?>; font-size:11px; padding:3px 8px; border-radius:var(--radius-sm);">
                                    <?php echo $statusLabels[$status] ?? $status; ?>
                                </span>
                            </div>

                            <div style="padding: 20px; display: flex; flex-direction: column; gap: 12px; flex-grow: 1;">
                                <h4 style="font-size: 16px; font-weight: 700; margin-bottom: 2px;">
                                    <?php echo sanitize($project['title']); ?>
                                </h4>
                                
                                <?php if (!empty($project['description'])): ?>
                                    <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 38px;">
                                        <?php echo sanitize($project['description']); ?>
                                    </p>
                                <?php else: ?>
                                    <p style="font-size: 13px; color: var(--text-muted); font-style: italic; height: 38px;">Sem descrição fornecida.</p>
                                <?php endif; ?>

                                <div style="display: flex; flex-direction: column; gap: 8px; border-top: 1px solid var(--border-color); padding-top: 12px; font-size: 13px; color: var(--text-secondary);">
                                    <?php if (!empty($project['location'])): ?>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <i data-lucide="map-pin" style="width: 14px; height: 14px; color: var(--accent-primary);"></i>
                                            <span><?php echo sanitize($project['location']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <i data-lucide="piggy-bank" style="width: 14px; height: 14px; color: var(--accent-secondary);"></i>
                                        <span>Orçamento: <?php echo format_currency((float)$project['budget'], $profile['currency'] ?? 'AOA'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function toggleFollow(targetId) {
    const btn = document.getElementById('follow-btn');
    const textSpan = document.getElementById('follow-text');
    const iconI = document.getElementById('follow-icon');
    const countSpan = document.getElementById('followers-count');
    
    if (!btn) return;
    
    App.setLoading(btn, true);
    
    try {
        const res = await App.post('/api/followers/follow', {
            following_id: targetId
        });
        
        if (res.success) {
            const isFollowing = res.data.following;
            countSpan.textContent = res.data.followers_count;
            
            if (isFollowing) {
                btn.className = "btn btn-secondary";
                textSpan.textContent = "A Seguir";
                iconI.setAttribute('data-lucide', 'user-check');
            } else {
                btn.className = "btn btn-primary";
                textSpan.textContent = "Seguir";
                iconI.setAttribute('data-lucide', 'user-plus');
            }
            
            // Re-renderizar o ícone com Lucide
            if (window.lucide) {
                window.lucide.createIcons();
            }
            
            App.showToast(res.message, 'success');
        } else {
            App.showToast(res.error || 'Erro ao processar ação.', 'danger');
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao conectar à API.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
