<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$flash = get_flash_message();
if ($flash):
    $type = sanitize($flash['type']); // 'success', 'danger', 'warning', 'info'
    $message = sanitize($flash['message']);
    
    // Associar ícone com base no tipo de feedback
    $icon = 'info';
    if ($type === 'success') $icon = 'check-circle';
    if ($type === 'danger') $icon = 'alert-triangle';
    if ($type === 'warning') $icon = 'alert-circle';
?>
<div class="toast-container" id="session-flash-container" style="pointer-events:none;">
    <div class="flash-message flash-message-<?php echo $type; ?>" id="session-flash-toast" style="pointer-events:auto;">
        <i data-lucide="<?php echo $icon; ?>"></i>
        <span><?php echo $message; ?></span>
    </div>
</div>
<script nonce="<?php echo Security::getNonce(); ?>">
    document.addEventListener('DOMContentLoaded', () => {
        const toast = document.getElementById('session-flash-toast');
        if (toast) {
            // Efeito fade-out automático após 4 segundos
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    const container = document.getElementById('session-flash-container');
                    if (container) {
                        container.remove();
                    }
                }, 300);
            }, 4000);
        }
    });
</script>
<?php endif; ?>
