<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

// Rota estritamente autenticada
middleware_require_auth();

$user = current_user();
$title = 'Estaleiro Social — Constrói Já';
require_once __DIR__ . '/../templates/header.php';

$db = db();

// Procurar utilizadores recomendados para seguir
$suggestions = $db->fetchAll(
    "SELECT id, name, username, avatar_url, is_verified 
     FROM profiles 
     WHERE id != ? AND status = 'active'
     ORDER BY RAND() 
     LIMIT 5",
    [$user['id']]
);
?>

<div style="display:flex; flex-direction:column; gap:0; max-width:680px; margin:0 auto; width:100%;">
    
    <!-- ÁREA CENTRAL DE CONTEÚDO (FEED) -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        
        <!-- STORIES DE OBRAS E ENGENHEIROS (Premium look) -->
        <div class="stories-container">
            <!-- Story do próprio user para criar nova obra -->
            <div class="story-card" data-jsaction="__go__" data-jsarg="/projects/create">
                <div class="story-avatar-wrap" style="border-color:var(--accent-secondary); background:rgba(59,130,246,0.1);">
                    <i data-lucide="plus" style="width:24px; height:24px; color:var(--accent-secondary);"></i>
                </div>
                <span class="story-username">Nova Obra</span>
            </div>
            
            <?php foreach ($suggestions as $sug): ?>
            <div class="story-card" data-jsaction="__go__" data-jsarg="/profile/<?php echo sanitize($sug['username']); ?>">
                <div class="story-avatar-wrap">
                    <img src="<?php echo get_avatar_url($sug['avatar_url'], $sug['name']); ?>" class="story-avatar">
                </div>
                <span class="story-username"><?php echo sanitize(explode(' ', $sug['name'])[0]); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- FORMULÁRIO DE PUBLICAÇÃO DE NOVO POST -->
        <div class="post-card-fb" style="margin-bottom:12px;">
            <form id="create-post-form" data-jsaction="publishPost" data-jsprevent="1" style="padding:16px;">
                <div style="display:flex; gap:10px; align-items:flex-start; margin-bottom:12px;">
                    <img src="<?php echo get_avatar_url($user['avatar_url'], $user['name']); ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    <textarea id="post-content" class="post-card-fb__comment-input" placeholder="O que está a acontecer na sua obra hoje?" style="min-height:60px; border-radius:var(--radius-md); resize:vertical; padding:12px 16px;"></textarea>
                </div>

                <!-- Zona de Upload de Imagem, Video ou PDF -->
                <div class="upload-zone" id="post-upload-zone" data-preview-id="post-upload-preview" style="padding:16px; border-radius:var(--radius-sm); margin-bottom:12px;">
                    <input type="file" id="post-file" name="file" accept="image/*,video/*,application/pdf" style="display:none;">
                    <i data-lucide="image" style="width:22px; height:22px; color:var(--text-muted); margin-bottom:4px;"></i>
                    <p style="font-size:12px; color:var(--text-muted);">Arraste ou clique para anexar foto/vídeo real da obra ou recibo PDF (Máx 50MB)</p>
                    <div id="post-upload-preview" style="margin-top:8px;"></div>
                </div>

                <!-- Selector de Tipo de Post (Post vs Reel) -->
                <div id="post-format-selector" style="display:none; align-items:center; gap:12px; margin-bottom:12px; font-size:13px; background:rgba(255,255,255,0.02); padding:10px; border-radius:6px; border:1px solid var(--border-color);">
                    <span style="color:var(--text-muted);"><i data-lucide="video" style="width:14px; height:14px; display:inline-block; vertical-align:middle; margin-right:4px;"></i> Formato de vídeo:</span>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; color:var(--text-primary);">
                        <input type="radio" name="is_reel" value="0" checked style="accent-color:var(--accent-primary);"> Post no Feed
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; color:var(--text-primary);">
                        <input type="radio" name="is_reel" value="1" style="accent-color:var(--accent-primary);"> Reel Vertical (9:16)
                    </label>
                </div>

                <div style="display:flex; justify-content:flex-end; align-items:center; border-top:1px solid var(--border-color); padding-top:10px;">
                    <button type="submit" id="post-submit-btn" class="btn btn-primary" style="padding:8px 24px; font-size:14px; border-radius:20px;">
                        Publicar
                    </button>
                </div>
            </form>
        </div>

        <!-- TABS DE FEED DE ÚLTIMA GERAÇÃO (WOW design) -->
        <div class="feed-tabs" style="display:flex; border-bottom:1px solid var(--border-color); margin-bottom:8px; gap:8px;">
            <button data-jsaction="App.Feed.switchTab" data-jsarg="all" class="feed-tab-btn active" id="tab-btn-all" style="flex:1; padding:12px; background:none; border:none; color:var(--text-primary); font-weight:700; cursor:pointer; border-bottom:2px solid var(--accent-primary); font-size:14px; transition: all 0.2s;">
                Todas as Publicações
            </button>
            <button data-jsaction="App.Feed.switchTab" data-jsarg="following" class="feed-tab-btn" id="tab-btn-following" style="flex:1; padding:12px; background:none; border:none; color:var(--text-muted); font-weight:700; cursor:pointer; font-size:14px; transition: all 0.2s;">
                Quem eu Sigo
            </button>
            <button data-jsaction="App.Feed.switchTab" data-jsarg="reels" class="feed-tab-btn" id="tab-btn-reels" style="flex:1; padding:12px; background:none; border:none; color:var(--text-muted); font-weight:700; cursor:pointer; font-size:14px; transition: all 0.2s;">
                🎥 Reels de Obras
            </button>
        </div>
        <select id="feed-filter" style="display:none;">
            <option value="all" selected>all</option>
            <option value="following">following</option>
            <option value="reels">reels</option>
        </select>

        <!-- INDICADOR DE PESQUISA / HASHTAG -->
        <div id="feed-search-indicator" style="display:none; align-items:center; justify-content:space-between; background:rgba(249,115,22,0.1); border:1px solid var(--accent-primary); padding:10px 16px; border-radius:var(--radius-sm); margin-bottom:16px;">
            <span style="color:var(--text-primary); font-size:14px;">A mostrar publicações com: <strong id="feed-search-tag" style="color:var(--accent-primary);">#tag</strong></span>
            <button data-jsaction="App.Feed.clearSearch" style="background:none; border:none; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; gap:4px; font-size:13px;">
                <i data-lucide="x" style="width:16px; height:16px;"></i> Limpar Filtro
            </button>
        </div>

        <!-- LISTAGEM DE POSTS (AJAX) -->
        <div id="feed-posts-list" style="display:flex; flex-direction:column; gap:0;"></div>

        <!-- SENTINEL PARA INFINITE SCROLL -->
        <div id="feed-sentinel" style="height:20px;"></div>

        <!-- SKELETON DE CARREGAMENTO -->
        <div id="feed-skeleton" style="display:none;">
            <div class="card" style="margin-bottom:20px;">
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:15px;">
                    <div class="skeleton" style="width:40px; height:40px; border-radius:50%;"></div>
                    <div>
                        <div class="skeleton" style="width:120px; height:16px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:80px; height:12px;"></div>
                    </div>
                </div>
                <div class="skeleton" style="width:100%; height:100px; margin-bottom:15px;"></div>
                <div style="display:flex; gap:20px;">
                    <div class="skeleton" style="width:60px; height:20px;"></div>
                    <div class="skeleton" style="width:60px; height:20px;"></div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Injetar scripts de publicação específicos -->
