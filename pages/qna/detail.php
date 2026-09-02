<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Proteção por login obrigatório
middleware_require_auth();

$user = current_user();
$questionId = (int)input('id', 0);
$db = db();

try {
    // 1. Obter detalhes da pergunta
    $question = $db->fetch(
        "SELECT q.*, pr.name, pr.username, pr.avatar_url, pr.is_verified
         FROM questions q
         JOIN profiles pr ON q.user_id = pr.id
         WHERE q.id = ?",
        [$questionId]
    );

    if (!$question) {
        http_response_code(404);
        require_once __DIR__ . '/../404.php';
        exit;
    }

    // Decode tags
    $qTags = [];
    if (!empty($question['tags'])) {
        $qTags = is_string($question['tags']) ? (json_decode($question['tags'], true) ?? []) : $question['tags'];
    }

    // 2. Obter respostas à pergunta (ordenando especialistas primeiro)
    $answers = $db->fetchAll(
        "SELECT a.*, pr.name, pr.username, pr.avatar_url, pr.is_verified, pr.is_admin
         FROM answers a
         JOIN profiles pr ON a.user_id = pr.id
         WHERE a.question_id = ?
         ORDER BY a.is_expert DESC, a.created_at ASC",
        [$questionId]
    );

} catch (PDOException $e) {
    die("Erro ao carregar detalhes da pergunta: " . $e->getMessage());
}

$title = sanitize($question['title']) . " — Constrói Já";
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- Link Voltar -->
    <a href="/qna" style="display:flex; align-items:center; gap:6px; color:var(--text-muted); text-decoration:none; font-size:13px; font-weight:600; width:fit-content;">
        <i data-lucide="arrow-left" style="width:16px; height:16px;"></i>
        Voltar ao Q&A Técnico
    </a>

    <!-- Bloco Principal: A Pergunta -->
    <div class="card" style="padding: 24px; display: flex; flex-direction: column; gap: 16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; font-size:13px; color:var(--text-muted);">
            <div style="display:flex; align-items:center; gap:8px;">
                <a href="/profile/<?php echo sanitize($question['username']); ?>" style="display:flex; align-items:center; gap:8px; text-decoration:none; color:var(--text-secondary); font-weight:600;">
                    <img src="<?php echo get_avatar_url($question['avatar_url'], $question['name']); ?>" style="width:28px; height:28px; border-radius:50%; object-fit:cover;">
                    <span><?php echo sanitize($question['name']); ?></span>
                    <?php if ((int)($question['is_verified'] ?? 0) === 1): ?>
                        <i data-lucide="verified" style="color: var(--accent-secondary); width: 14px; height: 14px; fill: rgba(59,130,246,0.1);"></i>
                    <?php endif; ?>
                </a>
            </div>
            <span>Perguntado <?php echo time_ago($question['created_at']); ?></span>
        </div>

        <h1 style="font-size:22px; font-weight:800; line-height:1.4; color:var(--text-primary);">
            <?php echo sanitize($question['title']); ?>
        </h1>

        <p style="color:var(--text-secondary); font-size:15px; line-height:1.7; white-space:pre-line;">
            <?php echo sanitize($question['content']); ?>
        </p>

        <?php if (!empty($qTags)): ?>
            <div style="display:flex; flex-wrap:wrap; gap:6px; border-top: 1px solid var(--border-color); padding-top:16px; margin-top:8px;">
                <?php foreach ($qTags as $tag): ?>
                    <span class="badge" style="background:rgba(255,255,255,0.02); color:var(--text-muted); border:1px solid var(--border-color); font-size:11px; padding:3px 10px; border-radius:4px;">
                        <?php echo sanitize($tag); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Secção: Respostas da Comunidade -->
    <div style="display:flex; flex-direction:column; gap:16px;">
        <h3 style="font-size:16px; font-weight:700; color:var(--text-primary); margin-left:4px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="message-square" style="color:var(--accent-secondary); width:18px; height:18px;"></i>
            Respostas (<?php echo count($answers); ?>)
        </h3>

        <div id="answers-container" style="display:flex; flex-direction:column; gap:16px;">
            <?php if (empty($answers)): ?>
                <div class="card" style="text-align:center; padding:40px 20px; color:var(--text-muted);" id="no-answers-alert">
                    <p style="font-size:14px;">Ainda não existem respostas para esta pergunta. Tem conhecimento sobre o assunto? Dê a sua resposta abaixo!</p>
                </div>
            <?php else: ?>
                <?php foreach ($answers as $ans): ?>
                    <?php $isAnsExpert = (int)$ans['is_expert'] === 1; ?>
                    <div class="card" style="padding: 20px; display:flex; flex-direction:column; gap:12px; border-color: <?php echo $isAnsExpert ? 'rgba(249,115,22,0.2)' : 'rgba(255,255,255,0.04)'; ?>; background: <?php echo $isAnsExpert ? 'linear-gradient(135deg, rgba(249,115,22,0.02) 0%, rgba(10,15,30,0.8) 100%)' : 'var(--card-bg)'; ?>;">
                        
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <a href="/profile/<?php echo sanitize($ans['username']); ?>" style="display:flex; align-items:center; gap:8px; text-decoration:none; color:var(--text-secondary); font-weight:600;">
                                    <img src="<?php echo get_avatar_url($ans['avatar_url'], $ans['name']); ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                    <span><?php echo sanitize($ans['name']); ?></span>
                                    <?php if ((int)($ans['is_verified'] ?? 0) === 1): ?>
                                        <i data-lucide="verified" style="color: var(--accent-secondary); width: 14px; height: 14px; fill: rgba(59,130,246,0.1);"></i>
                                    <?php endif; ?>
                                </a>

                                <?php if ($isAnsExpert): ?>
                                    <span class="badge" style="background: rgba(249,115,22,0.1); color: var(--accent-primary); border: 1px solid rgba(249,115,22,0.2); font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight:700;">
                                        Especialista Civil
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span style="color:var(--text-muted);">Respondido <?php echo time_ago($ans['created_at']); ?></span>
                        </div>

                        <p style="color:var(--text-primary); font-size:14px; line-height:1.6; white-space:pre-line;">
                            <?php echo sanitize($ans['content']); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bloco Formulário: Submeter Resposta -->
    <div class="card" style="padding: 24px; display:flex; flex-direction:column; gap:16px;">
        <h4 style="font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
            <i data-lucide="edit-3" style="color:var(--accent-primary); width:18px; height:18px;"></i>
            A sua Resposta Técnica
        </h4>

        <form id="answer-form" onsubmit="event.preventDefault(); submitAnswer();" style="display:flex; flex-direction:column; gap:14px;">
            <div class="form-group">
                <textarea id="answer-content" class="form-control" placeholder="Escreva a sua resposta técnica fundamentada para ajudar este construtor..." required style="width:100%; min-height:120px; line-height:1.6;"></textarea>
            </div>
            
            <div style="display:flex; justify-content:flex-end;">
                <button type="submit" id="submit-answer-btn" class="btn btn-primary" style="padding: 10px 24px;">
                    Submeter Resposta
                    <i data-lucide="send" style="width:16px; height:16px;"></i>
                </button>
            </div>
        </form>
    </div>

