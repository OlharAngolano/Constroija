<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

// Rota obrigatoriamente autenticada
middleware_require_auth();

$user = current_user();
$title = 'Notificações — Constrói Já';
require_once __DIR__ . '/../templates/header.php';

$db = db();

// Buscar notificações
try {
    $notifications = $db->fetchAll(
        "SELECT n.*, pr.name AS sender_name, pr.username AS sender_username, pr.avatar_url AS sender_avatar
         FROM notifications n
         JOIN profiles pr ON n.sender_id = pr.id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC
         LIMIT 50",
        [$user['id']]
    );
    
    // Marcar as mostradas nesta página como lidas imediatamente (ou no botão Limpar)
} catch (PDOException $e) {
    $notifications = [];
}
?>

<div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
    
    <!-- CABEÇALHO DA PÁGINA -->
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2>Notificações</h2>
            <p style="color:var(--text-secondary); font-size:14px;">Mantenha-se atualizado com a atividade à volta dos seus projetos e perfil.</p>
        </div>
        
        <?php if (!empty($notifications)): ?>
            <button class="btn btn-secondary" data-jsaction="App.Notifications.markAllRead" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="check-check" style="width:16px; height:16px;"></i>
                Marcar todas como lidas
            </button>
        <?php endif; ?>
    </div>

    <!-- LISTAGEM DE NOTIFICAÇÕES -->
    <div class="card" style="padding:0; overflow:hidden;">
        <?php if (empty($notifications)): ?>
            <div style="padding: 60px 40px; text-align: center; color: var(--text-secondary);">
                <div style="background:rgba(59,130,246,0.05); width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin: 0 auto 16px;">
                    <i data-lucide="bell-off" style="width:32px; height:32px; color:var(--text-muted);"></i>
                </div>
                <h3>Sem notificações para já</h3>
                <p style="font-size:13px; color:var(--text-muted); max-width:320px; margin:8px auto 0;">Sempre que alguém gostar de um post, lhe enviar mensagens ou responder às suas dúvidas, aparecerá aqui.</p>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column;">
                <?php foreach ($notifications as $n): ?>
                    <?php
                    // Configuração visual baseada no tipo de notificação
                    $icon = 'bell';
                    $iconColor = 'var(--text-secondary)';
                    $link = '#';
                    $text = '';
                    
                    switch ($n['type']) {
                        case 'like':
                            $icon = 'thumbs-up';
                            $iconColor = 'var(--accent-primary)';
                            $link = '/feed#post-card-' . $n['entity_id'];
                            $text = 'gostou da sua publicação.';
                            break;
                        case 'comment':
                            $icon = 'message-square';
                            $iconColor = 'var(--accent-secondary)';
                            $link = '/feed#post-card-' . $n['entity_id'];
                            $text = 'comentou a sua publicação.';
                            break;
                        case 'follow':
                            $icon = 'user-plus';
                            $iconColor = 'var(--accent-success)';
                            $link = '/profile/' . $n['sender_username'];
                            $text = 'começou a segui-lo.';
                            break;
                        case 'message':
                            $icon = 'message-circle';
                            $iconColor = 'var(--accent-warning)';
                            $link = '/messages?id=' . $n['entity_id'];
                            $text = 'enviou-lhe uma nova mensagem.';
                            break;
                        case 'answer':
                            $icon = 'check-circle';
                            $iconColor = 'var(--accent-success)';
                            $link = '/qna/detail?id=' . $n['entity_id'];
                            $text = 'respondeu à sua questão técnica.';
                            break;
                    }
                    
                    $isUnread = !(bool)$n['is_read'];
                    ?>
                    
                    <a href="<?php echo $link; ?>" data-jsaction="markRead" data-jsarg="<?php echo (int)$n['id']; ?>" class="notification-item-link" style="display: flex; gap: 16px; padding: 20px; align-items: center; border-bottom: 1px solid var(--border-color); background: <?php echo $isUnread ? 'rgba(249, 115, 22, 0.03)' : 'transparent'; ?>; transition: var(--transition-fast);">
                        <!-- Avatar com Ícone tipo Badge -->
                        <div style="position:relative;">
                            <img src="<?php echo get_avatar_url($n['sender_avatar'], $n['sender_name']); ?>" class="avatar avatar-md" style="width:44px; height:44px; border: 2px solid <?php echo $isUnread ? 'var(--accent-primary)' : 'transparent'; ?>;">
                            <div style="position:absolute; bottom:-4px; right:-4px; width:22px; height:22px; border-radius:50%; background:var(--bg-card); border: 2px solid var(--bg-primary); display:flex; align-items:center; justify-content:center;">
                                <i data-lucide="<?php echo $icon; ?>" style="width:10px; height:10px; color:<?php echo $iconColor; ?>;"></i>
                            </div>
                        </div>
                        
                        <!-- Conteúdo do Alerta -->
                        <div style="flex:1;">
                            <div style="font-size:14px; color:var(--text-primary); line-height:1.4;">
                                <strong style="font-weight:700; color:#ffffff;"><?php echo sanitize($n['sender_name']); ?></strong> 
                                <span style="color:var(--text-secondary);"><?php echo $text; ?></span>
                            </div>
                            <small style="color:var(--text-muted); margin-top:4px; display:block;"><?php echo time_ago($n['created_at']); ?></small>
                        </div>
                        
                        <!-- Ponto indicativo de não lida -->
                        <?php if ($isUnread): ?>
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-primary);" id="unread-dot-<?php echo $n['id']; ?>"></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function markRead(notificationId) {
    try {
        // Envia post para marcar como lida individualmente
        await App.post('/api/notifications/read', { id: notificationId });
        const dot = document.getElementById(`unread-dot-${notificationId}`);
        if (dot) dot.remove();
    } catch (e) {
        console.error("Falha ao marcar notificação como lida", e);
    }
}
</script>

<style>
.notification-item-link:hover {
    background: rgba(255, 255, 255, 0.02) !important;
}
</style>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