<script nonce="<?php echo Security::getNonce(); ?>">
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar o feed social a partir da nossa app modular
    App.Feed.init();

    // Event listener para monitorizar upload de vídeo e mostrar selector de formato
    const fileInput = document.getElementById('post-file');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            const selector = document.getElementById('post-format-selector');
            if (selector) {
                if (file && file.type.startsWith('video/')) {
                    selector.style.display = 'flex';
                } else {
                    selector.style.display = 'none';
                    // Reset check
                    const defaultRadio = document.querySelector('input[name="is_reel"][value="0"]');
                    if (defaultRadio) defaultRadio.checked = true;
                }
            }
        });
    }
});

async function publishPost() {
    const btn = document.getElementById('post-submit-btn');
    const content = document.getElementById('post-content').value.trim();
    const fileInput = document.getElementById('post-file');
    
    if (content === '' && (!fileInput.files || fileInput.files.length === 0)) {
        App.showToast('Escreva alguma coisa ou adicione um ficheiro.', 'warning');
        return;
    }

    App.setLoading(btn, true);

    const formData = new FormData();
    formData.append('content', content);
    if (fileInput.files.length > 0) {
        formData.append('file', fileInput.files[0]);
        
        // Obter formato (Post vs Reel)
        const isReelVal = document.querySelector('input[name="is_reel"]:checked')?.value || '0';
        formData.append('is_reel', isReelVal);
    }

    try {
        const response = await App.upload('/api/posts/create', formData);
        
        App.showToast('Publicação inserida com sucesso!', 'success');
        
        // Reset form
        document.getElementById('post-content').value = '';
        fileInput.value = '';
        document.getElementById('post-upload-preview').innerHTML = '';
        const selector = document.getElementById('post-format-selector');
        if (selector) selector.style.display = 'none';
        const defaultRadio = document.querySelector('input[name="is_reel"][value="0"]');
        if (defaultRadio) defaultRadio.checked = true;
        
        // Colocar o post publicado no topo do feed imediatamente se corresponder ao filtro
        const listEl = document.getElementById('feed-posts-list');
        if (listEl && response.data.post) {
            const isReel = parseInt(response.data.post.is_reel) === 1;
            const filter = document.getElementById('feed-filter')?.value || 'all';
            
            if ((isReel && filter === 'reels') || (!isReel && filter !== 'reels')) {
                const card = isReel ? App.Feed.renderReelCard(response.data.post) : App.Feed.renderPostCard(response.data.post);
                listEl.insertBefore(card, listEl.firstChild);
            }
            if (window.lucide) window.lucide.createIcons();
        }

    } catch (error) {
        App.showToast(error.message || 'Erro ao publicar.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}
</script>

<?php
// Adicionar barra lateral direita apenas no feed
?>
<section class="sidebar-right">
    <div style="display:flex; flex-direction:column; gap:24px;">
        
        <!-- Perfil rápido do próprio utilizador -->
        <div class="card" style="padding:16px; background:rgba(255,255,255,0.01);">
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="<?php echo get_avatar_url($user['avatar_url'], $user['name']); ?>" class="avatar avatar-md">
                <div style="overflow:hidden;">
                    <h4 style="white-space:nowrap; text-overflow:ellipsis; overflow:hidden;"><?php echo sanitize($user['name']); ?></h4>
                    <small style="color:var(--text-secondary);">@<?php echo sanitize($user['username'] ?? 'user'); ?></small>
                </div>
            </div>
        </div>

        <!-- Utilizadores Recomendados -->
        <div>
            <h4 style="margin-bottom:16px; font-size:14px; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-secondary);">Profissionais Sugeridos</h4>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <?php foreach ($suggestions as $sug): ?>
                <div style="display:flex; align-items:center; justify-content:between; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px; cursor:pointer;" data-jsaction="__go__" data-jsarg="/profile/<?php echo sanitize($sug['username']); ?>">
                        <img src="<?php echo get_avatar_url($sug['avatar_url'], $sug['name']); ?>" class="avatar avatar-sm">
                        <div>
                            <span style="font-weight:600; font-size:13px; display:flex; align-items:center; gap:4px; color:var(--text-primary);">
                                <?php echo sanitize($sug['name']); ?>
                                <?php if ($sug['is_verified']): ?>
                                <i data-lucide="check-circle-2" style="width:12px; height:12px; color:var(--accent-secondary); fill:var(--accent-secondary); --lucide-stroke: #0a0f1e;"></i>
                                <?php endif; ?>
                            </span>
                            <small style="color:var(--text-muted); display:block;">@<?php echo sanitize($sug['username']); ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>
</section>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
