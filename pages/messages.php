<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

// Garantir utilizador autenticado
middleware_require_auth();

$user = current_user();
$title = 'Mensagens — Constrói Já';
require_once __DIR__ . '/../templates/header.php';

$db = db();

// Procurar conversas ativas do utilizador logado
$conversations = $db->fetchAll(
    "SELECT c.id, 
            (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message,
            (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_time,
            pr.name, pr.username, pr.avatar_url, pr.id AS participant_id, pr.is_verified
     FROM conversations c
     JOIN conversation_participants cp ON c.id = cp.conversation_id
     JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id != cp.user_id
     JOIN profiles pr ON cp2.user_id = pr.id
     WHERE cp.user_id = ?
     ORDER BY last_message_time DESC, c.created_at DESC",
    [$user['id']]
);

// Obter conversa pré-selecionada via URL ou novo chat
$preselectId = (int)input('id', 0);
$recipientId = (int)input('recipient_id', 0);
$recipientUser = null;

if ($recipientId > 0 && $recipientId !== $user['id']) {
    // Verificar se já existe conversa
    $existing = $db->fetch(
        "SELECT cp1.conversation_id 
         FROM conversation_participants cp1
         JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
         WHERE cp1.user_id = ? AND cp2.user_id = ?",
        [$user['id'], $recipientId]
    );
    if ($existing) {
        $preselectId = (int)$existing['conversation_id'];
    } else {
        // Obter dados do novo destinatário
        $recipientUser = $db->fetch("SELECT id, name, username, avatar_url, is_verified FROM profiles WHERE id = ?", [$recipientId]);
    }
}
?>

<div class="msg-app" id="msg-app">
    
    <!-- PAINEL ESQUERDO: LISTA DE CONVERSAS -->
    <div class="msg-sidebar" id="msg-sidebar">
        <div class="msg-sidebar__header">
            <h2 class="msg-sidebar__title">Chats</h2>
        </div>
        
        <div class="msg-sidebar__search">
            <i data-lucide="search" style="width:16px; height:16px; color:var(--text-muted); position:absolute; left:14px; top:50%; transform:translateY(-50%);"></i>
            <input type="text" placeholder="Pesquisar no Messenger" class="msg-sidebar__search-input" id="msg-search-input" data-jsaction="filterConversations" data-jsarg="__value__">
        </div>
        
        <div class="msg-sidebar__list" id="chat-list-container">
            <?php if ($recipientUser): ?>
                <!-- Novo chat pendente -->
                <div class="msg-convo-item msg-convo-item--active" id="convo-item-new" data-jsaction="selectNewRecipient" data-jsarg="<?php echo (int)$recipientUser['id']; ?>" data-name="<?php echo sanitize($recipientUser['name']); ?>">
                    <img src="<?php echo get_avatar_url($recipientUser['avatar_url'], $recipientUser['name']); ?>" class="msg-convo-item__avatar">
                    <div class="msg-convo-item__info">
                        <span class="msg-convo-item__name">
                            <?php echo sanitize($recipientUser['name']); ?>
                            <?php if ($recipientUser['is_verified']): ?>
                                <i data-lucide="check-circle-2" style="width:14px; height:14px; color:var(--accent-secondary); fill:var(--accent-secondary);"></i>
                            <?php endif; ?>
                        </span>
                        <span class="msg-convo-item__preview" style="color:var(--accent-secondary);">Nova conversa por iniciar...</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($conversations) && !$recipientUser): ?>
                <div class="msg-sidebar__empty">
                    <i data-lucide="message-circle" style="width:40px; height:40px; margin-bottom:10px; stroke-width:1.5; color:var(--text-muted);"></i>
                    <p>Nenhuma conversa ativa.</p>
                    <small>Visite perfis de outros construtores para iniciar uma conversa.</small>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $convo): ?>
                    <?php $isActive = ($preselectId === (int)$convo['id'] && !$recipientUser); ?>
                    <div class="msg-convo-item <?php echo $isActive ? 'msg-convo-item--active' : ''; ?>" 
                         id="convo-item-<?php echo $convo['id']; ?>" 
                         data-jsaction="openConversationById" data-jsarg="<?php echo (int)$convo['id']; ?>"
                         data-name="<?php echo sanitize($convo['name']); ?>">
                        <img src="<?php echo get_avatar_url($convo['avatar_url'], $convo['name']); ?>" class="msg-convo-item__avatar">
                        <div class="msg-convo-item__info">
                            <div class="msg-convo-item__top">
                                <span class="msg-convo-item__name">
                                    <?php echo sanitize($convo['name']); ?>
                                    <?php if ($convo['is_verified']): ?>
                                        <i data-lucide="check-circle-2" style="width:14px; height:14px; color:var(--accent-secondary); fill:var(--accent-secondary);"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="msg-convo-item__time"><?php echo $convo['time_ago'] ?? ''; ?></span>
                            </div>
                            <span class="msg-convo-item__preview"><?php echo sanitize($convo['last_message'] ?? 'Sem mensagens.'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAINEL DIREITO: CHAT ATIVO -->
    <div class="msg-chat" id="msg-chat">
        <!-- Header do chat (aparece quando conversa é selecionada) -->
        <div class="msg-chat__header" id="msg-chat-header" style="display:none;">
            <button class="msg-chat__back-btn" data-jsaction="showSidebarPanel" title="Voltar">
                <i data-lucide="arrow-left" style="width:20px; height:20px;"></i>
            </button>
            <div class="msg-chat__header-info" id="msg-chat-header-info">
                <!-- Preenchido dinamicamente -->
            </div>
        </div>
        
        <!-- Área de mensagens -->
        <div class="msg-chat__messages" id="chat-messages-container">
            <div class="msg-chat__placeholder">
                <i data-lucide="message-circle" style="width:56px; height:56px; color:var(--accent-primary); margin-bottom:16px; opacity:0.6; stroke-width:1.2;"></i>
                <h3>As tuas mensagens</h3>
                <p>Seleciona uma conversa para começar a trocar mensagens.</p>
            </div>
        </div>
        
        <!-- Input de mensagem -->
        <div class="msg-chat__input-area">
            <form data-jsaction="submitChatMessage" data-jsprevent="1" class="msg-chat__form">
                <input type="hidden" id="chat-recipient-id" value="<?php echo $recipientUser ? $recipientUser['id'] : ''; ?>">
                <input type="text" id="chat-message-input" class="msg-chat__input" placeholder="Aa" disabled autocomplete="off">
                <button type="submit" id="chat-send-btn" class="msg-chat__send-btn" disabled>
                    <i data-lucide="send" style="width:18px; height:18px;"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar o chat a partir do módulo
    App.Messages.init();
    
    // Se houver conversa pré-selecionada
    const preselect = <?php echo $preselectId; ?>;
    const pendingRecipient = <?php echo $recipientUser ? $recipientUser['id'] : 0; ?>;
    
    if (preselect > 0) {
        App.Messages.loadConversation(preselect);
        enableForm();
        showChatPanel();
    } else if (pendingRecipient > 0) {
        selectNewRecipient(pendingRecipient);
        showChatPanel();
    }

    // Fechar menus de post ao clicar fora (reutilizável)
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.msg-convo-item')) return;
    });
});

