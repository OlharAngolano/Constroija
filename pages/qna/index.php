<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Proteção por login obrigatório
middleware_require_auth();

$user = current_user();
$db = db();

$searchQuery = trim((string)input('q', ''));
$filterTag = trim((string)input('tag', ''));

try {
    if ($searchQuery !== '') {
        // Pesquisa Fulltext ou fallback LIKE
        $sql = "SELECT q.*, pr.name, pr.username, pr.avatar_url,
                       (SELECT COUNT(*) FROM answers WHERE question_id = q.id) AS answers_count
                FROM questions q
                JOIN profiles pr ON q.user_id = pr.id
                WHERE MATCH(q.title, q.content) AGAINST(:query IN NATURAL LANGUAGE MODE)
                ORDER BY q.created_at DESC";
        $params = ['query' => $searchQuery];
    } elseif ($filterTag !== '') {
        // Filtro por Tag (busca dentro da coluna JSON 'tags')
        $sql = "SELECT q.*, pr.name, pr.username, pr.avatar_url,
                       (SELECT COUNT(*) FROM answers WHERE question_id = q.id) AS answers_count
                FROM questions q
                JOIN profiles pr ON q.user_id = pr.id
                WHERE JSON_CONTAINS(q.tags, :tag)
                ORDER BY q.created_at DESC";
        $params = ['tag' => json_encode($filterTag)];
    } else {
        // Listagem geral
        $sql = "SELECT q.*, pr.name, pr.username, pr.avatar_url,
                       (SELECT COUNT(*) FROM answers WHERE question_id = q.id) AS answers_count
                FROM questions q
                JOIN profiles pr ON q.user_id = pr.id
                ORDER BY q.created_at DESC";
        $params = [];
    }

    $questions = $db->fetchAll($sql, $params);

    // Listar algumas tags populares de construção para a barra lateral
    $popularTags = ['Betão Armado', 'Orçamentação', 'Fundações', 'Fiscalização', 'Alvenaria', 'Pinturas', 'Infiltrações', 'Instalações'];

} catch (PDOException $e) {
    die("Erro ao carregar Q&A Técnico: " . $e->getMessage());
}

