<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$title = 'Página não Encontrada — Constrói Já';
require_once __DIR__ . '/../templates/header.php';
?>

<div style="min-height: 75vh; display: flex; align-items: center; justify-content: center; padding: 20px; text-align: center;">
    <div class="card slideUp" style="max-width: 480px; padding: 50px 30px; border-radius: var(--radius-lg); background: rgba(255,255,255,0.01);">
        <h1 style="font-size: 80px; font-weight: 800; background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 10px;">404</h1>
        <h2 style="margin-bottom: 16px;">Obra não Encontrada!</h2>
        <p style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 30px;">
            O caminho ou documento que procura não existe neste estaleiro ou foi removido definitivamente. Verifique a URI e tente novamente.
        </p>
        <a href="<?php echo is_logged_in() ? '/feed' : '/'; ?>" class="btn btn-primary" style="padding: 12px 24px;">
            <i data-lucide="home"></i>
            Voltar ao Início
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
