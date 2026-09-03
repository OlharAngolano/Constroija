<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/helpers.php';

$currentUser = current_user();
$isLoggedIn = is_logged_in();
$isAdmin = is_admin();
$nonce = Security::getNonce();
$currentUri = current_uri();
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Constrói Já — A plataforma de eleição para gestão financeira, planeamento e feed social de obras na construção civil angolana.">
    <title><?php echo $title ?? 'Constrói Já — Gestão de Obras'; ?></title>
    
    <!-- DNS Prefetch para CDNs -->
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    
    <!-- Tipografia Inter & Outfit (font-display: swap para renderização instantânea) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS Customizado (Com Cache Buster Automático) -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    
    <!-- Lucide Icons CDN (defer para não bloquear renderização) -->
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.344.0/dist/umd/lucide.min.js" defer nonce="<?php echo $nonce; ?>"></script>
    
    <!-- Chart.js CDN (defer — carregado em background, disponível quando necessário) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer nonce="<?php echo $nonce; ?>"></script>

    <!-- Configurações Dinâmicas do Cliente JS (Nonce Protegido) -->
    <script nonce="<?php echo $nonce; ?>">
        window.APP = {
            url: "<?php echo APP_URL; ?>",
            csrfToken: "<?php echo Security::generateToken(); ?>",
            userId: <?php echo $currentUser ? (int)$currentUser['id'] : 'null'; ?>,
            isLoggedIn: <?php echo $isLoggedIn ? 'true' : 'false'; ?>,
            isAdmin: <?php echo $isAdmin ? 'true' : 'false'; ?>
        };
    </script>
</head>
<body>

    <!-- Contentores de UI Globais (Toasts) -->
    <div class="toast-container"></div>
    <?php require_once __DIR__ . '/flash_messages.php'; ?>

    <?php if ($isLoggedIn): ?>
    <!-- LAYOUT DA APLICAÇÃO LOGADA -->
    <div class="app-layout">
        
        <!-- SIDEBAR DE NAVEGAÇÃO ESQUERDA (DESKTOP) -->
        <aside class="sidebar-left">
            <div class="logo-container">
                <i data-lucide="hard-hat" style="color:var(--accent-primary); width:28px; height:28px;"></i>
                <span class="logo-text">Constrói Já</span>
            </div>
            
            <!-- Barra de Pesquisa Global no Sidebar -->
            <div style="position:relative; margin-bottom: 24px;">
                <div style="position:relative;">
                    <input type="text" id="global-search-input" class="form-control" placeholder="Pesquisar..." style="padding-left:36px; padding-right:12px; font-size:13px; height:36px; background:rgba(255,255,255,0.02);">
                    <i data-lucide="search" style="position:absolute; left:12px; top:9px; width:16px; height:16px; color:var(--text-muted);"></i>
                </div>
                <div id="global-search-results" class="card" style="position:absolute; top:42px; left:0; right:0; z-index:1000; padding:0; display:none; max-height:300px; overflow-y:auto; border-radius:var(--radius-sm);"></div>
            </div>

            <nav class="nav-menu">
                <li>
                    <a href="/feed" class="nav-link <?php echo $currentUri === '/feed' ? 'active' : ''; ?>">
                        <i data-lucide="layout"></i>
                        <span>Feed Social</span>
                    </a>
                </li>
                <li>
                    <a href="/projects" class="nav-link <?php echo strpos($currentUri, '/projects') === 0 ? 'active' : ''; ?>">
                        <i data-lucide="briefcase"></i>
                        <span>Minhas Obras</span>
                    </a>
                </li>
                <li>
                    <a href="/messages" class="nav-link <?php echo $currentUri === '/messages' ? 'active' : ''; ?>">
                        <i data-lucide="message-square"></i>
                        <span>Mensagens</span>
                    </a>
                </li>
                <li>
                    <a href="/notifications" class="nav-link <?php echo $currentUri === '/notifications' ? 'active' : ''; ?>" style="position:relative;">
                        <i data-lucide="bell"></i>
                        <span>Notificações</span>
                        <span class="badge badge-danger notification-badge" style="position:absolute; right:16px; top:12px; display:none; padding: 2px 6px;">0</span>
                    </a>
                </li>
                <li>
                    <a href="/qna" class="nav-link <?php echo strpos($currentUri, '/qna') === 0 ? 'active' : ''; ?>">
                        <i data-lucide="help-circle"></i>
                        <span>Q&A Técnico</span>
                    </a>
                </li>
                <li>
                    <a href="/subscription" class="nav-link <?php echo $currentUri === '/subscription' ? 'active' : ''; ?>">
                        <i data-lucide="award" style="color:var(--accent-primary);"></i>
                        <span style="color:var(--accent-primary); font-weight:600;">Assinatura VIP</span>
                    </a>
                </li>
                <li>
                    <a href="/marketplace" class="nav-link <?php echo $currentUri === '/marketplace' ? 'active' : ''; ?>">
                        <i data-lucide="shopping-bag" style="color:var(--accent-success);"></i>
                        <span>Parceiros B2B</span>
                    </a>
                </li>
                <li>
                    <a href="/profile/<?php echo sanitize($currentUser['username'] ?? ''); ?>" class="nav-link <?php echo $currentUri === '/profile/' . ($currentUser['username'] ?? '') ? 'active' : ''; ?>">
                        <i data-lucide="user"></i>
                        <span>Meu Perfil</span>
                    </a>
                </li>
                <li>
                    <a href="/portfolio-settings" class="nav-link <?php echo $currentUri === '/portfolio-settings' ? 'active' : ''; ?>">
                        <i data-lucide="folder-git-2"></i>
                        <span>Portfólio Público</span>
                    </a>
                </li>
                <li>
                    <a href="/profile/settings" class="nav-link <?php echo $currentUri === '/profile/settings' ? 'active' : ''; ?>">
                        <i data-lucide="settings"></i>
                        <span>Definições</span>
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                <li>
                    <a href="/admin" class="nav-link <?php echo strpos($currentUri, '/admin') === 0 ? 'active' : ''; ?>" style="border-left-color: var(--accent-secondary);">
                        <i data-lucide="shield" style="color:var(--accent-secondary);"></i>
                        <span style="color:var(--accent-secondary);">Administração</span>
                    </a>
                </li>
                <?php endif; ?>
            </nav>
            
            <div class="nav-footer">
                <a href="#" data-jsaction="App.logout" data-jsprevent="1" class="nav-link" style="color:var(--accent-danger);">
                    <i data-lucide="log-out"></i>
                    <span>Sair</span>
                </a>
            </div>
        </aside>



        <!-- CONTEÚDO CENTRAL PRINCIPAL DA APLICAÇÃO -->
        <main class="main-wrapper">
    <?php endif; ?>
