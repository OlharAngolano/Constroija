<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/helpers.php';

$isLoggedIn = is_logged_in();
$currentUser = current_user();
$currentUri = current_uri();
$nonce = Security::getNonce();
?>
    <?php if ($isLoggedIn): ?>
        </main> <!-- /main-wrapper -->

        <!-- STICKY BOTTOM NAVIGATION BAR (MOBILE ONLY) -->
        <nav class="mobile-nav mobile-only">
            <a href="/feed" class="mobile-nav-link <?php echo $currentUri === '/feed' ? 'active' : ''; ?>">
                <i data-lucide="layout" style="width:20px; height:20px;"></i>
                <span>Feed</span>
            </a>
            <a href="/projects" class="mobile-nav-link <?php echo strpos($currentUri, '/projects') === 0 ? 'active' : ''; ?>">
                <i data-lucide="briefcase" style="width:20px; height:20px;"></i>
                <span>Obras</span>
            </a>
            <a href="/messages" class="mobile-nav-link <?php echo $currentUri === '/messages' ? 'active' : ''; ?>">
                <i data-lucide="message-square" style="width:20px; height:20px;"></i>
                <span>Mensagens</span>
            </a>
            <a href="/qna" class="mobile-nav-link <?php echo strpos($currentUri, '/qna') === 0 ? 'active' : ''; ?>">
                <i data-lucide="help-circle" style="width:20px; height:20px;"></i>
                <span>Q&A</span>
            </a>
            <a href="/profile/<?php echo sanitize($currentUser['username'] ?? ''); ?>" class="mobile-nav-link <?php echo $currentUri === '/profile/' . ($currentUser['username'] ?? '') ? 'active' : ''; ?>">
                <i data-lucide="user" style="width:20px; height:20px;"></i>
                <span>Perfil</span>
            </a>
            <?php if (is_admin()): ?>
                <a href="/admin" class="mobile-nav-link <?php echo strpos($currentUri, '/admin') === 0 ? 'active' : ''; ?>">
                    <i data-lucide="shield" style="width:20px; height:20px; color:var(--accent-secondary);"></i>
                    <span style="color:var(--accent-secondary);">Admin</span>
                </a>
            <?php endif; ?>
        </nav>

    </div> <!-- /app-layout -->
    <?php endif; ?>

    <!-- Carregar Aplicação JavaScript Central -->
    <script src="<?php echo APP_URL; ?>/assets/js/app.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/app.js'); ?>" nonce="<?php echo $nonce; ?>"></script>

    <!-- Inicializar Ícones Lucide -->
    <script nonce="<?php echo $nonce; ?>">
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>

</body>
</html>