$title = 'Q&A Técnico de Construção — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- Topo da página com Botão de Ação -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2>Q&A Técnico de Engenharia & Construção</h2>
            <p style="color:var(--text-secondary); font-size:14px;">Tira as tuas dúvidas com engenheiros e construtores experientes em Angola.</p>
        </div>
        <button onclick="openQuestionModal();" class="btn btn-primary" style="padding: 10px 20px;">
            <i data-lucide="plus-circle" style="width:18px; height:18px;"></i>
            Fazer Pergunta
        </button>
    </div>

    <!-- Barra de Pesquisa e Filtros Rápidos -->
    <div class="card" style="padding: 16px; display: flex; flex-direction: column; gap: 16px;">
        <form method="GET" action="/qna" style="display:flex; gap:12px;">
            <div style="position:relative; flex-grow:1;">
                <input type="text" name="q" class="form-control" placeholder="Pesquisar por cimento, fundações, infiltrações, betão..." value="<?php echo sanitize($searchQuery); ?>" style="width:100%; padding-left:40px;">
                <i data-lucide="search" style="position:absolute; left:12px; top:11px; color:var(--text-muted); width:18px; height:18px;"></i>
            </div>
            <button type="submit" class="btn btn-secondary" style="padding: 0 20px;">Pesquisar</button>
            <?php if ($searchQuery !== '' || $filterTag !== ''): ?>
                <a href="/qna" class="btn btn-secondary" style="display:flex; align-items:center; justify-content:center; padding: 0 16px;"><i data-lucide="x" style="width:16px; height:16px;"></i></a>
            <?php endif; ?>
        </form>

        <!-- Filtros Rápidos de Tags -->
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <span style="font-size:12px; color:var(--text-muted); font-weight:600; text-transform:uppercase;">Especialidades populares:</span>
            <?php foreach ($popularTags as $popTag): ?>
                <a href="/qna?tag=<?php echo urlencode($popTag); ?>" 
                   class="badge" 
                   style="text-decoration:none; padding:4px 10px; border-radius:20px; font-size:12px; background: <?php echo $filterTag === $popTag ? 'var(--accent-primary)' : 'rgba(255,255,255,0.03)'; ?>; color: <?php echo $filterTag === $popTag ? '#fff' : 'var(--text-secondary)'; ?>; border: 1px solid <?php echo $filterTag === $popTag ? 'var(--accent-primary)' : 'var(--border-color)'; ?>;">
                    <?php echo $popTag; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Resultados -->
    <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
        <?php if (empty($questions)): ?>
            <div class="card" style="text-align:center; padding:60px 20px; color:var(--text-muted);">
                <i data-lucide="help-circle" style="width:48px; height:48px; stroke-width:1; margin:0 auto 12px; color:var(--text-muted);"></i>
                <p style="font-size:16px; font-weight:600; margin-bottom:4px;">Nenhuma pergunta encontrada</p>
                <p style="font-size:14px; margin-bottom:16px;">Seja o primeiro a colocar a sua dúvida à comunidade civil!</p>
                <button onclick="openQuestionModal();" class="btn btn-primary" style="margin: 0 auto;">Fazer uma Pergunta</button>
            </div>
        <?php else: ?>
            <?php foreach ($questions as $question): ?>
                <?php 
                $qTags = [];
                if (!empty($question['tags'])) {
                    $qTags = is_string($question['tags']) ? (json_decode($question['tags'], true) ?? []) : $question['tags'];
                }
                ?>
                <div class="card" style="padding: 20px; display:flex; flex-direction:column; gap:14px; transition: transform 0.2s; border-color: rgba(255,255,255,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                        <a href="/qna/detail?id=<?php echo $question['id']; ?>" style="text-decoration:none; color:var(--text-primary); flex-grow:1;">
                            <h3 style="font-size:17px; font-weight:800; line-height:1.4; hover:color:var(--accent-primary);">
                                <?php echo sanitize($question['title']); ?>
                            </h3>
                        </a>
                        
                        <span class="badge" style="background: rgba(59,130,246,0.08); color: var(--accent-secondary); border: 1px solid rgba(59,130,246,0.15); display:flex; align-items:center; gap:4px; font-size:12px; padding:4px 8px; border-radius:var(--radius-sm);">
                            <i data-lucide="message-square" style="width:14px; height:14px;"></i>
                            <?php echo $question['answers_count']; ?>
                        </span>
                    </div>

                    <!-- Conteúdo Curto da Pergunta -->
                    <p style="color:var(--text-secondary); font-size:14px; line-height:1.6; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                        <?php echo sanitize(strip_tags($question['content'])); ?>
                    </p>

                    <!-- Tags / Etiquetas -->
                    <?php if (!empty($qTags)): ?>
                        <div style="display:flex; flex-wrap:wrap; gap:6px;">
                            <?php foreach ($qTags as $tag): ?>
                                <span class="badge" style="background:rgba(255,255,255,0.02); color:var(--text-muted); border:1px solid var(--border-color); font-size:11px; padding:2px 8px; border-radius:4px;">
                                    <?php echo sanitize($tag); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Autor e Data -->
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid var(--border-color); padding-top: 12px; font-size:12px; color:var(--text-muted);">
                        <a href="/profile/<?php echo sanitize($question['username']); ?>" style="display:flex; align-items:center; gap:8px; text-decoration:none; color:var(--text-secondary);">
                            <img src="<?php echo get_avatar_url($question['avatar_url'], $question['name']); ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                            <span style="font-weight:600;"><?php echo sanitize($question['name']); ?></span>
                        </a>
                        <span>Publicado <?php echo time_ago($question['created_at']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Modal: Criar Pergunta -->
<div id="question-modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 600px; padding: 24px; display: flex; flex-direction: column; gap: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border-color); padding-bottom:12px;">
            <h3 style="display:flex; align-items:center; gap:8px; color: var(--accent-primary);">
                <i data-lucide="help-circle"></i>
                Fazer Nova Pergunta
            </h3>
            <button onclick="closeQuestionModal();" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i data-lucide="x" style="width:20px; height:20px;"></i></button>
        </div>

        <form id="question-form" onsubmit="event.preventDefault(); submitQuestion();" style="display:flex; flex-direction:column; gap:16px;">
            <div class="form-group">
                <label for="modal-title" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Título da Pergunta</label>
                <input type="text" id="modal-title" class="form-control" placeholder="Ex: Qual a dosagem recomendada de cimento para fundações de betão armado?" required style="width:100%;">
            </div>
            
            <div class="form-group">
                <label for="modal-content" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Descrição Detalhada</label>
                <textarea id="modal-content" class="form-control" placeholder="Explique os detalhes do seu problema técnico ou dúvida para que os engenheiros o possam ajudar..." required style="width:100%; min-height:150px;"></textarea>
            </div>

            <div class="form-group">
                <label for="modal-tags" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Tags / Especialidades (Separadas por vírgula)</label>
                <input type="text" id="modal-tags" class="form-control" placeholder="Ex: fundação, cimento, betão armado" style="width:100%;">
                <small style="color:var(--text-muted);">Adicione termos específicos que facilitem a pesquisa.</small>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:8px;">
                <button type="button" onclick="closeQuestionModal();" class="btn btn-secondary" style="padding:10px 20px;">Cancelar</button>
                <button type="submit" id="submit-question-btn" class="btn btn-primary" style="padding:10px 20px;">
                    Publicar Pergunta
                    <i data-lucide="send" style="width:16px; height:16px;"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
function openQuestionModal() {
    document.getElementById('question-modal').style.display = 'flex';
}

function closeQuestionModal() {
    document.getElementById('question-modal').style.display = 'none';
    document.getElementById('question-form').reset();
}

async function submitQuestion() {
    const btn = document.getElementById('submit-question-btn');
    const title = document.getElementById('modal-title').value.trim();
    const content = document.getElementById('modal-content').value.trim();
    const tags = document.getElementById('modal-tags').value.trim();

    App.setLoading(btn, true);

    try {
        const res = await App.post('/api/questions/create', {
            title,
            content,
            tags
        });

        if (res.success) {
            App.showToast('Pergunta publicada com sucesso!', 'success');
            closeQuestionModal();
            setTimeout(() => {
                window.location.href = `/qna/detail?id=${res.data.question_id}`;
            }, 1000);
        } else {
            App.showToast(res.error || 'Erro ao publicar pergunta.', 'danger');
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao comunicar com o servidor.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
