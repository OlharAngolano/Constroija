<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Exige permissão de administrador
middleware_require_admin();

$user = current_user();
$db = db();

try {
    // Obter todas as publicações do feed para moderação (ordenadas por mais recente)
    $posts = $db->fetchAll(
        "SELECT p.*, pr.name, pr.username, pr.avatar_url 
         FROM posts p
         JOIN profiles pr ON p.user_id = pr.id
         ORDER BY p.created_at DESC"
    );

} catch (PDOException $e) {
    page_error('Erro ao carregar publicações para moderação: ', $e);
}

$title = 'Moderação de Conteúdos — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- Topo Admin -->
    <div class="admin-header-flex" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2>Fila de Moderação de Conteúdos</h2>
            <p style="color:var(--text-secondary); font-size:14px;">Monitorize e remova publicações que violem os termos profissionais da comunidade civil angolana.</p>
        </div>
        
        <!-- Navegação interna Admin -->
        <div class="admin-subnav">
            <a href="/admin" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="bar-chart-3" style="width:16px; height:16px;"></i>
                Geral
            </a>
            <a href="/admin/users" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="users" style="width:16px; height:16px;"></i>
                Utilizadores
            </a>
            <a href="/admin/moderation" class="btn btn-primary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="message-square" style="width:16px; height:16px;"></i>
                Moderação
            </a>
            <a href="/admin/partners" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="shopping-bag" style="width:16px; height:16px;"></i>
                Parceiros B2B
            </a>
        </div>
    </div>

    <!-- Lista de Publicações para Moderação -->
    <div style="display:flex; flex-direction:column; gap:16px;">
        <?php if (empty($posts)): ?>
            <div class="card" style="text-align:center; padding:60px 20px; color:var(--text-muted);">
                <i data-lucide="check-circle2" style="width:48px; height:48px; stroke-width:1; margin:0 auto 12px; color:#22c55e;"></i>
                <p style="font-size:16px; font-weight:600; margin-bottom:4px;">Nenhuma publicação no sistema</p>
                <p style="font-size:14px;">O feed está limpo e sem movimentações de momento.</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div id="post-moderation-card-<?php echo $post['id']; ?>" class="card" style="padding: 20px; display:flex; flex-direction:column; gap:16px; border-color: rgba(255,255,255,0.05); position:relative;">
                    
                    <!-- Cabeçalho do Post -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="<?php echo get_avatar_url($post['avatar_url'], $post['name']); ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                            <div>
                                <h4 style="font-weight:700; color:var(--text-primary); font-size:14px;">
                                    <?php echo sanitize($post['name']); ?>
                                </h4>
                                <span style="font-size:11px; color:var(--text-muted);">@<?php echo sanitize($post['username']); ?> • <?php echo time_ago($post['created_at']); ?></span>
                            </div>
                        </div>

                        <!-- Botão de exclusão imediata (Moderador) -->
                        <button onclick="deletePostByModerator(<?php echo $post['id']; ?>)" 
                                id="btn-delete-<?php echo $post['id']; ?>" 
                                class="btn btn-primary" 
                                style="background:rgba(239,68,68,0.1); color:#ef4444; border-color:rgba(239,68,68,0.2); font-size:12px; padding:6px 12px;">
                            <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                            Remover Post
                        </button>
                    </div>

                    <!-- Corpo do Post -->
                    <div>
                        <p style="color:var(--text-primary); font-size:14px; line-height:1.6; white-space:pre-line;">
                            <?php echo sanitize($post['content'] ?? ''); ?>
                        </p>
                    </div>

                    <!-- Ficheiro em Anexo se existir -->
                    <?php if ($post['file_url']): ?>
                        <div style="border-radius: var(--radius-sm); overflow:hidden; border:1px solid var(--border-color); max-height:300px; display:flex; justify-content:center; background:rgba(0,0,0,0.1);">
                            <?php if (in_array(strtolower(pathinfo($post['file_url'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                <img src="<?php echo APP_URL . '/' . $post['file_url']; ?>" style="max-width:100%; height:auto; object-fit:contain;">
                            <?php else: ?>
                                <div style="padding:20px; display:flex; align-items:center; gap:10px; color:var(--text-secondary);">
                                    <i data-lucide="file-text" style="width:24px; height:24px; color:var(--accent-secondary);"></i>
                                    <a href="<?php echo APP_URL . '/' . $post['file_url']; ?>" target="_blank" style="color:var(--accent-secondary); text-decoration:none; font-weight:600; font-size:13px;">
                                        Documento em Anexo (<?php echo strtoupper(pathinfo($post['file_url'], PATHINFO_EXTENSION)); ?>)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function deletePostByModerator(postId) {
    const btn = document.getElementById(`btn-delete-${postId}`);
    if (!btn) return;

    if (!confirm("Tem a certeza de que deseja eliminar definitivamente esta publicação como moderador? Esta ação não pode ser revertida.")) {
        return;
    }

    App.setLoading(btn, true);

    try {
        const res = await App.post('/api/posts/delete', {
            post_id: postId
        });

        if (res.success) {
            App.showToast('Publicação removida com sucesso!', 'success');
            
            // Suave fade out do elemento
            const card = document.getElementById(`post-moderation-card-${postId}`);
            if (card) {
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateY(-20px)';
                setTimeout(() => card.remove(), 400);
            }
        } else {
            App.showToast(res.error || 'Erro ao tentar eliminar post.', 'danger');
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao comunicar com a API.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