</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function submitAnswer() {
    const btn = document.getElementById('submit-answer-btn');
    const contentInput = document.getElementById('answer-content');
    const content = contentInput.value.trim();
    const questionId = <?php echo $questionId; ?>;

    if (content === '') return;

    App.setLoading(btn, true);

    try {
        const res = await App.post('/api/questions/answer', {
            question_id: questionId,
            content: content
        });

        if (res.success) {
            App.showToast('Resposta enviada com sucesso!', 'success');
            contentInput.value = '';

            // Remover aviso de sem respostas
            const noAns = document.getElementById('no-answers-alert');
            if (noAns) noAns.style.display = 'none';

            // Injetar a resposta no container
            const container = document.getElementById('answers-container');
            const data = res.data.answer;

            const card = document.createElement('div');
            card.className = "card";
            card.style.padding = "20px";
            card.style.display = "flex";
            card.style.flexDirection = "column";
            card.style.gap = "12px";
            card.style.borderColor = data.is_expert ? 'rgba(249,115,22,0.2)' : 'rgba(255,255,255,0.04)';
            card.style.background = data.is_expert ? 'linear-gradient(135deg, rgba(249,115,22,0.02) 0%, rgba(10,15,30,0.8) 100%)' : 'var(--card-bg)';

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <a href="/profile/${data.username}" style="display:flex; align-items:center; gap:8px; text-decoration:none; color:var(--text-secondary); font-weight:600;">
                            <img src="${data.avatar_url}" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                            <span>${data.name}</span>
                        </a>
                        ${data.is_expert ? `
                            <span class="badge" style="background: rgba(249,115,22,0.1); color: var(--accent-primary); border: 1px solid rgba(249,115,22,0.2); font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight:700;">
                                Especialista Civil
                            </span>
                        ` : ''}
                    </div>
                    <span style="color:var(--text-muted);">Agora mesmo</span>
                </div>
                <p style="color:var(--text-primary); font-size:14px; line-height:1.6; white-space:pre-line;">
                    ${data.content}
                </p>
            `;

            container.appendChild(card);
            
            // Recriar ícones lucide se aplicável
            if (window.lucide) {
                window.lucide.createIcons();
            }

            // Scroll suave até ao novo card
            card.scrollIntoView({ behavior: 'smooth' });

        } else {
            App.showToast(res.error || 'Erro ao submeter resposta.', 'danger');
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao conectar ao servidor.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