// --- CONTROLO DE PAINÉIS MOBILE ---
// (CJ-12) Abre uma conversa existente e mostra o painel de chat (sem inline)
function openConversationById(conversationId) {
    App.Messages.loadConversation(conversationId);
    showChatPanel();
}

function showChatPanel() {
    document.getElementById('msg-app').classList.add('msg-app--chat-active');
    document.getElementById('msg-chat-header').style.display = 'flex';
}

function showSidebarPanel() {
    document.getElementById('msg-app').classList.remove('msg-app--chat-active');
}

// --- PESQUISA DE CONVERSAS ---
function filterConversations(query) {
    const items = document.querySelectorAll('.msg-convo-item');
    const q = query.toLowerCase();
    items.forEach(item => {
        const name = (item.getAttribute('data-name') || '').toLowerCase();
        item.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

function enableForm() {
    document.getElementById('chat-message-input').disabled = false;
    document.getElementById('chat-send-btn').disabled = false;
    document.getElementById('chat-message-input').focus();
}

function selectNewRecipient(recipientId) {
    App.Messages.activeConversationId = null;
    document.getElementById('chat-recipient-id').value = recipientId;
    
    const messagesArea = document.getElementById('chat-messages-container');
    messagesArea.innerHTML = `
        <div class="msg-chat__placeholder">
            <i data-lucide="edit-3" style="width:40px; height:40px; color:var(--accent-secondary); margin-bottom:12px;"></i>
            <h3>Iniciar Nova Conversa</h3>
            <p>Escreva e envie a primeira mensagem para abrir este canal de comunicação.</p>
        </div>
    `;
    enableForm();
    showChatPanel();
    if (window.lucide) window.lucide.createIcons();
}

async function submitChatMessage() {
    const inputEl = document.getElementById('chat-message-input');
    const text = inputEl.value.trim();
    if (!text) return;
    
    const activeId = App.Messages.activeConversationId;
    const recipientId = document.getElementById('chat-recipient-id').value;
    const sendBtn = document.getElementById('chat-send-btn');
    
    const payload = {};
    if (activeId) {
        payload.conversation_id = activeId;
    } else if (recipientId) {
        payload.recipient_id = parseInt(recipientId);
    } else {
        return;
    }
    payload.message = text;
    
    inputEl.value = '';
    App.setLoading(sendBtn, true);
    
    try {
        const response = await App.post('/api/messages/send', payload);
        
        if (!activeId) {
            App.showToast('Conversa iniciada!', 'success');
            setTimeout(() => {
                window.location.href = '/messages?id=' + response.data.conversation_id;
            }, 500);
        } else {
            const area = document.getElementById('chat-messages-container');
            const bubble = document.createElement('div');
            bubble.className = 'message-bubble message-sent slideUp';
            bubble.innerHTML = `
                <div>${response.data.message.message}</div>
                <small class="msg-bubble__time" style="text-align:right;">Agora mesmo</small>
            `;
            area.appendChild(bubble);
            area.scrollTop = area.scrollHeight;
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao enviar mensagem.', 'danger');
        inputEl.value = text;
    } finally {
        App.setLoading(sendBtn, false);
        enableForm();
    }
}

// Sobrescrevemos o método renderMessages para activar inputs
const originalRender = App.Messages.renderMessages;
App.Messages.renderMessages = function(messages) {
    enableForm();
    originalRender.call(App.Messages, messages);
};
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
